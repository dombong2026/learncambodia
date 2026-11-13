<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonProgress;
use App\Support\LearningAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentProgressController extends Controller
{
    public function index(Request $request): View
    {
        $enrollments = CourseEnrollment::query()
            ->where('student_id', $request->user()->id)
            ->where('status', 'approved')
            ->whereHas('course', fn ($query) => $query->published())
            ->with(['course' => fn ($query) => $query->withCount('lessons')])
            ->withCount(['lessonProgress as completed_lessons_count' =>
                fn ($query) => $query->whereNotNull('completed_at')])
            ->orderByDesc('updated_at')->paginate(10);

        return view('workspace.student.progress', [
            'role' => 'student', 'enrollments' => $enrollments,
        ]);
    }

    public function update(Request $request, CourseEnrollment $enrollment, CourseLesson $lesson): RedirectResponse
    {
        $data = $request->validate([
            'complete' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $enrollment, $lesson, $data): void {
            $locked = CourseEnrollment::query()->whereKey($enrollment->id)
                ->lockForUpdate()->firstOrFail();
            $course = LearningAccess::course($request->user(), $locked);
            LearningAccess::lesson($course, $lesson);

            // Row locking on the enrollment serializes completion updates.
            $record = LessonProgress::query()->firstOrCreate([
                'enrollment_id' => $locked->id,
                'lesson_id' => $lesson->id,
            ]);
            $record->update([
                'completed_at' => (bool) $data['complete']
                    ? ($record->completed_at ?? now()) : null,
            ]);
        });

        return back()->with('status', (bool) $data['complete']
            ? 'Lesson marked complete.' : 'Lesson marked for review.');
    }
}
