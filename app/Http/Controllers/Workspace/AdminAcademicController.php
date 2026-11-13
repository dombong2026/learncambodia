<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\AcademicEvidenceReview;
use App\Models\CourseEnrollment;
use App\Models\HomeworkSubmission;
use App\Support\AcademicEvidence;
use App\Support\ApprovalAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAcademicController extends Controller
{
    public function gradebook(): View
    {
        return view('workspace.admin.gradebook', [
            'role' => 'admin',
            'submissions' => HomeworkSubmission::query()
                ->with(['assignment.course','enrollment.student'])
                ->orderByDesc('submitted_at')->paginate(10),
        ]);
    }

    public function evidence(): View
    {
        return view('workspace.admin.academic-evidence', [
            'role' => 'admin',
            'enrollments' => CourseEnrollment::query()
                ->where('status','approved')
                ->whereHas('course',fn($courses)=>$courses->published())
                ->with(['student:id,name','course'=>fn($query)=>$query
                    ->select('id','title','revision_number')->withCount('lessons')])
                ->withCount([
                    'lessonProgress as completed_lessons_count' =>
                        fn($query)=>$query->whereNotNull('completed_at'),
                    'attempts as passed_practice_count' =>
                        fn($query)=>$query->where('passed',true),
                    'homeworkSubmissions as graded_homework_count' =>
                        fn($query)=>$query->where('status','graded'),
                ])
                ->orderByDesc('id')->paginate(10),
            'reviews' => AcademicEvidenceReview::query()
                ->with(['enrollment.course','enrollment.student'])
                ->orderByDesc('id')->paginate(10,['*'],'review_page'),
        ]);
    }

    public function request(Request $request, CourseEnrollment $enrollment): RedirectResponse
    {
        DB::transaction(function() use ($request,$enrollment): void {
            $locked = CourseEnrollment::query()->whereKey($enrollment->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'approved',422,
                'Only approved course enrollments can have academic evidence reviewed.');
            $snapshot = AcademicEvidence::summary($locked);

            $review = AcademicEvidenceReview::query()
                ->where('enrollment_id',$locked->id)->lockForUpdate()->first();
            abort_if($review && $review->status !== 'needs_revision',409,
                'Academic evidence has already been submitted for this enrollment.');

            $from = $review?->status;
            if ($review) {
                $review->update([
                    'status'=>'pending_review','evidence_snapshot'=>$snapshot,
                    'requested_by'=>$request->user()->id,'reviewed_by'=>null,
                    'reviewed_at'=>null,'review_note'=>null,
                ]);
            } else {
                $review = AcademicEvidenceReview::create([
                    'enrollment_id'=>$locked->id,
                    'requested_by'=>$request->user()->id,
                    'evidence_snapshot'=>$snapshot,
                    'status'=>'pending_review',
                ]);
            }

            ApprovalAudit::record('academic_evidence',$review->id,
                $request->user(),'requested',$from,'pending_review',
                'Internal evidence only. No certificate issued.');
        });

        return back()->with('status','Academic evidence sent for independent review; no certificate is issued.');
    }

    public function review(Request $request, AcademicEvidenceReview $review): RedirectResponse
    {
        $data = $request->validate([
            'decision'=>['required',Rule::in(['reviewed','needs_revision'])],
            'note'=>['required','string','min:10','max:500'],
        ]);

        DB::transaction(function() use ($request,$review,$data): void {
            $record = AcademicEvidenceReview::query()->whereKey($review->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'pending_review',409);
            abort_unless($record->requested_by !== $request->user()->id,403,
                'The requesting administrator cannot review their own evidence request.');

            $enrollment = CourseEnrollment::query()
                ->whereKey($record->enrollment_id)->lockForUpdate()->firstOrFail();
            abort_unless($enrollment->status === 'approved',409);
            if ($data['decision'] === 'reviewed') {
                $fresh = AcademicEvidence::summary($enrollment);
                abort_unless(AcademicEvidence::matchesSnapshot($fresh, $record->evidence_snapshot),409,
                    'Student evidence changed. Request a new academic review snapshot.');
            }
            $record->update([
                'status'=>$data['decision'],
                'reviewed_by'=>$request->user()->id,
                'reviewed_at'=>now(),'review_note'=>$data['note'],
            ]);
            ApprovalAudit::record('academic_evidence',$record->id,
                $request->user(),$data['decision'],'pending_review',
                $data['decision'],$data['note']);
        });

        return back()->with('status',
            'Academic evidence review recorded. Certificate issuance remains disabled.');
    }
}
