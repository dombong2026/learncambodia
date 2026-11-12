@extends('layouts.workspace')
@section('title', 'Available Courses')
@section('heading', 'Available Courses')
@section('description', 'Apply to independently approved courses. Enrollment requires administrator approval; no payment or external test registration occurs.')
@section('content')
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
@forelse($courses as $course)
    <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">{{ $course->category?->name }} · {{ $course->cefr_level ?? 'All levels' }}</p>
        <h2 class="mt-3 text-xl font-extrabold">{{ $course->title }}</h2>
        <p class="mt-3 flex-1 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $course->summary }}</p>
        <p class="mt-3 text-xs font-semibold text-slate-500">Delivery: {{ ucfirst($course->delivery_mode) }}</p>
        <form method="POST" action="{{ route('account.enrollments.store', $course) }}" class="mt-6 flex justify-end">@csrf
            <button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">Request enrollment</button>
        </form>
    </article>
@empty
    <div class="col-span-full rounded-3xl border border-slate-200 bg-white p-8 text-sm leading-7 text-slate-500 dark:border-slate-800 dark:bg-slate-900">No courses have passed academic review yet. You can explore published learning categories while new courses are prepared.</div>
@endforelse
</div>
<div class="mt-7">{{ $courses->links() }}</div>
@endsection
