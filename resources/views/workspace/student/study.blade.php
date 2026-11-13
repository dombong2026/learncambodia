@extends('layouts.workspace')
@section('title', $course->title)
@section('heading', $course->title)
@section('description', 'Your approved course curriculum. Lesson materials are for your own learning account.')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('account.enrollments.index') }}" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">← My Enrollments</a>
    <span class="rounded-full bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Enrollment approved</span>
</div>
@php $totalLessons = $course->modules->sum(fn ($module) => $module->lessons->count()); @endphp
<div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
    <div><p class="text-sm font-extrabold">My lesson progress</p><p class="mt-1 text-xs text-slate-500">{{ count($completedLessonIds) }} of {{ $totalLessons }} lessons complete (self-reported)</p></div>
    <a href="{{ route('account.progress.index') }}" class="text-sm font-bold text-blue-700 dark:text-blue-400">View progress →</a>
</div>
<p class="max-w-4xl text-sm leading-8 text-slate-600 dark:text-slate-400">{{ $course->description }}</p>
<div class="mt-7 space-y-6">
@foreach($course->modules as $module)
    <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-extrabold">Module {{ $module->position }}: {{ $module->title }}</h2>
        @if($module->summary)<p class="mt-2 text-sm leading-7 text-slate-500">{{ $module->summary }}</p>@endif
        <div class="mt-6 space-y-5">
            @foreach($module->lessons as $lesson)
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-950">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-lg font-bold">{{ $lesson->position }}. {{ $lesson->title }}</h3>
                        <span class="text-xs text-slate-500">{{ $lesson->duration_minutes }} min</span>
                    </div>
                    <p class="mt-4 whitespace-pre-line break-words text-sm leading-8 text-slate-700 dark:text-slate-300">{{ $lesson->content }}</p>
                    <a href="{{ route('account.lessons.watch',[$enrollment,$lesson]) }}" class="mt-4 inline-flex rounded-xl border border-[#d4a65b] px-4 py-2.5 text-xs font-bold text-[#a57526] dark:text-[#f3c773]">Open focused lesson view →</a>
                    @php $done = in_array($lesson->id, $completedLessonIds, true); @endphp
                    <form method="POST" action="{{ route('account.progress.update',[$enrollment,$lesson]) }}" class="mt-5 flex justify-end">@csrf
                        <input type="hidden" name="complete" value="{{ $done ? '0' : '1' }}">
                        <button type="submit" class="rounded-full px-5 py-2.5 text-xs font-extrabold {{ $done ? 'border border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-blue-700 text-white hover:bg-blue-800' }}">{{ $done ? '✓ Completed · Mark for review' : 'Mark lesson complete' }}</button>
                    </form>
                </article>
            @endforeach
        </div>
    </section>
@endforeach
@if($course->quizzes->isNotEmpty())
<section class="mt-8 rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-xl font-extrabold">Practice quizzes</h2>
    <p class="mt-2 text-sm text-slate-500">Optional in-platform learning checks, not official examinations.</p>
    <div class="mt-5 flex flex-wrap gap-3">
    @foreach($course->quizzes as $quiz)
        <a href="{{ route('account.practice.show',[$enrollment,$quiz]) }}" class="rounded-full border border-blue-300 px-5 py-3 text-sm font-bold text-blue-700 dark:border-blue-900 dark:text-blue-400">{{ $quiz->title }}</a>
    @endforeach
    </div>
</section>
@endif
</div>
@endsection
