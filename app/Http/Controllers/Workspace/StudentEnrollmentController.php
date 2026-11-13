<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Support\ApprovalAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentEnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        return view('workspace.student.enrollments', [
            'role' => 'student',
            'enrollments' => CourseEnrollment::query()
                ->where('student_id', $request->user()->id)
                ->with('course.category')->orderByDesc('id')->paginate(10),
        ]);
    }

    public function catalog(): View
    {
        return view('workspace.student.course-catalog', [
            'role' => 'student',
            'courses' => Course::discoverable()->with('category')
                ->orderBy('title')->paginate(10),
        ]);
    }

    public function show(Request $request, CourseEnrollment $enrollment): View
    {
        abort_unless($enrollment->student_id === $request->user()->id, 404);
        abort_unless($enrollment->status === 'approved', 403);

        $course = Course::published()->whereKey($enrollment->course_id)
            ->with('modules.lessons')->firstOrFail();

        return view('workspace.student.study', [
            'role' => 'student',
            'enrollment' => $enrollment,
            'course' => $course,
            'completedLessonIds' => $enrollment->lessonProgress()
                ->whereNotNull('completed_at')->pluck('lesson_id')->all(),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        DB::transaction(function () use ($request, $course): void {
            // Lock the student row to serialize concurrent enrollment attempts.
            $student = User::query()->whereKey($request->user()->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($student->role === User::ROLE_STUDENT, 403);

            abort_unless(Course::discoverable()->whereKey($course->id)->exists(), 404);
            $existing = CourseEnrollment::query()
                ->where('student_id', $student->id)->where('course_id', $course->id)
                ->first();

            abort_if($existing !== null, 409, 'You have already requested enrollment for this course.');

            $enrollment = CourseEnrollment::create([
                'course_id' => $course->id,
                'student_id' => $student->id,
                'status' => 'pending',
            ]);
            ApprovalAudit::record('enrollment', $enrollment->id,
                $student, 'requested', null, 'pending');
        });

        return redirect()->route('account.enrollments.index')
            ->with('status', 'Enrollment request submitted for administrator review.');
    }

    public function cancel(Request $request, CourseEnrollment $enrollment): RedirectResponse
    {
        DB::transaction(function () use ($request, $enrollment): void {
            $record = CourseEnrollment::query()->whereKey($enrollment->id)
                ->lockForUpdate()->firstOrFail();

            abort_unless($record->student_id === $request->user()->id, 404);
            abort_unless(in_array($record->status, ['pending','approved'], true), 409);

            $from = $record->status;
            $record->update(['status' => 'cancelled']);
            ApprovalAudit::record('enrollment', $record->id, $request->user(),
                'cancelled', $from, 'cancelled');
        });

        return back()->with('status', 'Enrollment request cancelled.');
    }
}
