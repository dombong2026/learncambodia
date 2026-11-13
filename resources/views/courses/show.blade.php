@extends('layouts.public')
@section('title', $course->title)
@section('meta_description', $course->summary)
@section('content')
<section class="border-b border-slate-200 bg-gradient-to-br from-[#f8fafc] via-white to-[#edf2f9] dark:border-slate-700 dark:from-[#102b4b] dark:via-[#12365d] dark:to-[#0b284e]">
    <div class="mx-auto max-w-7xl px-4 py-9 sm:px-6 lg:px-8">
        <nav aria-label="Breadcrumb" class="mb-7 flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-300">
            <a href="{{ route('home') }}" class="font-semibold hover:underline">{{ __('people.home') }}</a><span>/</span>
            <a href="{{ route('courses.index') }}" class="font-semibold hover:underline">{{ __('frontend.courses_title') }}</a><span>/</span>
            <a href="{{ route('categories.show',$course->category) }}" class="font-semibold hover:underline">{{ $course->category?->name }}</a><span>/</span>
            <span aria-current="page" class="font-bold text-[#12335e] dark:text-[#f6cc7d]">{{ $course->title }}</span>
        </nav>
        <div class="grid items-stretch gap-6 lg:grid-cols-[1.1fr_.88fr_275px]">
            <div class="min-w-0">
                <div class="flex flex-wrap gap-2 text-xs font-black">
                    <span class="rounded-lg bg-[#f5e1b4] px-3 py-1.5 text-[#7f5719]">{{ $course->category?->name }}</span>
                    @if($course->cefr_level)<span class="rounded-lg bg-[#dfe8f5] px-3 py-1.5 text-[#17345f]">CEFR {{ $course->cefr_level }}</span>@endif
                </div>
                <h1 class="mt-5 break-words text-3xl font-black leading-tight text-[#0c2e5b] dark:text-white sm:text-4xl">{{ $course->title }}</h1>
                <p class="mt-4 text-sm leading-8 text-slate-700 dark:text-[#d6e3ef]">{{ $course->summary }}</p>
                <div class="mt-5 flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-600 dark:text-slate-300">
                    <span class="inline-flex items-center gap-1.5"><x-workspace-icon name="book-open" class="h-4 w-4 text-[#b58128]"/>{{ number_format($course->lessons_count) }} {{ __('frontend.lessons') }}</span>
                    <span class="inline-flex items-center gap-1.5"><x-workspace-icon name="school" class="h-4 w-4 text-[#b58128]"/>{{ __('pages.mode_'.$course->delivery_mode) }}</span>
                    <span class="inline-flex items-center gap-1.5"><x-workspace-icon name="shield-check" class="h-4 w-4 text-[#b58128]"/>{{ __('pages.academically_reviewed') }}</span>
                </div>
            </div>
            <div class="relative flex min-h-[230px] flex-col items-center justify-center overflow-hidden rounded-3xl bg-gradient-to-br from-[#133a68] to-[#0a284f] px-6 py-8 text-center text-white">
                <div class="absolute -right-8 -top-10 h-44 w-44 rounded-full border-[22px] border-[#f5c468]/10" aria-hidden="true"></div>
                <x-workspace-icon name="book-open" class="relative h-16 w-16 text-[#f0c570]"/>
                <p class="relative mt-5 text-lg font-black leading-7">{{ $course->category?->name }}</p>
                <p class="relative mt-2 text-xs font-semibold text-[#d7e5f4]">{{ __('pages.lesson_preview_note') }}</p>
                <a href="#course-curriculum" class="relative mt-6 rounded-xl border border-white/40 px-5 py-2.5 text-xs font-bold hover:bg-white/10">{{ __('pages.view_curriculum') }}</a>
            </div>
            <aside class="flex flex-col rounded-3xl bg-[#0d305f] p-6 text-white shadow-xl">
                <p class="text-[11px] font-black uppercase tracking-[.14em] text-[#f6cd7f]">{{ __('frontend.start_learning') }}</p>
                <h2 class="mt-3 text-xl font-black">{{ __('pages.ready_to_study') }}</h2>
                <p class="mt-3 flex-1 text-xs leading-7 text-[#d1e0ef]">{{ __('frontend.enrollment_note') }}</p>
                @auth
                    @if(auth()->user()->role === \App\Models\User::ROLE_STUDENT)
                        @if($existingEnrollment?->status === 'approved')
                            <a href="{{ route('account.enrollments.show',$existingEnrollment) }}" class="mt-5 block rounded-xl bg-[#efbe62] px-5 py-3 text-center text-xs font-black text-[#0b2f5c] hover:bg-[#ffdc91]">{{ __('dashboard.resume_learning') }}</a>
                        @elseif($existingEnrollment)
                            <p class="mt-5 rounded-xl bg-white/10 p-4 text-xs font-bold text-[#ffe4a1]">{{ __('frontend.enrollment_requested') }}: {{ ucfirst($existingEnrollment->status) }}</p>
                            <a href="{{ route('account.enrollments.index') }}" class="mt-3 block rounded-xl border border-[#d9b464] px-5 py-3 text-center text-xs font-bold text-white hover:bg-white/10">{{ __('frontend.view_my_enrollments') }}</a>
                        @else
                            <form method="POST" action="{{ route('account.enrollments.store',$course) }}" class="mt-5">@csrf
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
                <p class="mt-5 border-t border-white/20 pt-4 text-[11px] leading-6 text-[#c5d7e9]">{{ __('frontend.no_certificate_claim') }}</p>
            </aside>
        </div>
    </div>
