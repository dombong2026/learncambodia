@extends('layouts.public')
@section('canonical', e(route('courses.show', $course->getRouteKey())))
@section('title', $course->title)
@section('meta_description', $course->summary)
@if($course->featured_kind === 'image' && $course->featuredMedia)
    @section('og_image', route('editor-media.show', $course->featuredMedia))
@endif
@section('content')
<x-detail-hero :title="$course->title" :entry="$course" :label="__('detail.course')">
    <x-slot:meta>
        <li><x-workspace-icon name="book-open" class="h-4 w-4 text-[#b58128] dark:text-[#efbe62]"/>{{ number_format($course->lessons_count) }} {{ __('frontend.lessons') }}</li>
        <li><x-workspace-icon name="school" class="h-4 w-4 text-[#b58128] dark:text-[#efbe62]"/>{{ __('pages.mode_'.$course->delivery_mode) }}</li>
        <li><x-workspace-icon name="shield-check" class="h-4 w-4 text-[#b58128] dark:text-[#efbe62]"/>{{ __('pages.academically_reviewed') }}</li>
        @if($course->cefr_level)<li class="rounded-md bg-blue-100 px-2 py-0.5 text-blue-950 dark:bg-white/10 dark:text-white">CEFR {{ $course->cefr_level }}</li>@endif
    </x-slot:meta>
    <x-slot:rail>
        <h2 class="text-xl font-black">{{ __('frontend.start_learning') }}</h2>
        @php
            $quote = \App\Support\CoursePricing::quote($course->pricing);
            $student = auth()->user()?->role === \App\Models\User::ROLE_STUDENT;
            $primary = 'mt-4 block w-full rounded-xl bg-[#efbe62] px-5 py-3 text-center text-xs font-black text-[#0b2f5c] hover:bg-[#ffdc91]';
            $secondary = 'mt-3 block rounded-xl border border-white/35 px-5 py-3 text-center text-xs font-bold text-white hover:bg-white/10';
        @endphp
        <x-course-price :course="$course" :quote="$quote" class="mt-3 text-sm font-bold text-[#f6cd7f]" />
        @if($quote['model'] !== 'free' && $existingEnrollment?->status !== 'approved')
            <p class="mt-2 text-xs leading-6 text-[#d1e0ef]">The school verifies payment or membership before granting access.</p>
        @endif
        @guest
            <p class="mt-3 text-xs leading-6 text-[#d1e0ef]">{{ __('detail.sign_in_to_enroll') }}</p>
            <a href="{{ \App\Support\ReadingReturn::authUrl(request(), 'login') }}" class="{{ $primary }}">{{ __('detail.enroll') }}</a>
            <a href="{{ \App\Support\ReadingReturn::authUrl(request(), 'register') }}" class="{{ $secondary }}">{{ __('frontend.create_account') }}</a>
        @elseif(!$student)
            <p class="mt-3 text-xs leading-6 text-[#d1e0ef]">{{ __('detail.student_enrollment_only') }}</p>
            <a href="{{ route(auth()->user()->homeRouteName()) }}" class="{{ $secondary }}">{{ __('frontend.open_my_workspace') }}</a>
        @elseif(!$existingEnrollment)
            @if($quote['model'] !== 'free')
                <p class="mt-3 text-xs leading-6 text-[#d1e0ef]">{{ __('detail.enroll_review_note') }}</p>
            @endif
            <form method="POST" action="{{ route('account.enrollments.store', $course) }}">
                @csrf
                @if($quote['model'] !== 'free')
                    <input type="hidden" name="pricing_quote" value="{{ json_encode($quote, JSON_THROW_ON_ERROR) }}">
                @endif
                <button type="submit" class="{{ $primary }}">{{ __('detail.enroll') }}</button>
            </form>
        @elseif($existingEnrollment->status === 'approved')
            @if($hasStarted)
                <a href="{{ route('account.progress.index', ['enrollment' => $existingEnrollment->id]) }}" class="{{ $primary }}">{{ __('detail.learning_progress') }}</a>
                <a href="{{ route('account.enrollments.show', $existingEnrollment) }}" class="{{ $secondary }}">{{ __('dashboard.resume_learning') }}</a>
            @else
                <a href="{{ route('account.enrollments.show', $existingEnrollment) }}" class="{{ $primary }}">{{ __('detail.already_enrolled') }}</a>
            @endif
        @else
            <p class="mt-4 rounded-xl bg-white/10 p-3 text-xs font-semibold text-[#ffe4a1]">
                {{ match ($existingEnrollment->status) {
                    'pending' => __('detail.enrollment_pending'),
                    'cancelled' => __('detail.enrollment_cancelled'),
                    'rejected' => __('detail.enrollment_rejected'),
                    default => __('frontend.enrollment_requested'),
                } }}
            </p>
            <a href="{{ route('account.enrollments.index') }}" class="{{ $secondary }}">{{ __('frontend.view_my_enrollments') }}</a>
        @endguest
        <a href="#course-curriculum" class="mt-4 block text-center text-xs font-bold text-[#f6cd7f] hover:underline">{{ __('pages.view_curriculum') }}</a>
        <p class="mt-5 border-t border-white/20 pt-4 text-[11px] leading-6 text-[#c5d7e9]">{{ __('frontend.no_certificate_claim') }}</p>
        <x-detail-share :url="route('courses.show', $course->getRouteKey())" :title="$course->title" :instructors="$educators" />
    </x-slot:rail>
