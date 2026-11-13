<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\StudyGoal;
use App\Models\StudySession;
use App\Models\StudyTask;
use App\Support\LearningAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentPlannerController extends Controller
{
    private function enrollment(Request $request, int $id): CourseEnrollment
    {
        $enrollment = CourseEnrollment::query()->where('student_id', $request->user()->id)
            ->whereKey($id)->firstOrFail();
        LearningAccess::course($request->user(), $enrollment);

        return $enrollment;
    }

    private function goal(Request $request, StudyGoal $goal): StudyGoal
    {
        abort_unless($goal->student_id === $request->user()->id, 404);
        $this->enrollment($request, $goal->enrollment_id);

        return $goal;
    }

    public function index(Request $request): View
    {
        $enrollments = CourseEnrollment::query()
            ->where('student_id', $request->user()->id)->where('status', 'approved')
            ->whereHas('course', fn ($query) => $query->published())
            ->with('course')->orderByDesc('id')->get();
        $ids = $enrollments->modelKeys();
        $goals = StudyGoal::query()->where('student_id', $request->user()->id)
            ->whereIn('enrollment_id', $ids)->with('tasks', 'enrollment.course')
            ->latest('id')->paginate(10);
        $sessions = StudySession::query()->where('student_id', $request->user()->id)
            ->whereIn('enrollment_id', $ids)->with('enrollment.course')
            ->orderBy('weekday')->orderBy('start_time')->get();

        $totalTasks = $goals->getCollection()->sum(fn ($goal) => $goal->tasks->count());
        $completedTasks = $goals->getCollection()->sum(fn ($goal) =>
            $goal->tasks->whereNotNull('completed_at')->count());

        return view('workspace.student.planner', compact(
            'enrollments', 'goals', 'sessions', 'totalTasks', 'completedTasks'
        ) + ['role' => 'student']);
    }

    public function storeGoal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enrollment_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $this->enrollment($request, (int) $data['enrollment_id']);
        abort_if(StudyGoal::where('student_id', $request->user()->id)->count() >= 50, 422);
        StudyGoal::create($data + ['student_id' => $request->user()->id]);

        return back()->with('status', 'Study goal saved.');
    }

    public function updateGoal(Request $request, StudyGoal $goal): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'complete' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($request, $goal, $data): void {
            $locked = StudyGoal::query()->whereKey($goal->id)->lockForUpdate()->firstOrFail();
            $this->goal($request, $locked);
            $locked->update([
                'title' => $data['title'],
                'target_date' => $data['target_date'] ?? null,
                'completed_at' => $data['complete'] ? ($locked->completed_at ?? now()) : null,
            ]);
        });

        return back()->with('status', 'Study goal updated.');
    }

    public function destroyGoal(Request $request, StudyGoal $goal): RedirectResponse
    {
        $this->goal($request, $goal)->delete();

        return back()->with('status', 'Study goal deleted.');
    }

    public function storeTask(Request $request, StudyGoal $goal): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $this->goal($request, $goal);
        abort_if($goal->tasks()->count() >= 40, 422);
        $goal->tasks()->create($data);

        return back()->with('status', 'Study task added.');
    }

    public function toggleTask(Request $request, StudyTask $task): RedirectResponse
    {
        $data = $request->validate(['complete' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $task, $data): void {
            $locked = StudyTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $this->goal($request, $locked->goal);
            $locked->update(['completed_at' => $data['complete'] ? ($locked->completed_at ?? now()) : null]);
        });

        return back()->with('status', 'Task progress updated.');
    }

    public function destroyTask(Request $request, StudyTask $task): RedirectResponse
    {
        $this->goal($request, $task->goal);
        $task->delete();

        return back()->with('status', 'Study task deleted.');
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enrollment_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:120'],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'between:15,180'],
        ]);
        $this->enrollment($request, (int) $data['enrollment_id']);
        abort_if(StudySession::where('student_id', $request->user()->id)->count() >= 21, 422);
        StudySession::create($data + ['student_id' => $request->user()->id]);

        return back()->with('status', 'Recurring weekly session saved.');
    }

    public function destroySession(Request $request, StudySession $session): RedirectResponse
    {
        abort_unless($session->student_id === $request->user()->id, 404);
        $this->enrollment($request, $session->enrollment_id);
        $session->delete();

        return back()->with('status', 'Weekly session removed.');
    }
}
