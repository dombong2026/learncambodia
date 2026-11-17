@extends('layouts.public')
@section('canonical', e(route('courses.show', $course->getRouteKey())))
@section('title', $course->title)
@section('meta_description', $course->summary)
@section('content')
<x-detail-hero :title="$course->title" :summary="$course->summary" :entry="$course" :label="__('detail.course')">
    <x-slot:meta>
        <li><x-workspace-icon name="book-open" class="h-4 w-4 text-[#b58128] dark:text-[#efbe62]"/>{{ number_format($course->lessons_count) }} {{ __('frontend.lessons') }}</li>
        <li><x-workspace-icon name="school" class="h-4 w-4 text-[#b58128] dark:text-[#efbe62]"/>{{ __('pages.mode_'.$course->delivery_mode) }}</li>
        <li><x-workspace-icon name="shield-check" class="h-4 w-4 text-[#b58128] dark:text-[#efbe62]"/>{{ __('pages.academically_reviewed') }}</li>
        @if($course->cefr_level)<li class="rounded-md bg-blue-100 px-2 py-0.5 text-blue-950 dark:bg-white/10 dark:text-white">CEFR {{ $course->cefr_level }}</li>@endif
    </x-slot:meta>
    <x-slot:rail>
        <h2 class="text-xl font-black">{{ __('frontend.start_learning') }}</h2>
        <p class="mt-3 text-xs leading-7 text-[#d1e0ef]">{{ __('frontend.enrollment_note') }}</p>
        @php($quote = \App\Support\CoursePricing::quote($course->pricing))
        <x-course-price :course="$course" :quote="$quote" class="mt-3 text-sm font-bold text-[#f6cd7f]" />
        @if($quote['model'] !== 'free')
            <p class="mt-2 text-xs leading-6 text-[#d1e0ef]">The school verifies payment or membership before granting access.</p>
        @endif
        @auth
            @if(auth()->user()->role === \App\Models\User::ROLE_STUDENT)
                @if($existingEnrollment?->status === 'approved')
                    <a href="{{ route('account.enrollments.show',$existingEnrollment) }}" class="mt-5 block rounded-xl bg-[#efbe62] px-5 py-3 text-center text-xs font-black text-[#0b2f5c] hover:bg-[#ffdc91]">{{ __('dashboard.resume_learning') }}</a>
                @elseif($existingEnrollment)
                    <p class="mt-5 rounded-xl bg-white/10 p-4 text-xs font-bold text-[#ffe4a1]">{{ __('frontend.enrollment_requested') }}: {{ ucfirst($existingEnrollment->status) }}</p>
                    <a href="{{ route('account.enrollments.index') }}" class="mt-3 block rounded-xl border border-[#d9b464] px-5 py-3 text-center text-xs font-bold text-white hover:bg-white/10">{{ __('frontend.view_my_enrollments') }}</a>
                @else
                    <form method="POST" action="{{ route('account.enrollments.store',$course) }}" class="mt-5">@csrf
                        @if($quote['model'] !== 'free')
                            <input type="hidden" name="pricing_quote" value="{{ json_encode($quote, JSON_THROW_ON_ERROR) }}">
                        @endif
                        <button type="submit" class="w-full rounded-xl bg-[#efbe62] px-5 py-3 text-center text-xs font-black text-[#0b2f5c] hover:bg-[#ffdc91]">{{ __('frontend.request_enrollment') }}</button>
                    </form>
                @endif
            @else
                <a href="{{ route(auth()->user()->homeRouteName()) }}" class="mt-5 block rounded-xl bg-[#efbe62] px-5 py-3 text-center text-xs font-black text-[#0b2f5c] hover:bg-[#ffdc91]">{{ __('frontend.open_my_workspace') }}</a>
            @endif
        @else
            <a href="{{ route('register') }}" class="mt-5 block rounded-xl bg-[#efbe62] px-5 py-3 text-center text-xs font-black text-[#0b2f5c] hover:bg-[#ffdc91]">{{ __('frontend.create_account') }}</a>
            <a href="{{ route('login') }}" class="mt-3 block rounded-xl border border-[#d9b464] px-5 py-3 text-center text-xs font-bold text-white hover:bg-white/10">{{ __('frontend.sign_in') }}</a>
        @endauth
        <a href="#course-curriculum" class="mt-4 block text-center text-xs font-bold text-[#f6cd7f] hover:underline">{{ __('pages.view_curriculum') }}</a>
        <p class="mt-5 border-t border-white/20 pt-4 text-[11px] leading-6 text-[#c5d7e9]">{{ __('frontend.no_certificate_claim') }}</p>
    </x-slot:rail>
</x-detail-hero>

