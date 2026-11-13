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

class InstructorHomeworkController extends Controller
{
    private function data(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['required', 'string', 'max:12000'],
            'rubric' => ['nullable', 'string', 'max:3500'],
            'due_at' => ['nullable', 'date', 'after:now'],
        ]);
    }

    public function index(Request $request): View
    {
        $data=$request->validate([
            'status'=>['nullable',Rule::in(['draft','rejected','pending_review','published'])],
            'q'=>['nullable','string','max:80'],
        ]);
        $instructorId = $request->user()->id;
        $search=trim((string)($data['q']??''));

        return view('workspace.instructor.assignments', [
            'role' => 'instructor','filters'=>$data,
            'assignments' => CourseHomeworkAssignment::query()
                ->where('author_id', $instructorId)
                ->when($data['status']??null,fn($q,$status)=>$q->where('status',$status))
                ->when($search!=='',fn($q)=>$q->where('title','like','%'.$search.'%'))
                ->with('course')->orderByDesc('id')->paginate(10)->withQueryString(),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $data = $this->data($request);

        $assignment = DB::transaction(function () use ($request, $course, $data): CourseHomeworkAssignment {
            // Lock course while verifying the currently active teaching role.
            $locked = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            InstructorAccess::check($request->user(), $locked);

            abort_if(CourseHomeworkAssignment::query()->where('course_id', $course->id)
                ->where('author_id', $request->user()->id)->count() >= 100, 422,
                'The course has reached the per-instructor assignment limit.');

            $record = CourseHomeworkAssignment::create($data + [
                'course_id' => $locked->id, 'author_id' => $request->user()->id,
                'status' => 'draft',
            ]);
            ApprovalAudit::record('homework_assignment', $record->id, $request->user(),
                'created', null, 'draft');

            return $record;
        });

        return redirect()->route('instructor.homework.edit', $assignment)
            ->with('status', 'Homework draft created. Publication requires academic review.');
    }

    public function edit(Request $request, CourseHomeworkAssignment $assignment): View
    {
        abort_unless($assignment->author_id === $request->user()->id, 404);
        InstructorAccess::check($request->user(), $assignment->course);

        return view('workspace.instructor.assignment-form', [
            'role' => 'instructor',
            'assignment' => $assignment,
        ]);
    }

    public function update(Request $request, CourseHomeworkAssignment $assignment): RedirectResponse
    {
        $data = $this->data($request);
        DB::transaction(function () use ($request, $assignment, $data): void {
            $record = CourseHomeworkAssignment::query()->whereKey($assignment->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($record->author_id === $request->user()->id, 404);
            InstructorAccess::check($request->user(), $record->course);
            abort_unless($record->isEditable(), 409, 'Submitted work is locked for review.');
            $record->update($data);
        });

        return back()->with('status', 'Assignment draft updated.');
    }

    public function submit(Request $request, CourseHomeworkAssignment $assignment): RedirectResponse
    {
        DB::transaction(function () use ($request, $assignment): void {
            $record = CourseHomeworkAssignment::query()->whereKey($assignment->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($record->author_id === $request->user()->id, 404);
            InstructorAccess::check($request->user(), $record->course);
            abort_unless($record->isEditable(), 409);
            abort_if($record->due_at && $record->due_at->isPast(), 422,
                'Move the due date into the future before submission.');

            $old = $record->status;
            $record->update([
                'status' => 'pending_review',
                'submitted_for_review_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]);
            ApprovalAudit::record('homework_assignment', $record->id, $request->user(),
                'submitted', $old, 'pending_review');
        });

        return redirect()->route('instructor.homework.index')
            ->with('status', 'Assignment submitted for administrator review.');
    }
}
