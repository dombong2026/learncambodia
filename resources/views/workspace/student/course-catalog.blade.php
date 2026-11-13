@extends('layouts.workspace')
@section('title', 'Available Courses')
@section('heading', 'Available Courses')
@section('description', 'Apply to independently approved courses. Enrollment requires administrator approval; no payment or external test registration occurs.')
@section('content')
<div class="mb-5 flex justify-end"><a href="{{ route('account.saved-courses.index') }}" class="rounded-full border border-blue-700 px-5 py-2.5 text-sm font-bold text-blue-700 dark:border-blue-400 dark:text-blue-300">{{ __('saved_courses.title') }}</a></div>
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
@forelse($courses as $course)
    <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">{{ $course->category?->name }} · {{ $course->cefr_level ?? 'All levels' }}</p>
        <h2 class="mt-3 text-xl font-extrabold">{{ $course->title }}</h2>
        <p class="mt-3 flex-1 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $course->summary }}</p>
        <p class="mt-3 text-xs font-semibold text-slate-500">Delivery: {{ ucfirst($course->delivery_mode) }}</p>
        <div class="mt-6 flex flex-wrap justify-end gap-2">
            @if(in_array($course->id, $savedCourseIds, true))
                <form method="POST" action="{{ route('account.saved-courses.destroy', $course) }}">
                    @csrf @method('DELETE')
                    <button type="submit" aria-label="{{ __('saved_courses.remove_course', ['course' => $course->title]) }}" class="rounded-full border border-slate-300 px-4 py-3 text-sm font-bold text-slate-700 hover:border-blue-700 dark:border-slate-600 dark:text-slate-200">{{ __('saved_courses.remove') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('account.saved-courses.store', $course) }}">
                    @csrf
                    <button type="submit" aria-label="{{ __('saved_courses.save_course', ['course' => $course->title]) }}" class="rounded-full border border-slate-300 px-4 py-3 text-sm font-bold text-slate-700 hover:border-blue-700 dark:border-slate-600 dark:text-slate-200">{{ __('saved_courses.save') }}</button>
                </form>
            @endif
            <form method="POST" action="{{ route('account.enrollments.store', $course) }}">@csrf
                <button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">Request enrollment</button>
            </form>
        </div>
    </article>
@empty
    <div class="col-span-full rounded-3xl border border-slate-200 bg-white p-8 text-sm leading-7 text-slate-500 dark:border-slate-800 dark:bg-slate-900">No courses have passed academic review yet. You can explore published learning categories while new courses are prepared.</div>
@endforelse
</div>
<div class="mt-7">{{ $courses->links() }}</div>
@endsection
