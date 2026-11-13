@extends('layouts.public')
@section('title', __('frontend.courses_title'))
@section('meta_description', __('frontend.courses_description'))
@section('content')
<section class="bg-gradient-to-br from-[#0a244f] via-[#123a6f] to-[#0a244f] text-white">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <p class="text-xs font-black uppercase tracking-[.18em] text-[#f5c56c]">{{ __('frontend.learn_pathways') }}</p>
        <h1 class="mt-4 text-4xl font-black tracking-tight sm:text-5xl">{{ __('frontend.courses_title') }}</h1>
        <p class="mt-4 max-w-2xl text-base leading-8 text-[#cedef0]">{{ __('frontend.courses_description') }}</p>
    </div>
</section>
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <form role="search" method="GET" action="{{ route('courses.index') }}" class="grid items-end gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto]">
        <div><label for="course-search" class="mb-2 block text-xs font-bold">{{ __('frontend.search_courses') }}</label><input id="course-search" type="search" name="q" value="{{ $search }}" maxlength="80" placeholder="{{ __('frontend.search_placeholder') }}" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950"></div>
        <div><label for="category-select" class="mb-2 block text-xs font-bold">{{ __('frontend.category') }}</label><select name="category" id="category-select" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">{{ __('frontend.all_categories') }}</option>@foreach($categories as $item)<option value="{{ $item->slug }}" @selected($category===$item->slug)>{{ $item->name }}</option>@endforeach</select></div>
        <div><label for="level-select" class="mb-2 block text-xs font-bold">{{ __('frontend.cefr') }}</label><select name="level" id="level-select" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">{{ __('frontend.any_level') }}</option>@foreach(['A1','A2','B1','B2','C1','C2'] as $option)<option value="{{ $option }}" @selected($level===$option)>{{ $option }}</option>@endforeach</select></div>
        <button type="submit" class="rounded-xl bg-[#0c2d5b] px-6 py-3 text-sm font-bold text-white hover:bg-[#1b4a84]">{{ __('frontend.apply') }}</button>
    </form>
    <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-black">{{ __('frontend.available_courses') }}</h2>
        <span class="text-sm font-semibold text-slate-500">{{ number_format($courses->total()) }} {{ __('frontend.result_count') }}</span>
    </div>
    <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
    @forelse($courses as $course)
        <x-public-course-card :course="$course" />
    @empty
        <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-sm leading-7 text-slate-500 dark:border-slate-700 dark:bg-slate-900">
            {{ __('frontend.no_courses') }}
            <div class="mt-5"><a href="{{ route('categories.index') }}" class="font-bold text-blue-700 hover:underline dark:text-blue-400">{{ __('frontend.explore_categories') }}</a></div>
        </div>
    @endforelse
    </div>
    <div class="mt-8">{{ $courses->links() }}</div>
    <p class="mt-10 text-xs leading-6 text-slate-500">{{ config('learning.external_assessment_disclaimer') }}</p>
</section>
@endsection
