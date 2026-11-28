<?php

namespace Database\Seeders;

use App\Models\CourseExam;
use App\Models\ExamType;
use App\Support\AssessmentQuestions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SpecializedPracticeExamSeeder
{
    public const KEY_PREFIX = 'specialized-practice-v1-';

    private const FORMS = [
        'a1-school' => ['A1', 'A1 Practice Exam: School and Everyday English', 'a1-be', 'a1-school'],
        'a1-town' => ['A1', 'A1 Practice Exam: Places and Directions', 'a1-place', 'a1-town'],
        'a2-plans' => ['A2', 'A2 Practice Exam: Plans and Everyday Services', 'a2-future', 'a2-services'],
        'a2-digital' => ['A2', 'A2 Practice Exam: Comparisons and Digital Life', 'a2-comparison', 'a2-digital'],
        'b1-travel' => ['B1', 'B1 Practice Exam: Travel and Decisions', 'b1-conditionals', 'b1-travel'],
        'b1-learning' => ['B1', 'B1 Practice Exam: Learning and Community', 'b1-relative', 'b1-student'],
        'b2-work' => ['B2', 'B2 Practice Exam: Workplace Reasoning', 'b2-modals', 'b2-business'],
        'b2-academic' => ['B2', 'B2 Practice Exam: Academic Reading and Language', 'b2-verb-patterns', 'b2-academic'],
        'c1-research' => ['C1', 'C1 Practice Exam: Research and Precision', 'c1-clauses', 'c1-research'],
        'c1-argument' => ['C1', 'C1 Practice Exam: Argument and Implication', 'c1-counterfactual', 'c1-argument'],
    ];

    public function run(): Collection
    {
        $readings = $this->readingQuestions();

        return DB::transaction(function () use ($readings): Collection {
            $forms = new Collection;
            $types = [];

            foreach (self::FORMS as $key => [$level, $title, $grammar, $vocabulary]) {
                $exam = CourseExam::withTrashed()->where('starter_key', self::KEY_PREFIX.$key)->lockForUpdate()->first();
                if ($exam !== null) {
                    if ($exam->trashed() || !$exam->isSpecialized() || $exam->kind !== 'exam') {
                        throw new RuntimeException("The exam key is already used by an unavailable record: $key.");
                    }

                    $forms->push($exam);
                    continue;
                }

                $banks = CourseExam::where('kind', 'quiz')->whereIn('starter_key', [$grammar, $vocabulary])
                    ->get(['starter_key', 'questions'])->keyBy('starter_key');
                $grammarQuestions = $banks->get($grammar)?->questions ?? [];
                $vocabularyQuestions = $banks->get($vocabulary)?->questions ?? [];
                $reading = $readings[$key] ?? [];
                if (count($grammarQuestions) !== 20 || count($vocabularyQuestions) !== 20 || count($reading) !== 10) {
                    throw new RuntimeException("Expected 20 grammar, 20 vocabulary and 10 reading questions: $key.");
                }

                $questions = AssessmentQuestions::validate([...$grammarQuestions, ...$vocabularyQuestions, ...$reading]);
                if (count(array_unique(array_column($questions, 'prompt'))) !== 50
                    || collect($questions)->contains(fn (array $question): bool =>
                        !in_array($question['format'], ['choice', 'reading'], true) || $question['points'] !== 1)) {
                    throw new RuntimeException("Expected 50 distinct multiple-choice questions worth one point each: $key.");
                }

                $type = $types[$level] ??= ExamType::firstOrCreate(
                    ['kind' => 'exam', 'name' => "$level English Practice Exam"],
                    ['description' => "Independent CEFR $level English practice in grammar, vocabulary and reading."]
                );
                $exam = new CourseExam([
                    'kind' => 'exam',
                    'exam_type_id' => $type->id,
                    'viewer_mode' => 'specialized',
                    'title' => $title,
                    'content_language' => 'English',
                    'instructions_html' => '<p>Answer 50 multiple-choice questions in 120 minutes. Each correct answer is worth one point.</p>'
                        .'<p>Questions 1–20 cover grammar, 21–40 cover vocabulary, and 41–50 cover reading. Read each passage before answering its questions. Submit before the timer reaches zero.</p>',
                    'questions' => $questions,
                    'time_limit' => 120,
                    'passing_percent' => 70,
                    'attempt_limit' => 2,
                    'live_feedback' => false,
                    'shuffle' => false,
                    'published' => true,
                ]);
                $exam->forceFill(['starter_key' => self::KEY_PREFIX.$key])->save();
                $forms->push($exam);
            }

            return $forms;
        });
    }

    private function readingQuestions(): array
    {
        $source = file_get_contents(base_path('support/editorial/specialized-practice-reading.txt'));
        $forms = [];

        foreach (preg_split('/\R\h*\R/u', trim($source)) as $block) {
            $lines = preg_split('/\R/u', $block);
            $key = ltrim(array_shift($lines), '@');
            if (!isset(self::FORMS[$key]) || isset($forms[$key])) {
                throw new RuntimeException("Unknown or repeated reading form: $key.");
            }

            $questions = [];
            $passages = [];
            $passage = null;
            foreach ($lines as $line) {
                if (str_starts_with($line, '> ')) {
                    $passage = trim(substr($line, 2));
                    if ($passage === '' || isset($passages[$passage])) {
                        throw new RuntimeException("Missing or repeated passage: $key.");
                    }
                    $passages[$passage] = 0;
                    continue;
                }

                $parts = explode('|', $line);
                if ($passage === null || count($parts) !== 4 || in_array('', $parts, true)
                    || count(array_unique(array_slice($parts, 1))) !== 3) {
                    throw new RuntimeException("Invalid reading question: $key.");
                }

                [$prompt, $correct, $distractorOne, $distractorTwo] = $parts;
                $options = [$correct, $distractorOne, $distractorTwo];
                $offset = (count($questions) + crc32($key)) % 3;
                $options = [...array_slice($options, $offset), ...array_slice($options, 0, $offset)];
                $letters = ['a', 'b', 'c'];
                $questions[] = [
                    'format' => 'reading',
                    'prompt' => $prompt,
                    'passage' => $passage,
                    'correct' => $letters[array_search($correct, $options, true)],
                    'points' => 1,
                    'feedback' => 'Correct answer: '.$correct,
                ] + array_combine($letters, $options);
                $passages[$passage]++;
            }

            if (array_values($passages) !== [5, 5]) {
                throw new RuntimeException("Expected two passages with five questions each: $key.");
            }
            $forms[$key] = $questions;
        }

        return $forms;
    }
}
