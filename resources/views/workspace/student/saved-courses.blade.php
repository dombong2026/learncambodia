@extends('layouts.workspace')
@section('title', __('saved_courses.title'))
@section('heading', __('saved_courses.title'))
@section('description', __('saved_courses.description'))
@section('content')
<div class="mb-5 flex justify-end">
    <a href="{{ route('account.courses.index') }}" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">{{ __('saved_courses.browse') }}</a>
</div>
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
@forelse($bookmarks as $bookmark)
    @php($course = $bookmark->course)
    <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">{{ $course->category?->name }} · {{ $course->cefr_level ?? __('saved_courses.all_levels') }}</p>
        <h2 class="mt-3 text-xl font-extrabold">{{ $course->title }}</h2>
        <p class="mt-3 flex-1 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $course->summary }}</p>
        <div class="mt-6 flex flex-wrap justify-end gap-2">
            <form method="POST" action="{{ route('account.saved-courses.destroy', $course) }}">
                @csrf @method('DELETE')
                <button type="submit" aria-label="{{ __('saved_courses.remove_course', ['course' => $course->title]) }}" class="rounded-full border border-slate-300 px-4 py-3 text-sm font-bold text-slate-700 hover:border-red-600 dark:border-slate-600 dark:text-slate-200">{{ __('saved_courses.remove') }}</button>
            </form>
            <a href="{{ route('courses.show', ['course' => $course->slug]) }}" class="rounded-full bg-blue-700 px-5 py-3 text-sm font-bold text-white hover:bg-blue-800">{{ __('saved_courses.view') }}</a>
        </div>
    </article>
@empty
    <div class="col-span-full rounded-3xl border border-slate-200 bg-white p-8 text-sm leading-7 text-slate-500 dark:border-slate-800 dark:bg-slate-900">{{ __('saved_courses.empty') }}</div>
@endforelse
</div>
<div class="mt-7">{{ $bookmarks->links() }}</div>
@endsection
