<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseHomeworkAssignment;
use App\Models\CourseInstructorAssignment;
use App\Support\InstructorAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstructorTeachingController extends Controller
{
    public function index(Request $request): View
    {
        $assignments = CourseInstructorAssignment::query()
            ->where('instructor_id', $request->user()->id)
            ->where('status', 'active')
            ->whereHas('course', fn ($query) => $query->published())
            ->with('course')
            ->orderByDesc('assigned_at')->paginate(10);

        return view('workspace.instructor.courses', [
            'role' => 'instructor', 'assignments' => $assignments,
        ]);
    }

    public function show(Request $request, Course $course): View
    {
        InstructorAccess::check($request->user(), $course);

        return view('workspace.instructor.course', [
            'role' => 'instructor', 'course' => $course,
            'assignments' => CourseHomeworkAssignment::query()
                ->where('course_id', $course->id)
                ->where('author_id', $request->user()->id)
                ->orderByDesc('id')->paginate(10),
        ]);
    }

    public function guidance(): View
    {
        return view('workspace.instructor.guidance', ['role' => 'instructor']);
    }
}
