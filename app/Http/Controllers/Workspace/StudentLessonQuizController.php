<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Support\LearningAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class StudentLessonQuizController extends Controller
{
    public function store(Request $request, CourseEnrollment $enrollment, CourseLesson $lesson): RedirectResponse
    {
        $course = LearningAccess::course($request->user(), $enrollment);
        LearningAccess::lesson($course, $lesson);
        $questions = $lesson->quiz_questions;
        abort_if(blank($questions), 404);

        $rules = ['answers' => ['required', 'array', 'list', 'size:'.count($questions)]];
        foreach ($questions as $index => $question) {
            $rules["answers.{$index}"] = ['required', Rule::in(array_keys($question['choices']))];
        }
        $return = $request->input('view') === 'curriculum'
            ? route('account.enrollments.show', $enrollment)
            : route('account.lessons.watch', [$enrollment, $lesson]);
        $return .= "#lesson-quiz-{$lesson->id}";

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->to($return)->with('lesson_quiz_result', [
                'enrollment' => $enrollment->id, 'lesson' => $lesson->id, 'invalid' => true,
            ]);
        }

        return redirect()->to($return)->with('lesson_quiz_result', [
            'enrollment' => $enrollment->id, 'lesson' => $lesson->id,
            'answers' => $validator->validated()['answers'],
        ]);
    }
}
