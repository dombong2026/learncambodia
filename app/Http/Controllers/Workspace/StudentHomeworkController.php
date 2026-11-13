<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\CourseHomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Support\ApprovalAudit;
use App\Support\LearningAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentHomeworkController extends Controller
{
    private const MAX_VERSIONS = 3;

    private function activeEnrollment(Request $request, CourseHomeworkAssignment $assignment): CourseEnrollment
    {
        $enrollment = CourseEnrollment::query()
            ->where('course_id', $assignment->course_id)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        LearningAccess::course($request->user(), $enrollment);

        return $enrollment;
    }

    public function index(Request $request): View
    {
        $id = $request->user()->id;
        $assignments = CourseHomeworkAssignment::published()
            ->whereHas('course', fn ($courses) => $courses->published()
                ->whereHas('enrollments', fn ($enrollments) => $enrollments
                    ->where('student_id', $id)->where('status', 'approved')))
            ->with('course')->orderByDesc('published_at')->paginate(10);

        return view('workspace.student.homework', [
            'role' => 'student','assignments' => $assignments,
        ]);
    }

    public function submissions(Request $request): View
    {
        return view('workspace.student.homework-submissions', [
            'role' => 'student',
            'submissions' => HomeworkSubmission::query()
                ->whereHas('enrollment', fn ($enrollments) => $enrollments
                    ->where('student_id', $request->user()->id))
                ->with('assignment.course')->orderByDesc('id')->paginate(10),
        ]);
    }

    public function show(Request $request, CourseHomeworkAssignment $assignment): View
    {
        abort_unless(CourseHomeworkAssignment::published()->whereKey($assignment->id)->exists(), 404);
        $enrollment = $this->activeEnrollment($request, $assignment);
        $submission = HomeworkSubmission::query()
            ->where('enrollment_id',$enrollment->id)
            ->where('assignment_id',$assignment->id)->first();

        return view('workspace.student.homework-show', [
            'role' => 'student','assignment' => $assignment,
            'submission' => $submission,
        ]);
    }

    public function submit(Request $request, CourseHomeworkAssignment $assignment): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required','string','min:10','max:16000'],
        ]);

        $submission = DB::transaction(function () use ($request, $assignment, $data): HomeworkSubmission {
            $record = CourseHomeworkAssignment::query()->whereKey($assignment->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'published'
                && $record->published_at !== null
                && $record->published_at->lte(now()), 404);
            abort_if($record->due_at && $record->due_at->isPast(), 422,
                'This homework due date has passed.');

            // Serializing on enrollment row protects its unique submitted-work key.
            $enrollment = CourseEnrollment::query()
                ->where('course_id',$record->course_id)
                ->where('student_id',$request->user()->id)
                ->lockForUpdate()->firstOrFail();
            LearningAccess::course($request->user(), $enrollment);

            $prior = HomeworkSubmission::query()->where('assignment_id', $record->id)
                ->where('enrollment_id', $enrollment->id)->lockForUpdate()->first();

            abort_if($prior && $prior->status !== 'returned', 409,
                'Your last submission is awaiting feedback or has already been graded.');
            abort_if($prior && $prior->version >= self::MAX_VERSIONS, 409,
                'The maximum of three submission versions has been reached.');

            $now = now();
            if ($prior) {
                $prior->update([
                    'version' => $prior->version + 1,
                    'body' => $data['body'],
                    'submitted_at' => $now,
                    'status' => 'submitted',
                    'graded_by' => null,
                    'graded_at' => null,
                    'score' => null,
                    'feedback' => null,
                ]);
                $submission = $prior;
            } else {
                $submission = HomeworkSubmission::create([
                    'assignment_id' => $record->id,
                    'enrollment_id' => $enrollment->id,
                    'body' => $data['body'],
                    'version' => 1,
                    'status' => 'submitted',
                    'submitted_at' => $now,
                ]);
            }

            $submission->versions()->create([
                'version' => $submission->version,
                'body' => $data['body'],
                'submitted_at' => $now,
            ]);
            ApprovalAudit::record('homework_submission', $submission->id, $request->user(),
                'submitted', $prior ? 'returned' : null, 'submitted',
                'Student version '.$submission->version);

            return $submission;
        });

        return redirect()->route('account.homework.result', $submission)
            ->with('status', 'Assignment submitted for instructor feedback.');
    }

    public function result(Request $request, HomeworkSubmission $submission): View
    {
        abort_unless($submission->enrollment()->where('student_id', $request->user()->id)
            ->exists(), 404);

        return view('workspace.student.homework-result', [
            'role' => 'student',
            'submission' => $submission->load(['assignment.course', 'versions']),
        ]);
    }
}
