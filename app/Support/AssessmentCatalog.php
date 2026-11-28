<?php

namespace App\Support;

use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class AssessmentCatalog
{
    public const LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
    public const DURATIONS = [15, 30, 60, 120];
    public const SKILLS = [
        'grammar' => ['grammar', 'grammatical', 'completes each sentence', 'sentence completion'],
        'vocabulary' => ['vocabulary', 'matches each definition', 'word meaning', 'word choice'],
        'reading' => ['reading', 'read each passage', 'read the passage'],
        'listening' => ['listening', 'listen to'],
        'writing' => ['writing', 'written response'],
        'speaking' => ['speaking', 'spoken response'],
    ];

    public static function published(string $kind): Builder
    {
        return CourseExam::query()->whereNull('course_id')->where('kind', $kind)->where('published', true);
    }

    public static function level(Builder $items, string $level): Builder
    {
        return $items->where(fn (Builder $match) => $match
            ->whereLike('title', $level.' %')->orWhereLike('title', $level.':%')
            ->orWhereHas('type', fn (Builder $type) => $type->whereLike('name', $level.' %')->orWhere('name', $level)));
    }

    public static function skill(Builder $items, string $skill): Builder
    {
        return $items->where(function (Builder $match) use ($skill): void {
            foreach (self::SKILLS[$skill] as $phrase) {
                $pattern = '%'.$phrase.'%';
                $match->orWhereLike('title', $pattern)->orWhereLike('instructions_html', $pattern)
                    ->orWhereHas('type', fn (Builder $type) => $type->whereLike('name', $pattern));
            }

            if (!in_array($skill, ['reading', 'listening', 'writing', 'speaking'], true)) {
                return;
            }

            $driver = $match->getModel()->getConnection()->getDriverName();
            if ($driver === 'sqlite') {
                $match->orWhereRaw("exists (select 1 from json_each(course_exams.questions) where json_extract(json_each.value, '$.format') = ?)", [$skill]);
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $match->orWhereRaw("json_search(course_exams.questions, 'one', ?, null, '$[*].format') is not null", [$skill]);
            }
        });
    }

    public static function nextPractice(ExamAttempt $attempt, ?User $user): ?CourseExam
    {
        $source = $attempt->exam()->withTrashed()->with(['type', 'course'])->first();
        $level = $source?->managerLevel();
        $title = strtolower(($attempt->snapshot['title'] ?? '').' '.strip_tags($attempt->snapshot['instructions_html'] ?? ''));
        $skill = null;
        foreach (self::SKILLS as $key => $phrases) {
            if (Str::contains($title, $phrases)) {
                $skill = $key;
                break;
            }
        }

        $missed = [];
        foreach ($attempt->snapshot['questions'] as $index => $question) {
            $format = $question['format'] ?? 'choice';
            if (isset(self::SKILLS[$format]) && in_array(AssessmentQuestions::reviewStatus(
                $question, (string) ($attempt->answers[$index] ?? ''), $attempt->grading[$index] ?? null,
            ), ['incorrect', 'partial', 'unanswered'], true)) {
                $missed[$format] = ($missed[$format] ?? 0) + 1;
            }
        }
        arsort($missed);
        $skill = array_key_first($missed) ?? $skill;

        if (!$level && preg_match('/^(A1|A2|B1|B2|C1|C2)\b/i', $title, $match)) {
            $level = strtoupper($match[1]);
        }
        if (!$skill && !$level) {
            return null;
        }

        $quizzes = self::published('quiz')->whereKeyNot($attempt->course_exam_id)
            ->where(fn (Builder $price) => $price->whereNull('pricing')->orWhere('pricing->model', 'free'))
            ->when($user?->role !== User::ROLE_STUDENT, fn (Builder $query) => $query->where('viewer_mode', 'specialized'))
            ->orderBy('time_limit')->orderBy('title')->orderBy('id');

        $levelQuizzes = $level ? self::level(clone $quizzes, $level) : $quizzes;
        if ($skill && ($match = self::skill(clone $levelQuizzes, $skill)->first())) {
            return $match;
        }

        return $level ? $levelQuizzes->first() : null;
    }
}
