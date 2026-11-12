<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseHomeworkAssignment;
use App\Support\ApprovalAudit;
use App\Support\InstructorAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminHomeworkReviewController extends Controller
{
    public function index(): View
    {
        return view('workspace.admin.homework-reviews', [
            'role' => 'admin',
            'assignments' => CourseHomeworkAssignment::query()
                ->where('status', 'pending_review')
                ->with(['course','author'])
                ->orderBy('submitted_for_review_at')->paginate(10),
        ]);
    }

    public function review(Request $request, CourseHomeworkAssignment $assignment): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve','reject'])],
            'note' => ['nullable','string','max:500','required_if:decision,reject'],
        ]);

        DB::transaction(function () use ($assignment, $data, $request): void {
            $record = CourseHomeworkAssignment::query()
                ->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'pending_review', 409);
            abort_unless($record->author_id !== $request->user()->id, 403);

            if ($data['decision'] === 'approve') {
                InstructorAccess::check($record->author, $record->course);
                abort_unless(Course::published()->whereKey($record->course_id)->exists(), 422);
                abort_if($record->due_at && $record->due_at->isPast(), 422,
                    'Homework is past its due date; request a revised draft.');
            }

            $status = $data['decision'] === 'approve' ? 'published' : 'rejected';
            $record->update([
                'status' => $status,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $data['note'] ?? null,
                'published_at' => $status === 'published' ? now() : null,
            ]);
            ApprovalAudit::record('homework_assignment', $record->id, $request->user(),
                $data['decision'], 'pending_review', $status, $data['note'] ?? null);
        });

        return back()->with('status', 'Homework review decision recorded.');
    }
}
