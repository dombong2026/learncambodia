<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\CourseResource;
use App\Support\LearningAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class StudentCourseResourceController extends Controller
{
    public function index(Request $request, CourseEnrollment $enrollment): View
    {
        $course = LearningAccess::course($request->user(), $enrollment);

        return view('workspace.student.course-resources', [
            'role' => 'student', 'course' => $course, 'enrollment' => $enrollment,
            'resources' => $course->resources()->with('media')->get(),
        ]);
    }

    public function download(Request $request, CourseEnrollment $enrollment, CourseResource $resource): Response
    {
        $course = LearningAccess::course($request->user(), $enrollment);
        abort_unless($resource->course_id === $course->id, 404);

        return $resource->download($request);
    }
}
