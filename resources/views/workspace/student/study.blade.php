@extends('layouts.workspace')
@section('title', $course->title)
@section('heading', $course->title)
@section('description', 'Your approved course curriculum. Lesson materials are for your own learning account.')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('account.enrollments.index') }}" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">← My Enrollments</a>
    <span class="rounded-full bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Enrollment approved</span>
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
                </article>
            @endforeach
        </div>
    </section>
@endforeach
</div>
@endsection
