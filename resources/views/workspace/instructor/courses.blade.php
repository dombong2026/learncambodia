@extends('layouts.workspace')
@section('title', 'My Assigned Courses')
@section('heading', 'My Assigned Courses')
@section('description', 'Only courses explicitly assigned to you by a school administrator are accessible in this teaching workspace.')
@section('content')
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
@forelse($assignments as $assignment)
    <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Approved teaching assignment</p>
        <h2 class="mt-3 text-xl font-extrabold">{{ $assignment->course?->title }}</h2>
        <p class="mt-2 text-xs text-slate-500">Edition {{ $assignment->course?->revision_number }} · Assigned {{ $assignment->assigned_at?->format('Y-m-d') }}</p>
        <p class="mt-4 flex-1 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $assignment->course?->summary }}</p>
        <div class="mt-6 flex justify-end"><a href="{{ route('instructor.courses.show',$assignment->course) }}" class="rounded-full bg-blue-700 px-5 py-3 text-sm font-bold text-white">Open teaching workspace</a></div>
    </article>
@empty
    <p class="col-span-full rounded-3xl border border-slate-200 bg-white p-8 text-sm leading-7 text-slate-500 dark:border-slate-800 dark:bg-slate-900">No active teaching assignments are available yet. An administrator must assign you to an academically approved course.</p>
@endforelse
</div>
<div class="mt-7">{{ $assignments->links() }}</div>
@endsection
