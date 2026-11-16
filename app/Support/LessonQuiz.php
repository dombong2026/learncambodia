<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class LessonQuiz
{
    public const MAX_QUESTIONS = 10;

    public const CHOICES = ['a', 'b', 'c', 'd'];

    public static function rememberResult(int $studentId, int $enrollmentId, int $lessonId, ?array $answers): void
    {
        Cache::put("lesson-quiz-result:{$studentId}", [
            'enrollment' => $enrollmentId,
            'lesson' => $lessonId,
            'answers' => $answers,
        ], now()->addMinutes(30));
    }

    public static function resultFor(Request $request, int $enrollmentId, int $lessonId): ?array
    {
        $studentId = $request->user()->id;
        $key = "lesson-quiz-result:{$studentId}";
        if (!$request->attributes->has($key)) {
            $request->attributes->set($key, Cache::get($key));
        }
        $result = $request->attributes->get($key);

        return ($result['enrollment'] ?? null) === $enrollmentId && ($result['lesson'] ?? null) === $lessonId
            ? $result
            : null;
    }

    public static function validateQuestions(Request $request): ?array
    {
        $data = $request->validate([
            'quiz_questions' => ['sometimes', 'array', 'list', 'max:'.self::MAX_QUESTIONS],
            'quiz_questions.*' => ['required', 'array:prompt,choices,correct,feedback'],
            'quiz_questions.*.prompt' => ['required', 'string', 'max:500'],
            'quiz_questions.*.choices' => ['required', 'array:a,b,c,d'],
            'quiz_questions.*.choices.a' => ['required', 'string', 'max:300'],
            'quiz_questions.*.choices.b' => ['required', 'string', 'max:300'],
            'quiz_questions.*.choices.c' => ['nullable', 'string', 'max:300'],
            'quiz_questions.*.choices.d' => ['nullable', 'string', 'max:300'],
            'quiz_questions.*.correct' => ['required', Rule::in(self::CHOICES)],
            'quiz_questions.*.feedback' => ['nullable', 'string', 'max:700'],
        ]);

        $questions = [];
        foreach ($data['quiz_questions'] ?? [] as $index => $question) {
            $choices = array_filter($question['choices'], fn ($choice) => filled($choice));
            if (!isset($choices[$question['correct']])) {
                throw ValidationException::withMessages([
                    "quiz_questions.{$index}.correct" => 'Enter text for the correct answer choice.',
                ]);
            }

            $questions[] = [
                'prompt' => $question['prompt'],
                'choices' => array_intersect_key(array_replace(array_fill_keys(self::CHOICES, null), $choices), $choices),
                'correct' => $question['correct'],
                'feedback' => $question['feedback'] ?? null,
            ];
        }

        return $questions === [] ? null : $questions;
    }
}
