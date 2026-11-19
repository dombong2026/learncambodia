<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseBookmark;
use App\Models\User;
use App\Support\AccountPreview;
use App\Support\ApprovalAudit;
use App\Support\CoursePricing;
use App\Support\LearningAccess;
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

    public function show(Request $request, CourseEnrollment $enrollment): View|RedirectResponse
    {
        $course = LearningAccess::course($request->user(), $enrollment)->load('modules.lessons');
        $lessons = $course->modules->flatMap(fn ($module) => $module->lessons);
        $completedLessonIds = $enrollment->lessonProgress()->whereNotNull('completed_at')
            ->whereIn('lesson_id', $lessons->pluck('id'))->pluck('lesson_id')->all();
        $nextLesson = $lessons->first(fn ($lesson) => !in_array($lesson->id, $completedLessonIds, true))
            ?? $lessons->first();
        $savedLesson = $lessons->firstWhere('id', $enrollment->last_opened_lesson_id);
        if ($savedLesson && in_array($savedLesson->id, $completedLessonIds, true)) {
            $savedLesson = null;
        }
        $resumeLesson = $savedLesson ?? $nextLesson;
        $preview = $request->session()->has(AccountPreview::KEY);
        $locale = $request->query('lang') === app()->getLocale() ? ['lang' => app()->getLocale()] : [];

        if ($resumeLesson && !$preview && $request->query('view') !== 'curriculum') {
            return to_route('account.lessons.watch', [$enrollment, $resumeLesson] + $locale);
        }

        if (!$resumeLesson && !$preview && $request->isMethod('GET')) {
            $enrollment->markStarted();
        }

        $course->load('exams.type', 'quizzes');
        $certificates = \App\Models\IssuedCertificate::where('course_enrollment_id', $enrollment->id)->get();

        return view('workspace.student.study', [
            'role' => 'student',
            'enrollment' => $enrollment,
            'course' => $course,
            'certificates' => $certificates->whereNull('revoked_at'),
            'canClaimCertificate' => !$preview && $certificates->isEmpty() && $lessons->count() === count($completedLessonIds)
                && \App\Support\CertificateAwards::courseEligible($course, $enrollment),
            'attempts' => \App\Models\ExamAttempt::where('course_enrollment_id', $enrollment->id)->latest('id')->get()->groupBy('course_exam_id'),
            'completedLessonIds' => $completedLessonIds,
            'resumeLesson' => $resumeLesson,
            'savedLesson' => $savedLesson,
            'nextLesson' => $nextLesson,
            'preview' => $preview,
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $enrollment = DB::transaction(function () use ($request, $course): CourseEnrollment {
            // Lock the student row to serialize concurrent enrollment attempts.
            $student = User::query()->whereKey($request->user()->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($student->role === User::ROLE_STUDENT, 403);

            $availableCourse = Course::discoverable()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $quote = CoursePricing::quote($availableCourse->pricing);
            $free = $quote['model'] === 'free';
            if (! $free) {
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
                'status' => $free ? 'approved' : 'pending',
                'pricing_quote' => $free ? null : $quote,
            ]);
            ApprovalAudit::record('enrollment', $enrollment->id,
                $student, $free ? 'auto_approved' : 'requested', null, $enrollment->status);

            return $enrollment;
        });

        if ($enrollment->status === 'approved') {
            return redirect()->route('account.enrollments.show', $enrollment);
        }

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