</section>

<div class="mx-auto grid max-w-7xl gap-8 px-4 py-11 sm:px-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(280px,.85fr)] lg:px-8">
    <div class="space-y-7">
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-700 dark:bg-[#152e50]">
            <p class="text-xs font-black uppercase tracking-[.12em] text-[#b27a25]">{{ __('pages.learning_objectives') }}</p>
            <h2 class="mt-2 text-xl font-black text-[#0c315c] dark:text-white">{{ __('pages.what_you_learn') }}</h2>
            <p class="mt-4 whitespace-pre-line break-words text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $course->description }}</p>
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
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-700 dark:bg-[#152e50]">
            <h2 class="text-xl font-black text-[#0d315c] dark:text-white">{{ __('pages.course_requirements') }}</h2>
            <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __('pages.course_requirements_desc') }}</p>
            <a href="{{ route('cefr.index') }}" class="mt-4 inline-flex rounded-xl border border-[#e5b86a] px-5 py-3 text-xs font-black text-[#a06d26]">{{ __('pages.read_cefr') }}</a>
        </section>
    </div>

    <aside class="space-y-7">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#152e50]">
            <h2 class="text-xl font-black text-[#12355e] dark:text-white">{{ __('pages.your_instructors') }}</h2>
            <p class="mt-2 text-xs leading-7 text-slate-500">{{ __('pages.approved_teacher_note') }}</p>
            <div class="mt-5 space-y-3">
                @forelse($educators as $profile)
                    <a href="{{ route('instructors.show',$profile) }}" class="flex items-center gap-3 rounded-2xl border border-slate-100 p-4 hover:border-[#e6bc70] dark:border-slate-700">
                        <span class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-[#153c69] text-xl font-black text-[#e8bd62]">{{ mb_strtoupper(mb_substr($profile->display_name,0,1)) }}</span>
                        <span class="min-w-0 flex-1"><strong class="block text-sm">{{ $profile->display_name }}</strong><small class="mt-1 block text-xs text-slate-500">{{ $profile->headline }}</small></span>
                        <x-workspace-icon name="arrow-up-right" class="h-4 w-4 text-[#b78a36]"/>
                    </a>
                @empty
                    <p class="rounded-2xl border border-dashed border-slate-200 p-5 text-sm leading-7 text-slate-500 dark:border-slate-700">{{ __('pages.no_public_instructors') }}</p>
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
            <p class="mt-2 text-xs leading-7 text-[#d5e3f0]">{{ __('pages.related_intro') }}</p>
            <a href="{{ route('courses.index',['category'=>$course->category->slug]) }}" class="mt-4 block rounded-xl bg-[#eebd61] px-5 py-3 text-center text-xs font-black text-[#0c315f]">{{ __('pages.more_in_category') }}</a>
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
