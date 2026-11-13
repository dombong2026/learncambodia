@extends('layouts.workspace')
@section('title', 'My Study Planner')
@section('heading', 'My Study Planner')
@section('description', 'Private study goals, weekly sessions and tasks for your approved courses. Dates and completion are self-reported.')
@section('content')
<div class="grid gap-5 lg:grid-cols-2">
<section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-xl font-extrabold">Create a study goal</h2>
    <form method="POST" action="{{ route('account.planner.goals.store') }}" class="mt-5 space-y-3">@csrf
        <label class="block text-xs font-bold" for="goal-enrollment">Approved course</label>
        <select id="goal-enrollment" name="enrollment_id" required class="w-full rounded-xl border p-3 dark:bg-slate-950">
        @foreach($enrollments as $enrollment)<option value="{{ $enrollment->id }}">{{ $enrollment->course->title }}</option>@endforeach
        </select>
        <label class="block text-xs font-bold" for="goal-title">Goal</label><input id="goal-title" name="title" maxlength="160" required class="w-full rounded-xl border p-3 dark:bg-slate-950" placeholder="Complete the first unit">
        <label class="block text-xs font-bold" for="goal-date">Target date (optional)</label><input id="goal-date" name="target_date" type="date" class="w-full rounded-xl border p-3 dark:bg-slate-950">
        <button @disabled($enrollments->isEmpty()) class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Save goal</button>
    </form>
</section>
<section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-xl font-extrabold">Weekly study schedule</h2>
    <p class="mt-2 text-sm text-slate-500">Sessions repeat each week and do not create notifications or calendar events.</p>
    <form method="POST" action="{{ route('account.planner.sessions.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
        <select name="enrollment_id" required aria-label="Approved course" class="rounded-xl border p-3 dark:bg-slate-950">@foreach($enrollments as $enrollment)<option value="{{ $enrollment->id }}">{{ $enrollment->course->title }}</option>@endforeach</select>
        <input name="title" maxlength="120" required placeholder="Reading practice" aria-label="Session title" class="rounded-xl border p-3 dark:bg-slate-950">
        <select name="weekday" aria-label="Day of week" class="rounded-xl border p-3 dark:bg-slate-950">@foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $index => $day)<option value="{{ $index + 1 }}">{{ $day }}</option>@endforeach</select>
        <input name="start_time" type="time" required value="18:00" aria-label="Start time" class="rounded-xl border p-3 dark:bg-slate-950">
        <input name="duration_minutes" type="number" min="15" max="180" value="30" required aria-label="Minutes" class="rounded-xl border p-3 dark:bg-slate-950">
        <button @disabled($enrollments->isEmpty()) class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Add weekly session</button>
    </form>
</section>
</div>
<section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-xl font-extrabold">Weekly schedule</h2>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
    @forelse($sessions as $session)
        <article class="rounded-2xl bg-slate-50 p-4 text-sm dark:bg-slate-950">
            <p class="font-bold">{{ ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][$session->weekday - 1] }} · {{ $session->start_time }} · {{ $session->duration_minutes }} min</p>
            <p class="mt-2">{{ $session->title }}</p><p class="mt-1 text-xs text-slate-500">{{ $session->enrollment->course->title }}</p>
            <form method="POST" action="{{ route('account.planner.sessions.destroy', $session) }}" class="mt-3">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-600">Remove session</button></form>
        </article>
    @empty<p class="text-sm text-slate-500">No weekly sessions planned.</p>@endforelse
    </div>
</section>
<section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-xl font-extrabold">Goals and personal tasks</h2>
    <p class="mt-2 text-sm text-slate-500">On this page: {{ $completedTasks }} of {{ $totalTasks }} tasks completed.</p>
    @forelse($goals as $goal)
    <article class="mt-5 rounded-2xl border border-slate-200 p-5 dark:border-slate-700">
        <p class="mb-3 text-xs font-bold text-blue-700 dark:text-blue-300">{{ $goal->enrollment->course->title }}</p>
        <form method="POST" action="{{ route('account.planner.goals.update', $goal) }}" class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
            @csrf
            @method('PATCH')
            <input name="title" maxlength="160" required aria-label="Goal title" value="{{ $goal->title }}" class="min-w-0 rounded-xl border p-3 dark:bg-slate-950">
            <input name="target_date" type="date" value="{{ $goal->target_date?->format('Y-m-d') }}" aria-label="Target date" class="rounded-xl border p-3 dark:bg-slate-950">
            <input type="hidden" name="complete" value="{{ $goal->completed_at ? 1 : 0 }}">
            <button class="rounded-xl bg-blue-700 px-4 py-3 text-xs font-bold text-white">Save goal</button>
        </form>
        <form method="POST" action="{{ route('account.planner.goals.update', $goal) }}" class="mt-3">
            @csrf
            @method('PATCH')
            <input type="hidden" name="title" value="{{ $goal->title }}">
            <input type="hidden" name="target_date" value="{{ $goal->target_date?->format('Y-m-d') }}">
            <input type="hidden" name="complete" value="{{ $goal->completed_at ? 0 : 1 }}">
            <button class="rounded-xl border border-blue-500 px-4 py-2 text-xs font-bold text-blue-700 dark:text-blue-300">{{ $goal->completed_at ? 'Reopen goal' : 'Mark goal complete' }}</button>
        </form>
        <ul class="mt-4 space-y-2">
        @foreach($goal->tasks as $task)
            <li class="flex flex-wrap items-center justify-between gap-3 text-sm">
                <span class="{{ $task->completed_at ? 'line-through text-slate-500' : '' }}">{{ $task->title }} @if($task->due_on)<small>· {{ $task->due_on->format('Y-m-d') }}</small>@endif</span>
                <span class="flex gap-3">
                    <form method="POST" action="{{ route('account.planner.tasks.toggle', $task) }}">@csrf @method('PATCH')<input type="hidden" name="complete" value="{{ $task->completed_at ? 0 : 1 }}"><button class="text-xs font-bold text-blue-700 dark:text-blue-400">{{ $task->completed_at ? 'Reopen' : 'Complete' }}</button></form>
                    <form method="POST" action="{{ route('account.planner.tasks.destroy', $task) }}">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-600">Delete</button></form>
                </span>
            </li>
        @endforeach
        </ul>
        <form method="POST" action="{{ route('account.planner.tasks.store', $goal) }}" class="mt-4 flex flex-wrap gap-2">@csrf
            <input name="title" maxlength="160" required placeholder="New task" aria-label="Task title" class="min-w-0 flex-1 rounded-xl border p-2 dark:bg-slate-950">
            <input name="due_on" type="date" aria-label="Due date" class="rounded-xl border p-2 dark:bg-slate-950"><button class="rounded-xl border border-blue-600 px-4 py-2 text-xs font-bold text-blue-700 dark:text-blue-300">Add task</button>
        </form>
        <form method="POST" action="{{ route('account.planner.goals.destroy', $goal) }}" class="mt-4">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-600">Delete goal and tasks</button></form>
    </article>
    @empty<p class="mt-4 text-sm text-slate-500">No goals yet. Start with an approved course above.</p>@endforelse
    <div class="mt-5">{{ $goals->links() }}</div>
</section>
@endsection
