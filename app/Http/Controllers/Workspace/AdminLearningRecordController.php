<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAccess;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseExam;
use App\Models\CourseInstructorAssignment;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Support\ApprovalAudit;
use App\Support\CoursePricing;
use App\Support\LearningTable;
use App\Support\WorkspaceNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminLearningRecordController extends Controller
{
    public function courseStatus(Request $request, Course $course): RedirectResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);
        $active = $request->boolean('active');

        if ($active && $course->isMutable()) {
            return app(AdminCourseController::class)->submit($request, $course);
        }

        DB::transaction(function () use ($request, $course, $active): void {
            $record = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($record->status !== 'published' || $record->superseded_at !== null
                || ($active && !Course::published()->whereKey($record->id)->exists())) {
                $this->unavailable('Only a current, academically approved course can change availability.');
            }

            if ($active !== ($record->deactivated_at === null)) {
                $record->deactivated_at = $active ? null : now();
                $record->save();
                ApprovalAudit::record('course', $record->id, $request->user(), $active ? 'activated' : 'deactivated',
                    $active ? 'inactive' : 'published', $active ? 'published' : 'inactive');
            }
        });

        return back()->with('status', $active ? 'Course activated.' : 'Course removed from the catalog and closed to new enrollments.');
    }

    public function deleteCourse(Request $request, Course $course): RedirectResponse
    {
        DB::transaction(function () use ($request, $course): void {
            $record = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($record->enrollments()->exists() || $record->instructorAssignments()->exists()
                || $record->source_course_id !== null || Course::withTrashed()->where('source_course_id', $record->id)->exists()
                || AssessmentAccess::whereIn('course_exam_id', $record->exams()->select('id'))->exists()) {
                $this->unavailable('This course has assignments, enrollment history, or linked editions. Deactivate it to close new enrollment.');
            }

            $record->delete();
            ApprovalAudit::record('course', $record->id, $request->user(), 'deleted', $record->status, 'deleted');
        });

        return back()->with('status', 'Course deleted.');
    }

    public function coursePreview(Course $course): View
    {
        return view('workspace.admin.learning-preview', [
            'role' => 'admin',
            'record' => $course->load(['category', 'featuredMedia', 'modules.lessons:id,course_module_id,title,duration_minutes']),
            'kind' => 'course',
        ]);
    }

    public function assessmentStatus(Request $request, CourseExam $exam): RedirectResponse
    {
        $this->checkKind($request, $exam);
        $request->validate(['active' => ['required', 'boolean']]);
        $active = $request->boolean('active');

        DB::transaction(function () use ($request, $exam, $active): void {
            $record = CourseExam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            if ($active && (empty($record->questions) || ($record->course_id !== null && !$record->course()->exists()))) {
                $this->unavailable('Add questions and check the linked course before activating this assessment.');
            }

            if ($active !== $record->published) {
                $from = strtolower($record->managerStatus());
                $record->published = $active;
                $record->deactivated_at = $active ? null : now();
                $record->save();
                ApprovalAudit::record($record->kind, $record->id, $request->user(), $active ? 'activated' : 'deactivated',
                    $from, $active ? 'published' : 'inactive');
            }
        });

        return back()->with('status', ucfirst($exam->kind).($active ? ' activated.' : ' deactivated.'));
    }

    public function deleteAssessment(Request $request, CourseExam $exam): RedirectResponse
    {
        $this->checkKind($request, $exam);

        DB::transaction(function () use ($request, $exam): void {
            $record = CourseExam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            if (ExamAttempt::where('course_exam_id', $record->id)->exists()
                || AssessmentAccess::where('course_exam_id', $record->id)->exists()) {
                $this->unavailable('This assessment has attempts or access history. Deactivate it to prevent new attempts.');
            }

            $record->delete();
            ApprovalAudit::record($record->kind, $record->id, $request->user(), 'deleted', strtolower($record->managerStatus()), 'deleted');
        });

        return back()->with('status', ucfirst($exam->kind).' deleted.');
    }

    public function assessmentPreview(Request $request, CourseExam $exam): View
    {
        $this->checkKind($request, $exam);

        return view('workspace.admin.learning-preview', [
            'role' => 'admin', 'record' => $exam->load('type', 'featuredMedia'), 'kind' => $exam->kind,
        ]);
    }

    public function courseUsers(Request $request, Course $course): View
    {
        return $this->assignmentPage($request, $course, 'course');
    }

    public function assessmentUsers(Request $request, CourseExam $exam): View
    {
        $this->checkKind($request, $exam);

        return $this->assignmentPage($request, $exam, $exam->kind);
    }

    private function assignmentPage(Request $request, Course|CourseExam $record, string $kind): View
    {
        $search = LearningTable::search($request);
        $users = User::query()->select(['id', 'public_id', 'name', 'email', 'role'])
            ->whereIn('role', $kind === 'course' ? [User::ROLE_STUDENT, User::ROLE_INSTRUCTOR] : [User::ROLE_STUDENT])
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $user) => $user->whereLike('name', "%$search%")->orWhereLike('email', "%$search%")
                    ->when(ctype_digit($search) && strlen($search) === 8, fn (Builder $match) => $match->orWhere('public_id', $search))
            ));

        if ($record instanceof CourseExam && $record->course_id !== null) {
            $users->whereIn('id', CourseEnrollment::where('course_id', $record->course_id)
                ->where('status', 'approved')->select('student_id'));
        }

        $users = LearningTable::paginate($users->orderBy('name')->orderBy('id'), $request);
        $userIds = $users->pluck('id');
        $quote = CoursePricing::quote($record->pricing);
        if ($record instanceof Course) {
            $enrollments = CourseEnrollment::where('course_id', $record->id)->whereIn('student_id', $userIds)
                ->get()->keyBy('student_id');
            $assigned = $enrollments->where('status', 'approved')->keys()->merge(
                CourseInstructorAssignment::where('course_id', $record->id)->whereIn('instructor_id', $userIds)
                    ->where('status', 'active')->pluck('instructor_id')
            );
            $required = $users->filter(function (User $user) use ($enrollments, $quote): bool {
                $enrollment = $enrollments->get($user->id);
                $currentQuote = $enrollment?->status === 'pending' && $enrollment->pricing_quote ? $enrollment->pricing_quote : $quote;

                return !$user->isInstructor() && $currentQuote['model'] !== 'free';
            })->pluck('id');
        } else {
            $grants = AssessmentAccess::where('course_exam_id', $record->id)->whereIn('student_id', $userIds)
                ->where('status', 'approved')->get()->filter(fn (AssessmentAccess $grant) => $grant->matches($quote));
            $assigned = $grants->whereNotNull('assigned_at')->pluck('student_id');
            $required = $quote['model'] === 'free' ? collect() : $userIds->diff($grants->pluck('student_id'));
        }

        return view('workspace.admin.learning-assignment', [
            'role' => 'admin', 'record' => $record, 'kind' => $kind, 'users' => $users,
            'available' => $kind !== 'course' || Course::discoverable()->whereKey($record->id)->exists(),
            'assigned' => $assigned, 'requiresReference' => $required,
        ]);
    }

    public function assignCourse(Request $request, Course $course): RedirectResponse
    {
        $user = $this->selectedUser($request);
        if ($user->isInstructor()) {
            app(AdminInstructorController::class)->assignToCourse($course->id, $user->id, $request->user(), currentOnly: true);

            return back()->with('status', 'Teaching assignment recorded.');
        }

        DB::transaction(function () use ($request, $course, $user): void {
            $student = User::whereKey($user->id)->where('role', User::ROLE_STUDENT)->lockForUpdate()->firstOrFail();
            $record = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            if (!Course::discoverable()->whereKey($record->id)->exists()) {
                $this->unavailable('Activate an approved course before assigning learners.');
            }

            $enrollment = CourseEnrollment::firstOrNew(['course_id' => $record->id, 'student_id' => $student->id]);
            if ($enrollment->status === 'approved') {
                return;
            }

            $quote = $enrollment->status === 'pending' && $enrollment->pricing_quote
                ? $enrollment->pricing_quote : CoursePricing::quote($record->pricing);
            $reference = $this->reference($request, $quote);
            $from = $enrollment->status;
            $note = $reference === null ? 'Assigned by an administrator.' : 'Payment or membership verified. Reference: '.$reference;
            $enrollment->fill([
                'status' => 'approved', 'pricing_quote' => $quote['model'] === 'free' ? null : $quote,
                'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $note,
            ])->save();
            ApprovalAudit::record('enrollment', $enrollment->id, $request->user(), 'assigned', $from, 'approved', $note);
            WorkspaceNotifications::send($student->id, 'Course assigned: '.$record->title, 'account.enrollments.show', ['enrollment' => $enrollment]);
        });

        return back()->with('status', 'Course assigned to the learner.');
    }

    public function assignAssessment(Request $request, CourseExam $exam): RedirectResponse
    {
        $this->checkKind($request, $exam);
        $user = $this->selectedUser($request);

        DB::transaction(function () use ($request, $exam, $user): void {
            $record = CourseExam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $student = User::whereKey($user->id)->where('role', User::ROLE_STUDENT)->lockForUpdate()->first();
            if (!$student || ($record->course_id !== null && !CourseEnrollment::where('course_id', $record->course_id)
                ->where('student_id', $student->id)->where('status', 'approved')->exists())) {
                $this->unavailable('Choose a learner with an approved enrollment if this assessment belongs to a course.');
            }

            $access = AssessmentAccess::firstOrNew(['course_exam_id' => $record->id, 'student_id' => $student->id]);
            $quote = CoursePricing::quote($record->pricing);
            if ($access->status === 'approved' && $access->assigned_at && $access->matches($quote)) {
                return;
            }

            $from = $access->status;
            $reference = $access->status === 'approved' && $access->matches($quote)
                ? $access->reference : $this->reference($request, $quote);
            $access->fill([
                'status' => 'approved', 'quote' => $quote, 'reference' => $reference,
                'reviewed_by' => $request->user()->id, 'assigned_at' => now(),
            ])->save();
            ApprovalAudit::record('assessment_access', $access->id, $request->user(), 'assigned', $from, 'approved');
            WorkspaceNotifications::send($student->id, 'Assessment assigned: '.$record->title, 'account.assessments.index');
        });

        return back()->with('status', 'Assessment assigned to the learner.');
    }

    private function selectedUser(Request $request): User
    {
        $data = $request->validate(['user' => ['required', 'digits:8']]);
        $user = User::where('public_id', $data['user'])->whereIn('role', [User::ROLE_STUDENT, User::ROLE_INSTRUCTOR])->first();
        if (!$user) {
            $this->unavailable('Choose an eligible learner or instructor.');
        }

        return $user;
    }

    private function reference(Request $request, array $quote): ?string
    {
        return $quote['model'] === 'free' ? null : $request->validate([
            'reference' => ['required', 'string', 'max:160'],
        ])['reference'];
    }

    private function checkKind(Request $request, CourseExam $exam): void
    {
        abort_unless($exam->kind === ($request->routeIs('admin.quizzes-manager.*') ? 'quiz' : 'exam'), 404);
    }

    private function unavailable(string $message): never
    {
        throw ValidationException::withMessages(['manager' => $message]);
    }
}
