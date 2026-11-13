@extends('layouts.public')
@section('title', $course->title)
@section('meta_description', $course->summary)
@section('content')
<section class="bg-gradient-to-br from-[#0b2d5a] via-[#124077] to-[#0b2d5a] text-white">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <nav aria-label="Breadcrumb" class="mb-6 flex flex-wrap items-center gap-2 text-xs text-[#c6d9ef]">
            <a href="{{ route('courses.index') }}" class="hover:text-white hover:underline">{{ __('frontend.courses_title') }}</a><span>/</span>
            <a href="{{ route('categories.show',$course->category) }}" class="hover:text-white hover:underline">{{ $course->category?->name }}</a><span>/</span>
            <span class="font-semibold text-white" aria-current="page">{{ $course->title }}</span>
        </nav>
        <div class="flex flex-wrap gap-2 text-xs font-bold"><span class="rounded-full bg-[#e9b85a] px-3 py-1 text-[#102d57]">{{ $course->category?->code }}</span>@if($course->cefr_level)<span class="rounded-full border border-white/25 px-3 py-1">CEFR {{ $course->cefr_level }}</span>@endif</div>
        <h1 class="mt-5 max-w-4xl text-4xl font-black tracking-tight sm:text-5xl">{{ $course->title }}</h1>
        <p class="mt-5 max-w-3xl text-base leading-8 text-[#d3e2f1]">{{ $course->summary }}</p>
    </div>
</section>
<div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_300px] lg:px-8">
    <div class="space-y-7">
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-extrabold">{{ __('frontend.about_course') }}</h2>
            <p class="mt-4 whitespace-pre-line text-sm leading-8 text-slate-600 dark:text-slate-400">{{ $course->description }}</p>
        </section>
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-extrabold">{{ __('frontend.course_outline') }}</h2>
            <p class="mt-2 text-xs text-slate-500">{{ __('frontend.curriculum_safe') }}</p>
            <div class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($course->modules as $module)
                    <div class="py-4"><h3 class="font-bold text-[#0c2d5b] dark:text-white">{{ $module->title }}</h3><span class="mt-1 block text-xs text-slate-500">{{ $module->lessons_count }} {{ __('frontend.lessons') }}</span></div>
                @empty
                    <p class="py-6 text-sm text-slate-500">{{ __('frontend.no_outline') }}</p>
                @endforelse
            </div>
        </section>
    </div>
    <aside class="self-start rounded-3xl border border-slate-200 bg-white p-6 shadow-lg dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-black">{{ __('frontend.start_learning') }}</h2>
        <p class="mt-3 text-sm leading-7 text-slate-500">{{ __('frontend.enrollment_note') }}</p>
        @auth
            <a href="{{ route(auth()->user()->homeRouteName()) }}" class="mt-5 block rounded-xl bg-[#0c2d5b] px-5 py-3 text-center text-sm font-bold text-white hover:bg-[#1c4a83]">{{ __('frontend.open_my_workspace') }}</a>
        @else
            <a href="{{ route('register') }}" class="mt-5 block rounded-xl bg-[#0c2d5b] px-5 py-3 text-center text-sm font-bold text-white hover:bg-[#1c4a83]">{{ __('frontend.create_account') }}</a>
            <a href="{{ route('login') }}" class="mt-3 block rounded-xl border border-[#d9b464] px-5 py-3 text-center text-sm font-bold text-[#0c2d5b] hover:bg-amber-50 dark:text-[#f6ca74] dark:hover:bg-slate-800">{{ __('frontend.sign_in') }}</a>
        @endauth
        <p class="mt-6 border-t border-slate-100 pt-4 text-xs leading-6 text-slate-500 dark:border-slate-800">{{ __('frontend.no_certificate_claim') }}</p>
    </aside>
</div>
@endsection
