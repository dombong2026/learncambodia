@extends('layouts.workspace')
@section('title', 'My Learning Progress')
@section('heading', 'My Learning Progress')
@section('description', 'Your own lesson-completion records for approved, currently published courses. Progress is self-reported and does not award a certificate.')
@section('content')
<div class="mb-6 flex justify-end"><a href="{{ route('account.enrollments.index') }}" class="rounded-full bg-blue-700 px-5 py-3 text-sm font-bold text-white">Open my enrollments</a></div>
<div class="space-y-4">
@forelse($enrollments as $enrollment)
    @php
        $total = $enrollment->course?->lessons_count ?? 0;
        $completed = min($total, $enrollment->completed_lessons_count);
        $percent = $total > 0 ? (int) round($completed * 100 / $total) : 0;
    @endphp
    <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Approved enrollment</p>
                <h2 class="mt-2 text-xl font-extrabold">{{ $enrollment->course?->title }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ $completed }} of {{ $total }} lessons marked complete</p></div>
            <a href="{{ route('account.enrollments.show', $enrollment) }}" class="rounded-full border border-blue-400 px-5 py-2.5 text-sm font-bold text-blue-700 dark:text-blue-400">Continue learning</a>
        </div>
        <div class="mt-6 flex items-center gap-4">
            <progress value="{{ $completed }}" max="{{ max(1, $total) }}" class="h-3 w-full flex-1 accent-blue-700" aria-label="Lesson completion progress">{{ $percent }}%</progress>
            <span class="w-14 text-right text-sm font-extrabold tabular-nums">{{ $percent }}%</span>
        </div>
    </section>
@empty
    <div class="rounded-3xl border border-slate-200 bg-white p-8 text-sm leading-7 text-slate-500 dark:border-slate-800 dark:bg-slate-900">No active approved enrollments yet. After approval, your lesson progress will appear here.</div>
@endforelse
</div>
<div class="mt-6">{{ $enrollments->links() }}</div>
@endsection