<div class="detail-grid mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    <div class="min-w-0 space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-700 dark:bg-[#152e50]">
            <p class="text-xs font-black uppercase tracking-[.12em] text-[#b27a25]">{{ __('pages.learning_objectives') }}</p>
            <h2 class="mt-2 text-xl font-black text-[#0c315c] dark:text-white">{{ __('pages.what_you_learn') }}</h2>
            <x-course-description :course="$course" class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-300" />
        </section>
        <section id="course-curriculum" class="scroll-mt-40 overflow-hidden rounded-3xl border border-[#c69a4d] bg-[#0e315e] p-7 text-white shadow-md">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="text-xs font-black uppercase tracking-[.12em] text-[#f1c36b]">{{ __('pages.module_overview') }}</p><h2 class="mt-2 text-2xl font-black">{{ __('frontend.course_outline') }}</h2></div>
                <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold">{{ $course->modules->count() }} {{ __('pages.modules') }}</span>
            </div>
            <p class="mt-3 text-xs leading-7 text-[#ccdeec]">{{ __('frontend.curriculum_safe') }}</p>
            <div class="mt-6 space-y-3">
                @forelse($course->modules as $module)
                    <details class="group rounded-xl bg-white text-[#12355c]">
                        <summary class="flex cursor-pointer items-center justify-between gap-4 px-5 py-4 text-sm font-black">
                            <span class="min-w-0 break-words">{{ $module->title }}</span>
                            <span class="flex shrink-0 items-center gap-2 text-xs font-bold text-slate-500">{{ $module->lessons_count }} {{ __('frontend.lessons') }} <x-workspace-icon name="chevron-down" class="h-4 w-4 transition group-open:rotate-180"/></span>
                        </summary>
                        <div class="border-t border-slate-200 px-5 py-4 text-xs leading-7 text-slate-600">{{ __('pages.lesson_access_note') }}</div>
                    </details>
                @empty
                    <p class="rounded-xl border border-white/20 bg-white/10 p-6 text-sm">{{ __('frontend.no_outline') }}</p>
                @endforelse
            </div>
        </section>
    </div>

    <aside class="min-w-0 space-y-5">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#152e50]">
            <h2 class="text-xl font-black text-[#12355e] dark:text-white">{{ __('pages.your_instructors') }}</h2>
            <p class="mt-2 text-xs leading-7 text-slate-500 dark:text-slate-300">{{ __('pages.approved_teacher_note') }}</p>
            <div class="mt-5 space-y-3">
                @forelse($educators as $profile)
                    <a href="{{ route('instructors.show',$profile) }}" class="flex items-center gap-3 rounded-2xl border border-slate-100 p-4 hover:border-[#e6bc70] dark:border-slate-700">
                        <span class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-[#153c69] text-xl font-black text-[#e8bd62]">{{ mb_strtoupper(mb_substr($profile->display_name,0,1)) }}</span>
                        <span class="min-w-0 flex-1"><strong class="block text-sm">{{ $profile->display_name }}</strong><small class="mt-1 block text-xs text-slate-500 dark:text-slate-300">{{ $profile->headline }}</small></span>
                        <x-workspace-icon name="arrow-up-right" class="h-4 w-4 text-[#b78a36]"/>
                    </a>
                @empty
                    <p class="rounded-2xl border border-dashed border-slate-200 p-5 text-sm leading-7 text-slate-500 dark:border-slate-700 dark:text-slate-300">{{ __('pages.no_public_instructors') }}</p>
                @endforelse
            </div>
            <a href="{{ route('instructors.index') }}" class="mt-4 block rounded-xl bg-[#123662] px-5 py-3 text-center text-xs font-bold text-white">{{ __('pages.meet_instructors') }}</a>
        </section>
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#152e50]">
            <h2 class="text-xl font-black text-[#12355e] dark:text-white">{{ __('pages.study_process') }}</h2>
            <div class="mt-5 space-y-4">
                @foreach([['user-round',__('pages.step_account')],['user-check',__('pages.step_approval')],['book-open',__('pages.step_study')]] as [$icon,$title])
                    <div class="flex items-center gap-3 text-xs"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#fff0d2]"><x-workspace-icon :name="$icon" class="h-5 w-5 text-[#ac7929]" /></span><strong class="leading-6">{{ $title }}</strong></div>
                @endforeach
            </div>
        </section>
        <section class="rounded-3xl bg-[#0c305e] p-6 text-white">
            <h2 class="text-lg font-black">{{ __('pages.explore_related') }}</h2>
            <a href="{{ route('courses.index') }}" class="mt-4 block rounded-xl bg-[#eebd61] px-5 py-3 text-center text-xs font-black text-[#0c315f]">{{ __('frontend.courses_title') }}</a>
        </section>
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#152e50]">
            <h2 class="text-xl font-black text-[#0d315c] dark:text-white">{{ __('pages.course_requirements') }}</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ __('pages.course_requirements_desc') }}</p>
            <a href="{{ route('cefr.index') }}" class="mt-4 inline-flex rounded-xl border border-[#e5b86a] px-5 py-3 text-xs font-black text-[#a06d26] dark:text-[#efbe62]">{{ __('pages.read_cefr') }}</a>
        </section>
    </aside>
</div>
@if($relatedCourses->isNotEmpty())
<section class="border-t border-slate-200 bg-white py-12 dark:border-slate-800 dark:bg-[#112a4a]">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-black text-[#102f5a] dark:text-white">{{ __('pages.related_courses') }}</h2>
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($relatedCourses as $related)<x-public-course-card :course="$related" />@endforeach
        </div>
    </div>
</section>
@endif
@endsection
