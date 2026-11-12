@extends('layouts.workspace')
@section('title', 'Homework Academic Reviews')
@section('heading', 'Homework Academic Reviews')
@section('description', 'Independent administrator decisions activate approved instructional homework. Instructor submissions remain private drafts until reviewed.')
@section('content')
<div class="space-y-5">
@forelse($assignments as $assignment)
    <article class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Assignment #{{ $assignment->id }} · Pending review</p>
                <h2 class="mt-2 text-xl font-extrabold">{{ $assignment->title }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ $assignment->course?->title }} · Instructor: {{ $assignment->author?->name }}</p></div>
            <span class="rounded-full bg-amber-50 px-4 py-2 text-xs font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300">{{ $assignment->due_at ? 'Due '.$assignment->due_at->format('Y-m-d H:i') : 'No due date' }}</span>
        </div>
        <h3 class="mt-6 text-sm font-bold">Instructions</h3>
        <p class="mt-2 whitespace-pre-line break-words text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $assignment->instructions }}</p>
        <h3 class="mt-5 text-sm font-bold">Assessment rubric</h3>
        <p class="mt-2 whitespace-pre-line break-words text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $assignment->rubric ?: 'No additional scoring rubric.' }}</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <form method="POST" action="{{ route('admin.homework.review',$assignment) }}" class="flex items-end">@csrf<input type="hidden" name="decision" value="approve"><button type="submit" class="w-full rounded-full bg-emerald-700 px-5 py-3 text-sm font-bold text-white">Approve & publish</button></form>
            <form method="POST" action="{{ route('admin.homework.review',$assignment) }}" class="space-y-2">@csrf<input type="hidden" name="decision" value="reject"><label for="reason-{{ $assignment->id }}" class="block text-xs font-bold">Rejection reason</label><textarea id="reason-{{ $assignment->id }}" name="note" maxlength="500" rows="2" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea><div class="flex justify-end"><button type="submit" class="rounded-full border border-red-300 px-5 py-2.5 text-sm font-bold text-red-700">Reject</button></div></form>
        </div>
    </article>
@empty
    <div class="rounded-3xl border border-slate-200 bg-white p-8 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">No homework currently awaiting academic review.</div>
@endforelse
</div>
<div class="mt-6">{{ $assignments->links() }}</div>
@endsection
