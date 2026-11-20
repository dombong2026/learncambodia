<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\AppearanceRevision;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseReviewSnapshot;
use App\Models\User;
use App\Support\ApprovalAudit;
use App\Support\CourseReadiness;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AdminReviewController extends Controller
{
    private const COURSE_PAGE_SIZE = 10;

    public function index(Request $request): View
    {
        $focus = $request->validate([
            'focus' => ['nullable', Rule::in(['courses','appearance','enrollments'])],
        ])['focus'] ?? 'courses';

        return view('workspace.admin.reviews', [
            'role' => 'admin', 'focus' => $focus,
            'courses' => Course::where('status', 'pending_review')
                ->with('category', 'creator:id,public_id')->orderBy('submitted_at')->orderBy('id')
                ->paginate(self::COURSE_PAGE_SIZE, ['*'], 'courses_page'),
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
        $this->recordCourseReview($course, $request->user(), $data['decision'], $data['note'] ?? null);

        return back()->with('status', 'Course review decision recorded.');
    }

    public function approveCourses(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'courses' => ['required', 'array', 'list', 'min:1', 'max:'.self::COURSE_PAGE_SIZE],
            'courses.*' => ['required', 'integer', 'distinct', 'between:100000,999999'],
        ])['courses'];

        $courses = Course::whereIn('public_id', $ids)->get()->keyBy('public_id');
        $published = 0;
        $skipped = [];

        foreach ($ids as $id) {
            $course = $courses->get($id);
            $label = $course?->title ?? 'Course #'.$id;

            if ($course === null) {
                $skipped[] = ['course' => $label, 'reason' => 'The course is unavailable.'];
                continue;
            }

            try {
                $this->recordCourseReview($course, $request->user(), 'approve');
                $published++;
            } catch (ModelNotFoundException) {
                $skipped[] = ['course' => $label, 'reason' => 'The course or its source edition is unavailable.'];
            } catch (HttpExceptionInterface $exception) {
                if (!in_array($exception->getStatusCode(), [409, 422], true)) {
                    throw $exception;
                }
                $skipped[] = ['course' => $label, 'reason' => $exception->getMessage()];
            }
        }

        return redirect()->route('admin.reviews.index', [
            'focus' => 'courses', 'lang' => app()->getLocale(),
        ])->with('course_review_batch', ['published' => $published, 'skipped' => $skipped]);
    }

    private function recordCourseReview(Course $course, User $actor, string $decision, ?string $note = null): void
    {
        DB::transaction(function () use ($course, $actor, $decision, $note): void {
            $locked = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending_review', 409, 'This course is no longer awaiting review.');
            $submission=CourseReviewSnapshot::where('course_id',$locked->id)->latest('id')->first();
            if ($decision === 'approve') {
                abort_unless(CourseReadiness::ready($locked),422,
                    'Academic readiness checklist failed.');
                // Legacy pending records without a snapshot retain the existing review
                // mechanism. New submissions require an unchanged content fingerprint.
                if ($submission !== null) {
                    abort_unless(hash_equals($submission->fingerprint,CourseReadiness::fingerprint($locked)),
                        409,'Submitted academic content changed; reject and resubmit.');
                }
            }

            // A replacement must not supersede an edition that was already withdrawn.
            $source = null;
            if ($decision === 'approve' && $locked->source_course_id) {
                $source = Course::whereKey($locked->source_course_id)
                    ->lockForUpdate()->firstOrFail();
                abort_unless($source->status === 'published'
                    && $source->superseded_at === null && $source->deactivated_at === null, 409,
                    'The source edition is no longer current.');
            }

            $status = $decision === 'approve' ? 'published' : 'rejected';
            $locked->update([
                'status' => $status,
                'published_at' => $status === 'published' ? now() : null,
                'reviewed_at' => now(),
                'reviewed_by' => $actor->id,
                'review_note' => $note,
            ]);
            if ($submission !== null) {
                $submission->update([
                    'decision'=>$decision,'reviewed_by'=>$actor->id,
                    'reviewed_at'=>now(),
                ]);
            }
            ApprovalAudit::record('course', $locked->id, $actor,
                $decision, 'pending_review', $status, $note);
            if ($source !== null) {
                $source->update(['superseded_at' => now()]);
                ApprovalAudit::record('course', $source->id, $actor,
                    'superseded', 'published', 'published',
                    'Replaced by approved edition '.$locked->id);
            }
        }, 3);
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
        $evidence = $request->validate(['pricing_reference' => ['nullable', 'string', 'max:160']]);

        DB::transaction(function () use ($request, $enrollment, $data, $evidence): void {
            $locked = CourseEnrollment::query()->whereKey($enrollment->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409);

            if ($data['decision'] === 'approve') {
                abort_unless(Course::discoverable()->whereKey($locked->course_id)->exists(), 422,
                    'Only published courses may accept enrollments.');
                abort_unless(User::query()->whereKey($locked->student_id)
                    ->where('role', User::ROLE_STUDENT)->exists(), 422);
            }

            $note = $data['note'] ?? null;
            if ($data['decision'] === 'approve' && filled($locked->pricing_quote)) {
                $reference = validator($evidence, ['pricing_reference' => ['required', 'string', 'max:160']])
                    ->validate()['pricing_reference'];
                $note = 'Payment or membership verified. Reference: '.$reference;
            }

            $status = $data['decision'] === 'approve' ? 'approved' : 'declined';
            $locked->update([
                'status' => $status,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
            ApprovalAudit::record('enrollment', $locked->id, $request->user(),
                $data['decision'], 'pending', $status, $note);
        });

        return back()->with('status', 'Enrollment review decision recorded.');
    }
}
