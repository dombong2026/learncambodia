<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseBookmark;
use App\Models\User;
use App\Support\ApprovalAudit;
use App\Support\CoursePricing;
use App\Support\LearningTable;
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
            'enrollments' => LearningTable::paginate(
                CourseEnrollment::where('student_id', $request->user()->id)
                    ->whereHas('course', fn ($query) => LearningTable::courses($query, LearningTable::search($request)))
                    ->with(['course' => fn ($query) => $query->with('category')->withCount('modules')])->latest('id'),
                $request
            ),
        ]);
    }

    public function catalog(Request $request): View
    {
        $query = Course::discoverable()->with('category')->withCount('modules')->orderBy('title')->orderBy('id');
        $courses = LearningTable::paginate(LearningTable::courses($query, LearningTable::search($request)), $request);
        $enrollments = CourseEnrollment::where('student_id', $request->user()->id)
            ->whereIn('course_id', $courses->pluck('id'))->get()->keyBy('course_id');

        $savedCourseIds = CourseBookmark::query()
            ->where('student_id', $request->user()->id)
            ->whereIn('course_id', $courses->getCollection()->pluck('id'))
            ->pluck('course_id')->all();

        return view('workspace.student.course-catalog', [
            'role' => 'student',
            'courses' => $courses,
            'savedCourseIds' => $savedCourseIds,
            'enrollments' => $enrollments,
        ]);
    }

    public function show(Request $request, CourseEnrollment $enrollment): View
    {
        abort_unless($enrollment->student_id === $request->user()->id, 404);
        abort_unless($enrollment->status === 'approved', 403);

        $course = Course::published()->whereKey($enrollment->course_id)
            ->with('modules.lessons', 'exams.type', 'quizzes')->firstOrFail();

        if ($request->isMethod('GET')) $enrollment->markStarted();

        return view('workspace.student.study', [
            'role' => 'student',
            'enrollment' => $enrollment,
            'course' => $course,
            'certificates' => \App\Models\IssuedCertificate::where('course_enrollment_id', $enrollment->id)->whereNull('revoked_at')->get(),
            'attempts' => \App\Models\ExamAttempt::where('course_enrollment_id', $enrollment->id)->latest('id')->get()->groupBy('course_exam_id'),
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

            $availableCourse = Course::discoverable()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $quote = CoursePricing::quote($availableCourse->pricing);
            if ($quote['model'] !== 'free') {
                $submitted = $request->validate(['pricing_quote' => ['required', 'string']]);
                abort_unless(hash_equals(json_encode($quote, JSON_THROW_ON_ERROR), $submitted['pricing_quote']),
                    409, 'Course pricing changed. Return to the course page and review the current price.');
            }
            $existing = CourseEnrollment::query()
                ->where('student_id', $student->id)->where('course_id', $course->id)
                ->first();

            abort_if($existing !== null, 409, 'You have already requested enrollment for this course.');

            $enrollment = CourseEnrollment::create([
                'course_id' => $course->id,
                'student_id' => $student->id,
                'status' => 'pending',
                'pricing_quote' => $quote['model'] === 'free' ? null : $quote,
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
