<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseBookmark;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentSavedCourseController extends Controller
{
    public function index(Request $request): View
    {
        return view('workspace.student.saved-courses', [
            'role' => 'student',
            'bookmarks' => CourseBookmark::query()
                ->where('student_id', $request->user()->id)
                ->whereHas('course', fn (Builder $query) => $query->discoverable())
                ->with('course.category')
                ->latest('id')
                ->paginate(10),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        // Bookmarks never grant course access; only approved, discoverable courses may be saved.
        abort_unless(Course::discoverable()->whereKey($course->id)->exists(), 404);

        CourseBookmark::query()->insertOrIgnore([
            'student_id' => $request->user()->id,
            'course_id' => $course->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('account.saved-courses.index')
            ->with('status', __('saved_courses.saved'));
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        // Idempotent removal scoped to the acting student: never touch another student's list.
        CourseBookmark::query()
            ->where('student_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->delete();

        return redirect()->route('account.saved-courses.index')
            ->with('status', __('saved_courses.removed'));
    }
}
