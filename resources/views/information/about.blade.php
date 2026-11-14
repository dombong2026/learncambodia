@extends('layouts.public')
@section('title',$title)
@section('meta_description',$lede)
@section('content')
@php $appearance=\App\Support\Appearance::approved('about'); @endphp
<section class="relative overflow-hidden bg-gradient-to-br from-[#0a2854] via-[#153f70] to-[#0c2a52] text-white">
    <div class="pointer-events-none absolute -right-20 top-0 h-80 w-80 rounded-full border-[54px] border-[#eec36d]/15" aria-hidden="true"></div>
    <div class="mx-auto max-w-[1600px] px-4 py-14 sm:px-8 lg:px-12 lg:py-20">
        <nav aria-label="Breadcrumb" class="mb-10 flex gap-2 text-xs text-[#d0e0f0]"><a href="{{ route('home') }}" class="hover:underline">{{ __('longpage.home') }}</a><span>/</span><span aria-current="page" class="font-bold text-[#f3cb7e]">{{ __('longpage.about') }}</span></nav>
        <div class="grid items-center gap-10 lg:grid-cols-[1.3fr_.7fr]">
            <div>
                <p class="text-xs font-black uppercase tracking-[.16em] text-[#f4c970]">{{ __('longpage.about_eyebrow') }}</p>
                <h1 class="mt-4 max-w-4xl text-4xl font-black leading-tight sm:text-5xl xl:text-6xl">{{ $appearance['heading'] }}</h1>
                <p class="mt-6 max-w-3xl text-base leading-9 text-[#d9e6f4]">{{ $appearance['intro'] }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="#about-mission" class="rounded-xl bg-[#f1c36a] px-6 py-3 text-sm font-black text-[#0e315c] hover:bg-[#ffdd99]">{{ __('longpage.our_mission') }}</a>
                    <a href="{{ route('information.show','contact') }}" class="rounded-xl border border-white/30 px-6 py-3 text-sm font-bold hover:bg-white/10">{{ __('longpage.contact_us') }}</a>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                @foreach([
                    ['book-open',$courseCount,__('longpage.published_courses')],
                    ['tags',$categoryCount,__('longpage.published_categories')],
                    ['newspaper',$publishedArticles,__('longpage.published_articles')],
                    ['award',$cefrLevelCount,__('longpage.cefr_levels')],
                ] as [$icon,$value,$label])
                    <div class="premium-card premium-card-on-navy relative min-h-36 rounded-2xl border border-white/20 bg-white/10 p-5">
                        <x-workspace-icon :name="$icon" class="absolute right-4 top-4 h-6 w-6 text-[#f1c977]"/>
                        <p class="mt-8 text-4xl font-black tabular-nums" title="{{ number_format($value) }}">{{ \App\Support\DashboardNumber::compact($value) }}</p>
                        <p class="mt-2 text-xs font-bold leading-6 text-[#d3e3f3]">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<section id="about-mission" class="scroll-mt-28 bg-white py-16 dark:bg-[#0e284a]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <p class="text-xs font-black uppercase tracking-widest text-[#af7c26]">{{ __('longpage.why_learn_cambodia') }}</p>
        <h2 class="mt-3 text-3xl font-black text-[#10325e] dark:text-white">{{ __('longpage.about_purpose') }}</h2>
        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            @foreach([
                ['target',$appearance['mission_heading'],$appearance['mission_body']],
                ['eye',$appearance['vision_heading'],$appearance['vision_body']],
                ['shield-check',__('longpage.values_heading'),__('longpage.values_body')],
            ] as [$icon,$heading,$description])
                <article class="premium-card flex flex-col rounded-3xl border border-slate-200 bg-[#f8fafc] p-8 shadow-sm dark:border-slate-700 dark:bg-[#183759]">
                    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="$icon" /></span>
                    <h3 class="mt-6 text-2xl font-black text-[#12355e] dark:text-white">{{ $heading }}</h3>
                    <p class="mt-4 flex-1 whitespace-pre-line text-sm leading-9 text-slate-600 dark:text-slate-300">{{ $description }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="bg-[#f0f3f8] py-16 dark:bg-[#102c4d]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="max-w-4xl"><p class="text-xs font-black uppercase tracking-widest text-[#b17f26]">{{ __('longpage.learning_method') }}</p><h2 class="mt-3 text-3xl font-black text-[#0e315f] dark:text-white">{{ $appearance['method_heading'] }}</h2>
            <p class="mt-4 text-sm leading-9 text-slate-600 dark:text-slate-300">{{ __('longpage.method_intro') }}</p></div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['compass','longpage.about_method_discover','longpage.about_method_discover_note',route('categories.index')],
                ['shield-check','longpage.about_method_review','longpage.about_method_review_note',route('courses.index')],
                ['book-open','longpage.about_method_study','longpage.about_method_study_note',route('courses.index')],
                ['chart-line','longpage.about_method_progress','longpage.about_method_progress_note',route('login')],
            ] as [$icon,$heading,$note,$url])
                <a href="{{ $url }}" class="premium-card group rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition hover:border-[#dcae57] hover:shadow-lg dark:border-slate-700 dark:bg-[#163758]">
                    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="$icon" /></span>
                    <h3 class="mt-5 text-lg font-black text-[#12345f] dark:text-white">{{ __($heading) }}</h3>
                    <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __($note) }}</p>
                    <span class="mt-5 inline-block text-xs font-black text-[#b4852b]">{{ __('longpage.explore') }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="bg-[#10345f] py-16 text-white">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div><p class="text-xs font-extrabold uppercase tracking-[.14em] text-[#efc475]">{{ __('longpage.public_team') }}</p><h2 class="mt-2 text-3xl font-black">{{ __('longpage.approved_educators') }}</h2><p class="mt-4 max-w-3xl text-sm leading-8 text-[#d7e5f4]">{{ __('longpage.about_team_note') }}</p></div>
            <a href="{{ route('instructors.index') }}" class="rounded-xl bg-[#f1c16c] px-5 py-3 text-sm font-black text-[#0c315d]">{{ __('longpage.view_instructors') }}</a>
        </div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse($instructors as $profile)<x-public-person-card :profile="$profile" kind="instructor"/>
            @empty<p class="col-span-full rounded-2xl border border-white/20 bg-white/10 p-8 text-sm text-[#d6e4f3]">{{ __('people.no_instructors') }}</p>@endforelse
        </div>
    </div>
</section>

<section class="bg-white py-16 dark:bg-[#0c2645]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <h2 class="text-3xl font-black text-[#10345d] dark:text-white">{{ __('longpage.about_trust_heading') }}</h2>
        <p class="mt-4 max-w-4xl text-sm leading-9 text-slate-600 dark:text-slate-300">{{ __('longpage.about_trust_note') }}</p>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            @foreach([
                ['badge-check','longpage.trust_accurate','longpage.trust_accurate_desc'],
                ['lock','longpage.trust_privacy','longpage.trust_privacy_desc'],
                ['users','longpage.trust_review','longpage.trust_review_desc'],
            ] as [$icon,$title,$note])
                <div class="premium-card rounded-3xl border border-slate-200 bg-[#f8fafc] p-7 dark:border-slate-700 dark:bg-[#163656]">
                    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="$icon" /></span>
                    <h3 class="mt-5 text-xl font-black">{{ __($title) }}</h3>
                    <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __($note) }}</p>
                </div>
            @endforeach
        </div>
        <p class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-xs leading-8 text-amber-900 dark:border-amber-900 dark:bg-[#45351c] dark:text-[#f3d18e]">{{ __('longpage.about_no_accreditation') }}</p>
    </div>
</section>

<section class="border-t border-slate-200 bg-[#f0f3f8] py-16 dark:border-slate-800 dark:bg-[#102c4d]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <h2 class="text-3xl font-black text-[#10345d] dark:text-white">{{ __('longpage.faq') }}</h2>
        <div class="mt-6 space-y-3">
            @foreach([['longpage.about_faq_one','longpage.about_faq_one_answer'],['longpage.about_faq_two','longpage.about_faq_two_answer'],['longpage.about_faq_three','longpage.about_faq_three_answer']] as [$q,$a])
                <details class="group rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-[#163656]">
                    <summary class="flex cursor-pointer items-center justify-between gap-3 text-sm font-black text-[#10355f] dark:text-white">{{ __($q) }} <x-workspace-icon name="chevron-down" class="h-5 w-5 shrink-0 transition group-open:rotate-180"/></summary>
                    <p class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __($a) }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

<section class="bg-gradient-to-r from-[#0c2d56] to-[#174576] py-14 text-white">
    <div class="mx-auto flex max-w-[1600px] flex-col justify-between gap-6 px-4 sm:px-8 lg:flex-row lg:items-center lg:px-12">
        <div><h2 class="text-3xl font-black">{{ $appearance['join_heading'] }}</h2><p class="mt-3 max-w-3xl text-sm leading-8 text-[#d6e5f4]">{{ __('longpage.about_cta_note') }}</p></div>
        <div class="flex flex-wrap gap-3"><a href="{{ route('courses.index') }}" class="rounded-xl bg-[#eec46d] px-6 py-3 text-sm font-black text-[#12345e]">{{ __('longpage.browse_course_catalog') }}</a><a href="{{ route('information.show','contact') }}" class="rounded-xl border border-white/30 px-6 py-3 text-sm font-black text-white">{{ __('longpage.contact_us') }}</a></div>
    </div>
</section>
@endsection
