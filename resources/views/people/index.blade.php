@extends('layouts.public')
@section('title', $kind==='instructor' ? __('people.instructors_title') : __('people.students_title'))
@if($kind==='student')@section('robots','noindex,nofollow')@endif
@section('content')
@php $teaching=$kind==='instructor'; @endphp
<section class="border-b border-[#264976] bg-gradient-to-br from-[#0b254b] via-[#123a6c] to-[#0b254b] text-white">
    <div class="mx-auto grid max-w-7xl items-center gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1.45fr_1fr] lg:px-8 lg:py-16">
        <div>
            <p class="text-xs font-black uppercase tracking-[.14em] text-[#f5c96f]">{{ __('people.directory') }}</p>
            <h1 class="mt-3 max-w-3xl text-4xl font-black leading-tight sm:text-5xl">{{ $teaching ? __('people.instructors_title') : __('people.students_title') }}</h1>
            <p class="mt-4 max-w-2xl text-sm leading-8 text-[#d3e1f2]">{{ $teaching ? __('people.instructors_intro') : __('people.students_intro') }}</p>
            <form method="GET" action="{{ $teaching ? route('instructors.index') : route('students.index') }}" class="mt-6 flex max-w-lg gap-2">
                <label for="person-query" class="sr-only">{{ __('people.search') }}</label>
                <input id="person-query" name="q" type="search" maxlength="80" value="{{ $query }}" placeholder="{{ __('people.search_placeholder') }}" class="min-w-0 flex-1 rounded-xl border border-white/20 bg-white px-4 py-3 text-sm text-[#14325e] outline-none focus:ring-2 focus:ring-[#f4c776]">
                <button type="submit" class="rounded-xl bg-[#ebba57] px-5 py-3 text-sm font-black text-[#10325c] transition hover:bg-[#ffd68a]">{{ __('frontend.apply') }}</button>
            </form>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('courses.index') }}" class="rounded-2xl border border-white/15 bg-white/10 p-5 text-left transition hover:bg-white/20"><x-workspace-icon name="book-open" class="h-7 w-7 text-[#f2c66d]" /><p class="mt-3 text-sm font-black">{{ __('people.study_courses') }}</p><p class="mt-1 text-xs leading-6 text-[#cbdff4]">{{ __('people.browse_approved') }}</p></a>
            <a href="{{ route('cefr.index') }}" class="rounded-2xl border border-white/15 bg-white/10 p-5 text-left transition hover:bg-white/20"><x-workspace-icon name="award" class="h-7 w-7 text-[#f2c66d]" /><p class="mt-3 text-sm font-black">{{ __('people.level_guidance') }}</p><p class="mt-1 text-xs leading-6 text-[#cbdff4]">{{ __('people.cefr_explained') }}</p></a>
        </div>
    </div>
</section>
<div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(260px,.75fr)] lg:px-8">
    <div>
        <div class="mb-5 flex flex-wrap items-end justify-between gap-2">
            <div><p class="text-xs font-black uppercase tracking-widest text-[#b1842d]">{{ __('people.approved_directory') }}</p><h2 class="mt-2 text-2xl font-black text-[#12335f] dark:text-white">{{ $teaching ? __('people.instructor_profiles') : __('people.student_stories') }}</h2></div>
            <span class="text-xs font-semibold text-slate-500">{{ number_format($profiles->total()) }} {{ __('people.results') }}</span>
        </div>
        @if(!$teaching && !config('public_profiles.students_enabled'))
            <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-7 text-amber-900 dark:border-amber-900 dark:bg-[#392f27] dark:text-[#f5d99c]">{{ __('people.student_privacy_gate') }}</div>
        @endif
        <div class="grid gap-5 sm:grid-cols-2">
            @forelse($profiles as $profile)
                <x-public-person-card :profile="$profile" :kind="$kind" />
            @empty
                <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white p-9 text-sm leading-8 text-slate-500 dark:border-slate-700 dark:bg-[#142b4b]">{{ $teaching ? __('people.no_instructors') : __('people.no_students') }}</div>
            @endforelse
        </div>
        <div class="mt-7">{{ $profiles->links() }}</div>
    </div>
    <aside class="space-y-5">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#142c4d]">
            <h2 class="text-lg font-black text-[#0c2f5d] dark:text-white">{{ __('people.quality_title') }}</h2>
            <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ __('people.quality_description') }}</p>
            <div class="mt-5 grid grid-cols-2 gap-3">
                @foreach([['shield-check','people.quality_review'],['user-check','people.consent'],['lock','people.no_private_data'],['book-open','people.real_courses']] as [$icon,$label])
                    <div class="rounded-xl bg-slate-50 p-4 text-center dark:bg-[#1d3b60]"><x-workspace-icon :name="$icon" class="mx-auto h-6 w-6 text-[#b77f27]" /><p class="mt-3 text-xs font-bold">{{ __($label) }}</p></div>
                @endforeach
            </div>
        </section>
        <section class="rounded-3xl bg-[#0c2e5c] p-6 text-white">
            <h2 class="text-xl font-black">{{ $teaching ? __('people.teach_with_us') : __('people.your_learning') }}</h2>
            <p class="mt-3 text-sm leading-7 text-[#d0e0f1]">{{ $teaching ? __('people.teacher_application_note') : __('people.student_application_note') }}</p>
            <a href="{{ $teaching ? route('information.show','careers') : route('courses.index') }}" class="mt-5 inline-flex rounded-xl bg-[#eab859] px-5 py-3 text-xs font-black text-[#0c2e5c] hover:bg-[#ffd889]">{{ $teaching ? __('people.teaching_information') : __('people.study_courses') }}</a>
        </section>
    </aside>
</div>
@endsection
