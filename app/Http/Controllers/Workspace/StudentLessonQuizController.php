<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Support\LearningAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class StudentLessonQuizController extends Controller
{
    public function store(Request $request, CourseEnrollment $enrollment, CourseLesson $lesson): RedirectResponse
    {
        return DB::transaction(function () use ($request, $enrollment, $lesson): RedirectResponse {
            $locked = CourseEnrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $course = LearningAccess::course($request->user(), $locked);
            LearningAccess::lesson($course, $lesson);
            abort_if(blank($lesson->quiz_questions), 404);

            $data = $request->validate(['retry' => ['nullable', 'integer', 'min:1']]);
            $attempts = $locked->lessonQuizAttempts()->where('lesson_id', $lesson->id);
            $parent = isset($data['retry']) ? $attempts->findOrFail($data['retry']) : null;
            $questions = $parent ? $parent->missedQuestions() : $lesson->quiz_questions;
            abort_if($questions === [], 404);

            $rules = ['answers' => ['required', 'array', 'list', 'size:'.count($questions)]];
            foreach ($questions as $index => $question) {
                $rules["answers.{$index}"] = ['required', Rule::in(array_keys($question['choices']))];
            }

            $route = [$enrollment, $lesson, 'viewer' => 'quiz'] + $request->only('lang');
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $route += $parent ? ['retry' => $parent->id] : ['start' => 1];
                return redirect()->to(route('account.lessons.watch', $route)."#lesson-quiz-{$lesson->id}")
                    ->withErrors(['answers' => __('focused_learning.answer_every_question')]);
            }

            $answers = $validator->validated()['answers'];
            $correctCount = count(array_filter($questions,
                fn (array $question, int $index) => $question['correct'] === $answers[$index],
                ARRAY_FILTER_USE_BOTH));
            $attempt = $locked->lessonQuizAttempts()->create([
                'lesson_id' => $lesson->id,
                'retry_of_id' => $parent?->id,
                'questions' => $questions,
                'answers' => $answers,
                'correct_count' => $correctCount,
            ]);

            return redirect()->to(route('account.lessons.watch', $route + ['attempt' => $attempt->id])
                ."#lesson-quiz-{$lesson->id}");
        });
    }
}
