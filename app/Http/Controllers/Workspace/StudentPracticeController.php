<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\CourseQuiz;
use App\Models\PracticeAttempt;
use App\Support\LearningAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentPracticeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function index(Request $request): View
    {
        $enrollments = CourseEnrollment::query()
            ->where('student_id', $request->user()->id)
            ->where('status', 'approved')
            ->whereHas('course', fn ($query) => $query->published())
            ->with(['course.quizzes' => fn ($query) => $query->withCount('questions')])
            ->orderByDesc('id')->paginate(10);

        return view('workspace.student.practice-index', [
            'role' => 'student',
            'enrollments' => $enrollments,
        ]);
    }

    public function history(Request $request): View
    {
        $attempts = PracticeAttempt::query()
            ->whereHas('enrollment', fn ($query) =>
                $query->where('student_id', $request->user()->id))
            ->with('quiz.course')
            ->orderByDesc('submitted_at')
            ->paginate(10);

        return view('workspace.student.practice-history', [
            'role' => 'student',
            'attempts' => $attempts,
        ]);
    }

    public function show(Request $request, CourseEnrollment $enrollment, CourseQuiz $quiz): View
    {
        $course = LearningAccess::course($request->user(), $enrollment);
        LearningAccess::quiz($course, $quiz);

        $questions = $quiz->questions()->get([
            'id', 'course_quiz_id', 'prompt',
            'option_a', 'option_b', 'option_c', 'option_d', 'position',
        ]);
        abort_if($questions->isEmpty(), 404);

        $used = $enrollment->attempts()->where('course_quiz_id', $quiz->id)->count();

        return view('workspace.student.practice-show', [
            'role' => 'student', 'enrollment' => $enrollment, 'course' => $course,
            'quiz' => $quiz, 'questions' => $questions,
            'attemptsRemaining' => max(0, self::MAX_ATTEMPTS - $used),
        ]);
    }

    public function store(Request $request, CourseEnrollment $enrollment, CourseQuiz $quiz): RedirectResponse
    {
        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1', 'max:50'],
            'answers.*' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
        ]);

        $attempt = DB::transaction(function () use ($request, $enrollment, $quiz, $validated) {
            $locked = CourseEnrollment::whereKey($enrollment->id)
                ->lockForUpdate()->firstOrFail();
            $course = LearningAccess::course($request->user(), $locked);
            LearningAccess::quiz($course, $quiz);

            abort_if($locked->attempts()->where('course_quiz_id', $quiz->id)
                ->count() >= self::MAX_ATTEMPTS, 409, 'Practice attempt limit reached.');

            $questions = $quiz->questions()->get(['id', 'correct_option']);
            $answers = $validated['answers'];
            abort_unless($questions->isNotEmpty()
                && $questions->count() === count($answers), 422,
                'Answer each practice question exactly once.');

            $rows = [];
            $correct = 0;
            foreach ($questions as $question) {
                abort_unless(array_key_exists((string) $question->id, $answers), 422,
                    'Unknown or missing practice question.');
                $selected = $answers[$question->id];
                $ok = hash_equals($question->correct_option, $selected);
                $correct += (int) $ok;
                $rows[] = [
                    'question_id' => $question->id,
                    'selected_option' => $selected,
                    'is_correct' => $ok,
                ];
            }

            $total = $questions->count();
            $percent = (int) round($correct * 100 / $total);
            $attempt = $locked->attempts()->create([
                'course_quiz_id' => $quiz->id,
                'correct_count' => $correct,
                'question_count' => $total,
                'score_percent' => $percent,
                'passed' => $percent >= $quiz->passing_percent,
                'submitted_at' => now(),
            ]);

            $attempt->answers()->createMany($rows);

            return $attempt;
        });

        return redirect()->route('account.practice.result', [$enrollment, $quiz, $attempt])
            ->with('status', 'Practice answers scored. This is not an official exam.');
    }

    public function result(
        Request $request,
        CourseEnrollment $enrollment,
        CourseQuiz $quiz,
        PracticeAttempt $attempt
    ): View {
        abort_unless($enrollment->student_id === $request->user()->id, 404);
        abort_unless($attempt->enrollment_id === $enrollment->id
            && $attempt->course_quiz_id === $quiz->id
            && $quiz->course_id === $enrollment->course_id, 404);

        return view('workspace.student.practice-result', [
            'role' => 'student', 'enrollment' => $enrollment,
            'quiz' => $quiz, 'attempt' => $attempt->load('answers.question'),
        ]);
    }
}
