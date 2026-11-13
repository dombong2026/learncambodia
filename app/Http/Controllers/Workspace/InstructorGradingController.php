<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\HomeworkSubmission;
use App\Models\InstructorFeedbackTemplate;
use App\Support\ApprovalAudit;
use App\Support\InstructorAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstructorGradingController extends Controller
{
    private function authorizeSubmission(Request $request, HomeworkSubmission $submission): void
    {
        InstructorAccess::check($request->user(), $submission->assignment->course);
        abort_unless($submission->assignment->status === 'published', 404);
    }

    public function index(Request $request): View
    {
        $filters=$request->validate([
            'status'=>['nullable',Rule::in(['submitted','graded','returned'])],
            'course'=>['nullable','integer'],
        ]);
        $id = $request->user()->id;
        $submissions = HomeworkSubmission::query()
            ->whereHas('assignment.course', fn ($courses) => $courses
                ->whereHas('instructorAssignments', fn ($assignments) => $assignments
                    ->where('instructor_id',$id)->where('status','active')))
            ->whereHas('assignment', fn ($assignments) => $assignments
                ->where('status','published'))
            ->when($filters['status']??null,fn($q,$status)=>$q->where('status',$status))
            ->when($filters['course']??null,fn($q,$course)=>$q->whereHas('assignment',fn($a)=>$a->where('course_id',(int)$course)))
            ->with(['assignment.course','enrollment.student'])
            ->orderByDesc('submitted_at')->paginate(10)->withQueryString();

        return view('workspace.instructor.submissions', [
            'role' => 'instructor','submissions' => $submissions,
            'filters' => $filters,
            'courses' => \App\Models\Course::published()->whereHas('instructorAssignments',
                fn($q)=>$q->where('instructor_id',$id)->where('status','active'))
                ->orderBy('title')->get(['id','title']),
        ]);
    }

    public function show(Request $request, HomeworkSubmission $submission): View
    {
        $this->authorizeSubmission($request, $submission);
        $data=$request->validate(['template'=>['nullable','integer']]);
        $templates=InstructorFeedbackTemplate::query()->where('instructor_id',$request->user()->id)
            ->orderBy('title')->limit(30)->get();
        $selected=isset($data['template']) ? $templates->firstWhere('id',(int)$data['template']) : null;
        if(isset($data['template'])) abort_unless($selected!==null,404);

        return view('workspace.instructor.submission', [
            'role' => 'instructor','templates'=>$templates,'selectedTemplate'=>$selected,
            'submission' => $submission->load(['assignment.course', 'enrollment.student', 'versions']),
        ]);
    }

    public function review(Request $request, HomeworkSubmission $submission): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['grade','return'])],
            'score' => ['nullable','required_if:decision,grade','integer','between:0,100'],
            'feedback' => ['required','string','min:5','max:3000'],
        ]);

        DB::transaction(function () use ($request, $submission, $data): void {
            $record = HomeworkSubmission::query()
                ->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $record->load(['assignment.course','enrollment']);
            $this->authorizeSubmission($request, $record);
            abort_unless($record->status === 'submitted', 409,
                'Only submitted work can receive a new grading decision.');
            abort_unless($record->enrollment->status === 'approved', 409,
                'The student must retain an approved enrollment for grading.');

            $status = $data['decision'] === 'grade' ? 'graded' : 'returned';
            $record->update([
                'status' => $status,
                'score' => $status === 'graded' ? (int) $data['score'] : null,
                'feedback' => $data['feedback'],
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
            ]);

            ApprovalAudit::record('homework_submission', $record->id, $request->user(),
                $data['decision'], 'submitted', $status,
                mb_substr($data['feedback'], 0, 500));
        });

        return redirect()->route('instructor.grading.show', $submission)
            ->with('status', 'Assignment evaluation recorded and visible to the student.');
    }
}
