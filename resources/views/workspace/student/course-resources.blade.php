@extends('layouts.workspace')
@section('title', __('focused_learning.course_resources').' · '.$course->title)
@section('heading', $course->title)
@section('content')
    <nav aria-label="{{ __('focused_learning.curriculum') }}" class="mb-4 flex flex-wrap gap-3">
        <a class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold dark:border-slate-700"
            href="{{ route('account.enrollments.show', [$enrollment, 'view' => 'curriculum'] + request()->only('lang')) }}">{{ __('focused_learning.curriculum') }}</a>
        <span class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white" aria-current="page">{{ __('focused_learning.resources') }}</span>
    </nav>
    <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900" aria-labelledby="course-resource-heading">
        <h2 id="course-resource-heading" class="mb-3 text-xl font-bold">{{ __('focused_learning.course_resources') }}</h2>
        @if($resources->isEmpty())
            <p class="text-sm">{{ __('focused_learning.no_course_resources') }}</p>
        @else
            <x-course-resource-list :resources="$resources" :enrollment="$enrollment" />
        @endif
    </section>
@endsection
