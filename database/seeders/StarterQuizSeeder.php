<?php

namespace Database\Seeders;

use App\Models\CourseExam;
use App\Models\EditorMedia;
use App\Models\ExamType;
use App\Models\User;
use App\Support\AssessmentQuestions;
use App\Support\ManagedFiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class StarterQuizSeeder
{
    private const PUBLIC_PRACTICE = [
        'a1-present', 'a1-reading',
        'a2-past', 'a2-travel',
        'b1-perfect', 'b1-work',
        'b2-connectors', 'b2-reading',
        'c1-editing', 'c1-reading',
    ];

    public function publishPractice(): int
    {
        return DB::transaction(function (): int {
            $quizzes = CourseExam::withTrashed()
                ->whereIn('starter_key', self::PUBLIC_PRACTICE)->orderBy('id')->lockForUpdate()->get()
                ->keyBy('starter_key');

            foreach (self::PUBLIC_PRACTICE as $key) {
                $quiz = $quizzes->get($key);
                if (!$quiz || $quiz->trashed() || $quiz->deactivated_at || $quiz->kind !== 'quiz' || $quiz->course_id !== null) {
                    throw new RuntimeException("The practice quiz is missing or unavailable: $key.");
                }

                $questions = AssessmentQuestions::validate($quiz->questions ?? []);
                if (count($questions) !== 20 || collect($questions)->contains(fn (array $item): bool =>
                    !AssessmentQuestions::scored($item) || AssessmentQuestions::manual($item) || blank($item['feedback'] ?? null))) {
                    throw new RuntimeException("The practice quiz needs 20 auto-graded questions with feedback: $key.");
                }

                $quiz->update([
                    'time_limit' => 20,
                    'attempt_limit' => 2,
                    'live_feedback' => true,
                    'viewer_mode' => 'specialized',
                    'pricing' => null,
                    'published' => true,
                ]);
            }

            return $quizzes->count();
        });
    }

    public function run(?int $authorId = null): int
    {
        $bank = $this->bank();
        $disk = ManagedFiles::disk();
        $paths = [];

        try {
            return DB::transaction(function () use ($bank, $authorId, $disk, &$paths): int {
                $author = User::query()
                    ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERADMIN])
                    ->when($authorId !== null, fn ($query) => $query->whereKey($authorId))
                    ->orderBy('id')->lockForUpdate()->firstOrFail();

                $types = [];
                $created = 0;

                foreach ($bank as $key => $entry) {
                    if (CourseExam::withTrashed()->where('starter_key', $key)->exists()) {
                        continue;
                    }

                    $level = $entry['level'];
                    $type = $types[$level] ??= ExamType::firstOrCreate(
                        ['kind' => 'quiz', 'name' => "$level English Practice"],
                        ['description' => "English practice at CEFR level $level."]
                    );

                    $path = 'editor-media/starter-quiz-'.Str::uuid().'.webp';
                    $paths[] = $path;
                    if (!ManagedFiles::put($disk, $path, $entry['cover'])) {
                        throw new RuntimeException("Could not save the featured image for $key.");
                    }

                    $media = EditorMedia::create([
                        'uploaded_by' => $author->id,
                        'context' => 'library',
                        'path' => $path,
                        'mime' => 'image/webp',
                        'original_name' => Str::slug($entry['title']).'.webp',
                        'size' => strlen($entry['cover']),
                    ]);

                    $quiz = new CourseExam([
                        'kind' => 'quiz',
                        'exam_type_id' => $type->id,
                        'title' => $entry['title'],
                        'content_language' => 'English',
                        'featured_kind' => 'image',
                        'featured_media_id' => $media->id,
                        'featured_alt' => "{$entry['title']}, CEFR $level English practice",
                        'instructions_html' => '<p>'.match ($entry['skill']) {
                            'reading' => 'Read each passage and choose the answer best supported by the text.',
                            'grammar' => 'Choose the option that completes each sentence.',
                            'vocabulary' => 'Choose the word or phrase that matches each definition.',
                        }.'</p>',
                        'questions' => $entry['questions'],
                        'time_limit' => 20,
                        'passing_percent' => 70,
                        'attempt_limit' => 3,
                        'live_feedback' => true,
                        'shuffle' => false,
                        'gamified' => false,
                        'published' => false,
                    ]);

                    $quiz->forceFill(['starter_key' => $key])->save();
                    $created++;
                }

                return $created;
            });
        } catch (Throwable $exception) {
            if ($paths !== []) {
                $disk->delete($paths);
            }
            throw $exception;
        }
    }

    public function questionsByStarterKey(): array
    {
        return array_map(fn (array $entry): array => $entry['questions'], $this->bank(includeCovers: false));
    }

    private function bank(bool $includeCovers = true): array
    {
        $directory = base_path('support/editorial/quiz-starter');
        $bank = [];
        $titles = [];
        $coverHashes = [];

        foreach (['grammar', 'vocabulary', 'reading'] as $skill) {
            $source = trim(file_get_contents("$directory/$skill.txt"));

            foreach (preg_split('/\R\h*\R/u', $source) as $block) {
                $lines = preg_split('/\R/u', $block);
                [$key, $level, $declaredSkill, $title] = array_pad(
                    explode('|', ltrim(array_shift($lines), '@'), 4), 4, ''
                );

                if (!preg_match('/^[a-z][a-z0-9-]+$/D', $key) || isset($bank[$key])
                    || $declaredSkill !== $skill || !in_array($level, ['A1', 'A2', 'B1', 'B2', 'C1'], true)
                    || blank($title) || mb_strlen($title) > 160 || isset($titles[$title])) {
                    throw new RuntimeException("Invalid quiz header: $key.");
                }

                $cover = null;
                if ($includeCovers) {
                    $filepath = "$directory/covers/$key.webp";
                    $image = is_file($filepath) ? getimagesize($filepath) : false;
                    if (!$image || $image[2] !== IMAGETYPE_WEBP || $image[0] !== 960 || $image[1] !== 540) {
                        throw new RuntimeException("Invalid featured image: $key.");
                    }

                    $cover = file_get_contents($filepath);
                    $hash = hash('sha256', $cover);
                    if (isset($coverHashes[$hash])) {
                        throw new RuntimeException("Duplicate featured image: $key.");
                    }
                    $coverHashes[$hash] = true;
                }

                $bank[$key] = [
                    'level' => $level,
                    'skill' => $skill,
                    'title' => $title,
                    'cover' => $cover,
                    'questions' => $this->questions($key, $skill, $lines),
                ];
                $titles[$title] = true;
            }
        }

        foreach (['A1', 'A2', 'B1', 'B2', 'C1'] as $level) {
            $quizzes = array_filter($bank, fn (array $entry): bool => $entry['level'] === $level);
            $counts = array_count_values(array_column($quizzes, 'skill'));
            if ($counts !== ['grammar' => 4, 'vocabulary' => 5, 'reading' => 1]) {
                throw new RuntimeException("Invalid quiz distribution for $level.");
            }
        }

        return $bank;
    }

    private function questions(string $key, string $skill, array $lines): array
    {
        $questions = [];
        $prompts = [];
        $passage = null;
        $passages = [];
        $letter = ['a', 'b', 'c', 'd'];
        $terms = [];

        if ($skill === 'vocabulary') {
            if (count($lines) !== 20) {
                throw new RuntimeException("Expected 20 terms for $key.");
            }
            $terms = array_map(fn (string $line): string => Str::before($line, '|'), $lines);
        }

        foreach ($lines as $line) {
            if ($skill === 'reading' && str_starts_with($line, '>')) {
                $passage = trim(substr($line, 1));
                if ($passage === '' || isset($passages[$passage])) {
                    throw new RuntimeException("Invalid reading passage in $key.");
                }
                $passages[$passage] = 0;
                continue;
            }

            $index = count($questions);
            if ($skill === 'vocabulary') {
                [$correct, $definition] = array_pad(explode('|', $line, 2), 2, '');
                $prompt = $definition;
                $options = [$correct, $terms[($index + 3) % 20], $terms[($index + 8) % 20], $terms[($index + 13) % 20]];
                $explanation = "$correct — $definition.";
            } else {
                [$prompt, $correct, $second, $third] = array_pad(
                    explode('|', $skill === 'reading' ? ltrim($line, '|') : $line, 4), 4, ''
                );
                $options = [$correct, $second, $third];

                if ($skill === 'grammar') {
                    if (substr_count($prompt, '____') !== 1) {
                        throw new RuntimeException("Expected one blank in $key at item ".($index + 1).'.');
                    }
                    $explanation = 'Correct sentence: '.str_replace('____', $correct, $prompt);
                } else {
                    if ($passage === null) {
                        throw new RuntimeException("Reading question precedes its passage in $key.");
                    }
                    $passages[$passage]++;
                    $explanation = "The passage supports this answer: $correct";
                }
            }

            $normalized = array_map(fn (string $value): string => mb_strtolower(trim($value)), $options);
            $promptKey = mb_strtolower($passage."\n".$prompt);
            if (blank($prompt) || in_array('', $normalized, true) || count($normalized) !== count(array_unique($normalized))
                || isset($prompts[$promptKey])) {
                throw new RuntimeException("Invalid options or prompt in $key at item ".($index + 1).'.');
            }
            $prompts[$promptKey] = true;

            $offset = ($index + crc32($key)) % count($options);
            $options = [...array_slice($options, $offset), ...array_slice($options, 0, $offset)];
            $question = [
                'format' => $skill === 'reading' ? 'reading' : 'choice',
                'prompt' => $prompt,
                'correct' => $letter[array_search($correct, $options, true)],
                'points' => 1,
                'feedback' => $explanation,
            ];

            if ($passage !== null) {
                $question['passage'] = $passage;
            }

            foreach ($options as $position => $option) {
                $question[$letter[$position]] = $option;
            }
            $questions[] = $question;
        }

        if (count($questions) !== 20 || ($skill === 'reading' && array_values($passages) !== [5, 5, 5, 5])) {
            throw new RuntimeException("Invalid question count for $key.");
        }

        return AssessmentQuestions::validate($questions);
    }
}