</x-detail-hero>

<div class="detail-grid mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    <div class="min-w-0 space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-700 dark:bg-[#152e50]">
            <p class="text-xs font-black uppercase tracking-[.12em] text-[#805718] dark:text-[#efbe62]">{{ __('pages.learning_objectives') }}</p>
            <h2 class="mt-2 text-xl font-black text-[#0c315c] dark:text-white">{{ __('pages.what_you_learn') }}</h2>
            <div class="rich-public-content course-learning-checklist mt-4 break-words text-sm leading-7 text-slate-700 dark:text-slate-200">
                {!! \App\Support\CourseDescriptionChecklist::render($course->description_html, filled($course->description) ? $course->description : $course->summary) !!}
            </div>
        </section>
        <x-learning-detail-metadata :record="$course" kind="course" />
        <section id="course-curriculum" class="scroll-mt-40 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-700 dark:bg-[#102d51]">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[.12em] text-[#805718] dark:text-[#efbe62]">{{ __('pages.module_overview') }}</p>
                    <h2 class="mt-2 text-2xl font-black text-[#0c315c] dark:text-white">{{ __('frontend.course_outline') }}</h2>
                </div>
                <span class="rounded-full bg-[#edf4fe] px-3 py-1.5 text-xs font-bold text-[#28517c] dark:bg-white/10 dark:text-slate-100">{{ $course->modules->count() }} {{ __('pages.modules') }}</span>
            </div>
            <div class="mt-5 space-y-3">
                @forelse($course->modules as $module)
                    <details class="course-outline-module group rounded-xl border border-slate-200 bg-slate-50 text-[#12355c] dark:border-[#355475] dark:bg-[#17395f] dark:text-slate-100">
                        <summary class="flex cursor-pointer items-center justify-between gap-2 rounded-xl px-3 py-3 text-sm font-bold sm:gap-4 sm:px-4">
                            <span class="flex min-w-0 items-center gap-2 sm:gap-3">
                                <span class="inline-grid min-h-7 min-w-7 shrink-0 place-items-center rounded-lg bg-[#e4eefc] px-1.5 text-xs tabular-nums text-[#234c7a] dark:bg-[#273f61] dark:text-[#f5c76e]" data-module-number>{{ $loop->iteration }}</span>
                                <span class="min-w-0 break-words">{{ $module->title }}</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">{{ trans_choice('detail.lesson_count', $module->lessons->count()) }} <x-workspace-icon name="chevron-down" class="h-4 w-4 transition group-open:rotate-180"/></span>
                        </summary>
                        @if($module->lessons->isEmpty())
                            <p class="border-t border-slate-200 px-4 py-3 text-sm text-slate-600 dark:border-[#355475] dark:text-slate-300">{{ __('detail.no_module_lessons') }}</p>
                        @else
                            <ul class="divide-y divide-slate-200 rounded-b-xl border-t border-slate-200 bg-white dark:divide-[#355475] dark:border-[#355475] dark:bg-[#122b48]" aria-label="{{ __('frontend.lessons') }}">
                                @foreach($module->lessons as $lesson)
                                    <li class="flex items-start justify-between gap-3 px-3 py-3 text-sm sm:px-4">
                                        <span class="flex min-w-0 items-start gap-2 break-words">
                                            <x-workspace-icon name="book-open" class="mt-0.5 h-4 w-4 shrink-0 text-[#527295] dark:text-[#9bb6d3]" />
                                            <span class="min-w-0">{{ $lesson->title }}</span>
                                        </span>
                                        <span class="shrink-0 tabular-nums text-slate-600 dark:text-slate-300">{{ trans_choice('detail.minutes', $lesson->duration_minutes) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </details>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-600 dark:border-slate-600 dark:text-slate-200">{{ __('frontend.no_outline') }}</p>
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
