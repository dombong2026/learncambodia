<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseQuiz;
use App\Models\CourseQuizQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminQuizController extends Controller
{
    private function mutate(Course $course, callable $callback): mixed
    {
        return DB::transaction(function () use ($course, $callback) {
            $locked = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isMutable(), 409,
                'Quizzes are locked while the curriculum is under review or published.');

            return $callback($locked);
        });
    }

    private function owns(Course $course, CourseQuiz $quiz): void
    {
        abort_unless($quiz->course_id === $course->id, 404);
    }

    private function quizData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'position' => ['required', 'integer', 'min:1', 'max:999'],
            'passing_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
    }

    private function questionData(Request $request): array
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:1500'],
            'option_a' => ['required', 'string', 'max:500'],
            'option_b' => ['required', 'string', 'max:500'],
            'option_c' => ['required', 'string', 'max:500'],
            'option_d' => ['required', 'string', 'max:500'],
            'correct_option' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'position' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $choices = array_map(
            fn ($key) => mb_strtolower(trim($data[$key])),
            ['option_a', 'option_b', 'option_c', 'option_d']
        );

        if (count(array_unique($choices)) !== 4) {
            throw ValidationException::withMessages([
                'option_a' => 'All four answer options must be distinct.',
            ]);
        }

        return $data;
    }

    public function index(): View
    {
        return view('workspace.admin.quizzes', [
            'role' => 'admin',
            'quizzes' => CourseQuiz::with('course')->withCount('questions')
                ->orderByDesc('id')->paginate(10),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $data = $this->quizData($request);

        $quiz = $this->mutate($course, function (Course $locked) use ($data) {
            abort_if($locked->quizzes()->count() >= 10, 422,
                'Maximum of ten practice quizzes per course.');

            return $locked->quizzes()->create($data);
        });

        return redirect()->route('admin.quiz-builder.edit', [$course, $quiz])
            ->with('status', 'Practice quiz created in draft curriculum.');
    }

    public function edit(Course $course, CourseQuiz $quiz): View
    {
        $this->owns($course, $quiz);

        return view('workspace.admin.quiz-form', [
            'role' => 'admin', 'course' => $course,
            'quiz' => $quiz->load('questions'),
            'editable' => $course->isMutable(),
        ]);
    }

    public function update(Request $request, Course $course, CourseQuiz $quiz): RedirectResponse
    {
        $this->owns($course, $quiz);
        $data = $this->quizData($request);
        $this->mutate($course, fn () => $quiz->update($data));

        return back()->with('status', 'Quiz settings saved.');
    }

    public function destroy(Course $course, CourseQuiz $quiz): RedirectResponse
    {
        $this->owns($course, $quiz);
        $this->mutate($course, fn () => $quiz->delete());

        return redirect()->route('admin.course-builder.edit', $course)
            ->with('status', 'Draft quiz deleted.');
    }

    public function storeQuestion(Request $request, Course $course, CourseQuiz $quiz): RedirectResponse
    {
        $this->owns($course, $quiz);
        $data = $this->questionData($request);
        $this->mutate($course, function () use ($quiz, $data): void {
            abort_if($quiz->questions()->count() >= 50, 422,
                'Maximum of 50 practice questions per quiz.');
            $quiz->questions()->create($data);
        });

        return back()->with('status', 'Practice question added.');
    }

    public function updateQuestion(Request $request, Course $course, CourseQuiz $quiz, CourseQuizQuestion $question): RedirectResponse
    {
        $this->owns($course, $quiz);
        abort_unless($question->course_quiz_id === $quiz->id, 404);
        $data = $this->questionData($request);
        $this->mutate($course, fn () => $question->update($data));

        return back()->with('status', 'Question updated.');
    }

    public function destroyQuestion(Course $course, CourseQuiz $quiz, CourseQuizQuestion $question): RedirectResponse
    {
        $this->owns($course, $quiz);
        abort_unless($question->course_quiz_id === $quiz->id, 404);
        $this->mutate($course, fn () => $question->delete());

        return back()->with('status', 'Draft question deleted.');
    }
}
