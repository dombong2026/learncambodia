<?php

namespace Database\Seeders;

use App\Models\CourseExam;
use App\Models\ExamType;
use App\Support\AssessmentQuestions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ExpandedSpecializedPracticeExamSeeder
{
    public const KEY_PREFIX = 'specialized-practice-v2-';

    private const BANKS = [
        'A1' => [['a1-present', 'a1-articles'], ['a1-food', 'a1-home', 'a1-clothes'], 'a1-reading'],
        'A2' => [['a2-past', 'a2-countable'], ['a2-health', 'a2-weather', 'a2-travel'], 'a2-reading'],
        'B1' => [['b1-perfect', 'b1-passive'], ['b1-environment', 'b1-stories', 'b1-work'], 'b1-reading'],
        'B2' => [['b2-reported', 'b2-connectors'], ['b2-journalism', 'b2-tech', 'b2-community'], 'b2-reading'],
        'C1' => [['c1-inversion', 'c1-clefts'], ['c1-policy', 'c1-economy', 'c1-editing'], 'c1-reading'],
    ];

    public function run(): Collection
    {
        $forms = $this->forms();

        return DB::transaction(function () use ($forms): Collection {
            $exams = new Collection;
            $types = [];

            foreach ($forms as [$key, $level, $title, $questions]) {
                $starterKey = self::KEY_PREFIX.$key;
                $exam = CourseExam::withTrashed()->where('starter_key', $starterKey)->lockForUpdate()->first();
                if ($exam !== null) {
                    if ($exam->trashed() || !$exam->isSpecialized() || $exam->kind !== 'exam') {
                        throw new RuntimeException("The exam key is used by an unavailable record: $key.");
                    }
                } else {
                    $type = $types[$level] ??= ExamType::firstOrCreate(
                        ['kind' => 'exam', 'name' => "$level English Practice Exam"],
                        ['description' => "Independent CEFR $level English practice in grammar, vocabulary and reading."]
                    );
                    $exam = new CourseExam([
                        'kind' => 'exam',
                        'exam_type_id' => $type->id,
                        'viewer_mode' => 'specialized',
                        'title' => "$level Practice Exam: $title",
                        'content_language' => 'English',
                        'instructions_html' => '<p>Answer 50 multiple-choice questions in 120 minutes. Each correct answer is worth one point.</p>'
                            .'<p>Questions 1–15 cover grammar, 16–35 cover vocabulary, and 36–50 cover reading. Read each passage before answering its questions. Submit before the timer reaches zero.</p>',
                        'questions' => $questions,
                        'time_limit' => 120,
                        'passing_percent' => 70,
                        'attempt_limit' => 2,
                        'live_feedback' => false,
                        'shuffle' => false,
                        'published' => true,
                    ]);
                    $exam->forceFill(['starter_key' => $starterKey])->save();
                }

                $exams->push($exam);
            }

            return $exams;
        });
    }

    private function forms(): array
    {
        $forms = [];
        $questionKeys = [];
        foreach (self::BANKS as $level => [$grammarKeys, $vocabularyKeys, $readingKey]) {
            $sources = CourseExam::where('kind', 'quiz')->whereIn('starter_key', [...$grammarKeys, ...$vocabularyKeys, $readingKey])
                ->get(['starter_key', 'questions'])->keyBy('starter_key');

            foreach ($sources as $source) {
                if (count($source->questions ?? []) !== 20) {
                    throw new RuntimeException("Expected 20 questions in the starter quiz: {$source->starter_key}.");
                }
            }
            if ($sources->count() !== 6) {
                throw new RuntimeException("Missing starter quiz source for level $level.");
            }

            $combine = fn (array $keys): array => array_merge(...array_map(fn (string $key): array => $sources[$key]->questions, $keys));
            $grammar = $combine($grammarKeys);
            $vocabulary = $combine($vocabularyKeys);
            $reading = $sources[$readingKey]->questions;
            if (array_values(array_count_values(array_column($reading, 'passage'))) !== [5, 5, 5, 5]
                || array_map(fn (array $items): int => count(array_unique(array_column($items, 'passage'))), array_chunk($reading, 5)) !== [1, 1, 1, 1]) {
                throw new RuntimeException("Expected four consecutive groups of five reading questions for $level.");
            }

            foreach ($this->authoredForms($level) as $index => [$key, $title, $authored]) {
                $questions = AssessmentQuestions::validate([
                    ...array_slice($grammar, $index * 10, 10),
                    ...array_slice($authored, 0, 5),
                    ...array_slice($vocabulary, $index * 15, 15),
                    ...array_slice($authored, 5, 5),
                    ...array_slice($reading, $index * 5, 5),
                    ...array_slice($authored, 10, 10),
                ]);

                foreach ($questions as $position => $question) {
                    $questionKey = mb_strtolower(trim(($question['passage'] ?? '')."\n".$question['prompt']));
                    $format = $position < 35 ? 'choice' : 'reading';
                    if (isset($questionKeys[$questionKey]) || $question['format'] !== $format
                        || $question['points'] !== 1 || blank($question['feedback'] ?? null)) {
                        throw new RuntimeException("Invalid or repeated question in $key at item ".($position + 1).'.');
                    }
                    $questionKeys[$questionKey] = true;
                }

                if (isset($forms[$key]) || count($questions) !== 50 || count(array_unique(array_column($questions, 'passage'))) !== 3) {
                    throw new RuntimeException("Expected a new exam key and 50 questions with three reading passages: $key.");
                }
                $forms[$key] = [$key, $level, $title, $questions];
            }
        }

        return $forms;
    }

    private function authoredForms(string $level): array
    {
        $path = base_path('support/editorial/specialized-practice/'.strtolower($level).'.txt');
        $blocks = preg_split('/\R\h*\R/u', trim(file_get_contents($path)));
        if (count($blocks) !== 4) {
            throw new RuntimeException("Expected four new exam forms for $level.");
        }

        $forms = [];
        foreach ($blocks as $block) {
            $lines = preg_split('/\R/u', $block);
            [$key, $declaredLevel, $title] = array_pad(explode('|', array_shift($lines), 3), 3, '');
            $key = ltrim($key, '@');
            if (!preg_match('/^'.strtolower($level).'-[a-z0-9-]+$/D', $key) || $declaredLevel !== $level || blank($title)) {
                throw new RuntimeException("Invalid exam header: $key.");
            }

            $questions = [];
            $passages = [];
            $passage = null;
            foreach ($lines as $line) {
                if (str_starts_with($line, '> ')) {
                    $passage = trim(substr($line, 2));
                    if ($passage === '' || isset($passages[$passage])) {
                        throw new RuntimeException("Empty or repeated passage in $key.");
                    }
                    $passages[$passage] = 0;
                    continue;
                }

                $parts = array_map('trim', explode('|', $line));
                if (count($parts) !== 4 || in_array('', $parts, true)
                    || count(array_unique(array_map(mb_strtolower(...), array_slice($parts, 1)))) !== 3) {
                    throw new RuntimeException("Invalid question in $key at item ".(count($questions) + 1).'.');
                }
                [$prompt, $correct, $distractorOne, $distractorTwo] = $parts;
                $options = [$correct, $distractorOne, $distractorTwo];
                $offset = (count($questions) + crc32($key)) % count($options);
                $options = [...array_slice($options, $offset), ...array_slice($options, 0, $offset)];
                $letters = ['a', 'b', 'c'];
                $question = [
                    'format' => $passage === null ? 'choice' : 'reading',
                    'prompt' => $prompt,
                    'correct' => $letters[array_search($correct, $options, true)],
                    'points' => 1,
                    'feedback' => $passage === null
                        ? 'Correct sentence: '.str_replace('____', $correct, $prompt)
                        : 'The passage supports this answer: '.$correct,
                ] + array_combine($letters, $options);

                if ($passage !== null) {
                    $question['passage'] = $passage;
                    $passages[$passage]++;
                } elseif (substr_count($prompt, '____') !== 1) {
                    throw new RuntimeException("Expected one blank in a grammar or vocabulary question: $key.");
                }

                $questions[] = $question;
            }

            if (count($questions) !== 20 || array_values($passages) !== [5, 5]) {
                throw new RuntimeException("Expected ten standalone questions and two passages with five questions each: $key.");
            }
            $forms[] = [$key, $title, $questions];
        }

        return $forms;
    }
}
