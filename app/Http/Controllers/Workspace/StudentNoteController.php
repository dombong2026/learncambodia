<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonNote;
use App\Support\LearningAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentNoteController extends Controller
{
    private function enrollment(Request $request, CourseEnrollment $enrollment): void
    {
        LearningAccess::course($request->user(), $enrollment);
    }

    private function owner(Request $request, LessonNote $note): void
    {
        abort_unless($note->student_id === $request->user()->id, 404);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'body' => ['required', 'string', 'max:10000'],
            'is_pinned' => ['required', 'boolean'],
        ]);
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'enrollment' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);
        $id = $request->user()->id;
        $enrollments = CourseEnrollment::query()->where('student_id', $id)
            ->where('status', 'approved')
            ->whereHas('course', fn ($q) => $q->published())
            ->with('course')->orderByDesc('id')->get();
        $ownedIds = $enrollments->modelKeys();
        $selected = $filters['enrollment'] ?? null;
        if ($selected !== null) {
            abort_unless(in_array((int) $selected, $ownedIds, true), 404);
        }
        $search = trim((string) ($filters['q'] ?? ''));
        $notes = LessonNote::query()
            ->where('student_id', $id)->whereIn('enrollment_id', $ownedIds)
            ->when($selected, fn ($q) => $q->where('enrollment_id', (int) $selected))
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('title', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%');
            }))
            ->with('enrollment.course', 'lesson')
            ->orderByDesc('is_pinned')->latest('updated_at')->paginate(10)->withQueryString();

        return view('workspace.student.notes', compact('notes', 'enrollments', 'selected', 'search')
            + ['role' => 'student']);
    }

    public function store(Request $request, CourseEnrollment $enrollment, CourseLesson $lesson): RedirectResponse
    {
        $data = $this->validated($request);
        $course = LearningAccess::course($request->user(), $enrollment);
        LearningAccess::lesson($course, $lesson);

        LessonNote::query()->updateOrCreate(
            ['student_id' => $request->user()->id, 'enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id],
            $data
        );

        return back()->with('status', 'Your private lesson note was saved.');
    }

    public function update(Request $request, LessonNote $note): RedirectResponse
    {
        $this->owner($request, $note);
        $course = LearningAccess::course($request->user(), $note->enrollment);
        LearningAccess::lesson($course, $note->lesson);
        $note->update($this->validated($request));

        return back()->with('status', 'Your lesson note was updated.');
    }

    public function destroy(Request $request, LessonNote $note): RedirectResponse
    {
        // Ownership is enough for deletion even after enrollment cancellation or course withdrawal.
        $this->owner($request, $note);
        $note->delete();

        return redirect()->route('account.notes.index')->with('status', 'Private note deleted.');
    }
}
