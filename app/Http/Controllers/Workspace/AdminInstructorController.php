<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseInstructorAssignment;
use App\Models\User;
use App\Support\ApprovalAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminInstructorController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($search) > 80, 422);

        $assignments = CourseInstructorAssignment::query()
            ->with(['course', 'instructor'])
            ->when($search !== '', fn ($query) => $query->whereHas('instructor',
                fn ($users) => $users->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')))
            ->orderByDesc('id')->paginate(10)->withQueryString();

        return view('workspace.admin.instructors', [
            'role' => 'admin', 'assignments' => $assignments,
            'instructors' => User::query()->where('role', User::ROLE_INSTRUCTOR)
                ->orderBy('name')->limit(100)->get(['id','name','email']),
            'courses' => Course::published()->orderBy('title')->limit(100)->get(['id','title','revision_number']),
            'instructorCount' => User::where('role', User::ROLE_INSTRUCTOR)->count(),
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'instructor_id' => ['required','integer','exists:users,id'],
            'course_id' => ['required','integer','exists:courses,id'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $teacher = User::query()->whereKey($data['instructor_id'])
                ->lockForUpdate()->firstOrFail();
            abort_unless($teacher->role === User::ROLE_INSTRUCTOR, 422,
                'Select an operator-approved instructor account.');
            $course = Course::query()->whereKey($data['course_id'])
                ->lockForUpdate()->firstOrFail();
            abort_unless(Course::published()->whereKey($course->id)->exists(), 422,
                'Instructors can only be assigned to academically approved courses.');

            $record = CourseInstructorAssignment::query()
                ->where('instructor_id', $teacher->id)
                ->where('course_id', $course->id)
                ->lockForUpdate()->first();

            abort_if($record?->status === 'active', 409,
                'This instructor is already assigned to this course.');

            $from = $record?->status;
            if ($record) {
                $record->update([
                    'status' => 'active',
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                    'revoked_at' => null,
                ]);
            } else {
                $record = CourseInstructorAssignment::create([
                    'course_id' => $course->id,
                    'instructor_id' => $teacher->id,
                    'assigned_by' => $request->user()->id,
                    'status' => 'active',
                    'assigned_at' => now(),
                ]);
            }

            ApprovalAudit::record('instructor_assignment', $record->id,
                $request->user(), 'assigned', $from, 'active');
        });

        return back()->with('status', 'Instructor assignment approved and recorded.');
    }

    public function revoke(Request $request, CourseInstructorAssignment $assignment): RedirectResponse
    {
        DB::transaction(function () use ($request, $assignment): void {
            $locked = CourseInstructorAssignment::query()
                ->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'active', 409);
            $locked->update(['status' => 'revoked', 'revoked_at' => now()]);
            ApprovalAudit::record('instructor_assignment', $locked->id,
                $request->user(), 'revoked', 'active', 'revoked');
        });

        return back()->with('status', 'Teaching permission revoked immediately.');
    }
}
