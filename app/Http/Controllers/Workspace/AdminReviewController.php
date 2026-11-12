<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\AppearanceRevision;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Support\ApprovalAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminReviewController extends Controller
{
    public function index(Request $request): View
    {
        $focus = $request->validate([
            'focus' => ['nullable', Rule::in(['courses','appearance','enrollments'])],
        ])['focus'] ?? 'courses';

        return view('workspace.admin.reviews', [
            'role' => 'admin', 'focus' => $focus,
            'courses' => Course::where('status', 'pending_review')
                ->with('category')->orderBy('submitted_at')
                ->paginate(10, ['*'], 'courses_page'),
            'appearances' => AppearanceRevision::where('status', 'pending_review')
                ->with('creator')->orderBy('submitted_at')
                ->paginate(10, ['*'], 'appearance_page'),
            'enrollments' => CourseEnrollment::where('status', 'pending')
                ->with(['course', 'student'])->orderBy('created_at')
                ->paginate(10, ['*'], 'enrollment_page'),
        ]);
    }

    private function reviewData(Request $request, string $negative): array
    {
        return $request->validate([
            'decision' => ['required', Rule::in(['approve', $negative])],
            'note' => ['nullable', 'string', 'max:500', 'required_if:decision,'.$negative],
        ]);
    }

    public function course(Request $request, Course $course): RedirectResponse
    {
        $data = $this->reviewData($request, 'reject');

        DB::transaction(function () use ($request, $course, $data): void {
            $locked = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending_review', 409);
            // A second administrator must approve the author's curriculum.
            abort_unless($locked->created_by && $locked->created_by !== $request->user()->id,
                403, 'A separate administrator must review the submitted course.');

            if ($data['decision'] === 'approve') {
                abort_unless($locked->category()->where('is_published', true)->exists(), 422);
                $modules = $locked->modules()->withCount('lessons')->get();
                abort_unless($modules->isNotEmpty()
                    && $modules->every(fn ($module) => $module->lessons_count > 0)
                    && filled($locked->description), 422);
            }

            $status = $data['decision'] === 'approve' ? 'published' : 'rejected';
            $locked->update([
                'status' => $status,
                'published_at' => $status === 'published' ? now() : null,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'review_note' => $data['note'] ?? null,
            ]);
            ApprovalAudit::record('course', $locked->id, $request->user(),
                $data['decision'], 'pending_review', $status, $data['note'] ?? null);
        });

        return back()->with('status', 'Course review decision recorded.');
    }

    public function appearance(Request $request, AppearanceRevision $revision): RedirectResponse
    {
        $data = $this->reviewData($request, 'reject');

        DB::transaction(function () use ($request, $revision, $data): void {
            $locked = AppearanceRevision::whereKey($revision->id)
                ->lockForUpdate()->firstOrFail();

            abort_unless($locked->status === 'pending_review', 409);
            abort_unless($locked->created_by !== $request->user()->id, 403,
                'A separate administrator must review appearance changes.');

            $status = $data['decision'] === 'approve' ? 'approved' : 'rejected';
            if ($status === 'approved') {
                AppearanceRevision::query()->approved()
                    ->where('section', $locked->section)->whereKeyNot($locked->id)
                    ->update(['status' => 'superseded']);
            }

            $locked->update([
                'status' => $status,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $data['note'] ?? null,
            ]);
            ApprovalAudit::record('appearance', $locked->id, $request->user(),
                $data['decision'], 'pending_review', $status, $data['note'] ?? null);
        });

        return back()->with('status', 'Appearance review decision recorded.');
    }

    public function enrollment(Request $request, CourseEnrollment $enrollment): RedirectResponse
    {
        $data = $this->reviewData($request, 'decline');

        DB::transaction(function () use ($request, $enrollment, $data): void {
            $locked = CourseEnrollment::query()->whereKey($enrollment->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409);

            if ($data['decision'] === 'approve') {
                abort_unless(Course::published()->whereKey($locked->course_id)->exists(), 422,
                    'Only published courses may accept enrollments.');
                abort_unless(User::query()->whereKey($locked->student_id)
                    ->where('role', User::ROLE_STUDENT)->exists(), 422);
            }

            $status = $data['decision'] === 'approve' ? 'approved' : 'declined';
            $locked->update([
                'status' => $status,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $data['note'] ?? null,
            ]);
            ApprovalAudit::record('enrollment', $locked->id, $request->user(),
                $data['decision'], 'pending', $status, $data['note'] ?? null);
        });

        return back()->with('status', 'Enrollment review decision recorded.');
    }
}
