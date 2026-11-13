@extends('layouts.public')
@section('title', $profile->display_name)
@if($kind==='student')@section('robots','noindex,nofollow')@endif
@section('content')
@php $teaching=$kind==='instructor'; @endphp
<section class="bg-gradient-to-br from-[#0a284f] via-[#174675] to-[#0c2b56] text-white">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <nav aria-label="Breadcrumb" class="mb-8 flex flex-wrap gap-2 text-xs text-[#c7d7ec]">
            <a href="{{ route('home') }}" class="hover:text-white hover:underline">{{ __('people.home') }}</a><span>/</span>
            <a href="{{ $teaching ? route('instructors.index') : route('students.index') }}" class="hover:text-white hover:underline">{{ $teaching ? __('people.instructors_title') : __('people.students_title') }}</a><span>/</span>
            <span aria-current="page" class="font-bold text-white">{{ $profile->display_name }}</span>
        </nav>
        <div class="flex flex-col items-start gap-6 sm:flex-row sm:items-center">
            <div class="grid h-28 w-28 shrink-0 place-items-center rounded-full border-[5px] border-[#f2c169] bg-[#315d8e] text-5xl font-black text-white shadow-xl" aria-hidden="true">{{ mb_strtoupper(mb_substr($profile->display_name,0,1)) }}</div>
            <div>
                <p class="text-xs font-extrabold uppercase tracking-[.12em] text-[#f5c979]">{{ $teaching ? __('people.instructor') : __('people.student') }} · {{ __('people.publicly_approved') }}</p>
                <h1 class="mt-2 break-words text-4xl font-black leading-tight">{{ $profile->display_name }}</h1>
                <p class="mt-3 max-w-3xl text-base leading-8 text-[#d7e5f4]">{{ $profile->headline }}</p>
                @if($profile->subjects)<p class="mt-3 text-sm font-semibold text-[#ffe09f]">{{ $profile->subjects }}</p>@endif
            </div>
        </div>
    </div>
</section>
<div class="mx-auto grid max-w-7xl gap-7 px-4 py-12 sm:px-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(270px,.7fr)] lg:px-8">
    <div class="space-y-7">
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-700 dark:bg-[#142d4f]">
            <h2 class="text-xl font-black text-[#0b2d59] dark:text-white">{{ __('people.about') }}</h2>
            <p class="mt-4 whitespace-pre-line break-words text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $profile->bio ?: __('people.no_bio') }}</p>
            @if($profile->city)<p class="mt-4 text-xs font-semibold text-slate-500">{{ __('people.city') }}: {{ $profile->city }}</p>@endif
        </section>
        @if($teaching)
            <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-700 dark:bg-[#142d4f]">
                <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-black text-[#0b2d59] dark:text-white">{{ __('people.teaching_portfolio') }}</h2><span class="rounded-full bg-[#fff1d1] px-3 py-1 text-xs font-extrabold text-[#87561d]">{{ $courses->count() }} {{ __('frontend.courses_title') }}</span></div>
                <p class="mt-2 text-xs leading-6 text-slate-500">{{ __('people.courses_note') }}</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                @forelse($courses as $course)
                    <x-public-course-card :course="$course" />
                @empty
                    <p class="col-span-full text-sm leading-7 text-slate-500">{{ __('people.no_teaching_courses') }}</p>
                @endforelse
                </div>
            </section>
        @else
            <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-700 dark:bg-[#142d4f]">
                <h2 class="text-xl font-black text-[#0b2d59] dark:text-white">{{ __('people.learning_portfolio') }}</h2>
                <p class="mt-3 text-sm leading-7 text-slate-500">{{ __('people.student_privacy_note') }}</p>
                @if($learning!==null)
                    <div class="mt-5 grid grid-cols-2 gap-4">
                        <div class="rounded-2xl bg-slate-50 p-5 text-center dark:bg-[#1c3b61]"><p class="text-3xl font-black text-[#0c2d5b] dark:text-white">{{ \App\Support\DashboardNumber::compact($learning['courses']) }}</p><p class="mt-2 text-xs font-bold">{{ __('people.approved_enrollments') }}</p></div>
                        <div class="rounded-2xl bg-slate-50 p-5 text-center dark:bg-[#1c3b61]"><p class="text-3xl font-black text-[#0c2d5b] dark:text-white">{{ \App\Support\DashboardNumber::compact($learning['completed_lessons']) }}</p><p class="mt-2 text-xs font-bold">{{ __('people.completed_lessons') }}</p></div>
                    </div>
                @else
                    <p class="mt-5 rounded-2xl border border-dashed border-slate-200 p-5 text-sm leading-7 text-slate-500 dark:border-slate-700">{{ __('people.no_public_learning_stats') }}</p>
                @endif
            </section>
        @endif
    </div>
    <aside class="space-y-5">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#142d4f]">
            <div class="flex items-center gap-3"><x-workspace-icon name="shield-check" class="h-7 w-7 text-[#c2943f]" /><h2 class="text-lg font-black">{{ __('people.public_profile') }}</h2></div>
            <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ __('people.consent_notice') }}</p>
            <p class="mt-3 text-xs leading-7 text-slate-500">{{ __('people.no_contact') }}</p>
        </section>
        <section class="rounded-3xl bg-[#0b2e5d] p-6 text-white">
            <h2 class="text-xl font-black">{{ __('people.explore_next') }}</h2>
            <p class="mt-3 text-sm leading-7 text-[#cadbef]">{{ __('people.learning_link_intro') }}</p>
            <a href="{{ route('courses.index') }}" class="mt-5 block rounded-xl bg-[#eebd5f] px-5 py-3 text-center text-xs font-black text-[#0e315b] hover:bg-[#ffdc95]">{{ __('people.study_courses') }}</a>
            <a href="{{ $teaching ? route('instructors.index') : route('students.index') }}" class="mt-3 block rounded-xl border border-white/40 px-5 py-3 text-center text-xs font-bold text-white hover:bg-white/10">{{ __('people.back_to_directory') }}</a>
        </section>
    </aside>
</div>
@endsection
