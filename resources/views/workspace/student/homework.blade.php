@extends('layouts.workspace')
@section('title', 'My Course Assignments')
@section('heading', 'My Course Assignments')
@section('description', 'Only academically approved coursework from your active, approved course enrollments is listed here.')
@section('content')
<div class="mb-5 flex justify-end"><a href="{{ route('account.homework.submissions') }}" class="rounded-full border border-slate-300 px-5 py-3 text-sm font-bold dark:border-slate-700">My submitted work</a></div>
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
@forelse($assignments as $assignment)
    <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">{{ $assignment->course?->title }}</p>
        <h2 class="mt-3 text-xl font-extrabold">{{ $assignment->title }}</h2>
        <p class="mt-3 flex-1 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($assignment->instructions,160) }}</p>
        <p class="mt-5 text-xs font-semibold {{ $assignment->due_at?->isPast() ? 'text-amber-700 dark:text-amber-400' : 'text-slate-500' }}">{{ $assignment->due_at ? 'Due '.$assignment->due_at->format('Y-m-d H:i') : 'No fixed deadline' }}</p>
        <div class="mt-5 flex justify-end"><a href="{{ route('account.homework.show',$assignment) }}" class="rounded-full bg-blue-700 px-5 py-3 text-sm font-bold text-white">Open assignment</a></div>
    </article>
@empty
    <p class="col-span-full rounded-3xl border border-slate-200 bg-white p-8 text-sm leading-7 text-slate-500 dark:border-slate-800 dark:bg-slate-900">There are no approved assignments in your currently enrolled courses yet.</p>
@endforelse
</div>
<div class="mt-6">{{ $assignments->links() }}</div>
@endsection
