@extends('layouts.public')
@section('title', __('pages.home_title'))
@section('meta_description', __('pages.home_intro'))
@section('content')
<section class="relative isolate overflow-hidden bg-gradient-to-br from-[#09234b] via-[#123962] to-[#0b2a53] text-white">
    <div class="pointer-events-none absolute -right-20 top-4 h-80 w-80 rounded-full bg-[#f4c266]/10 blur-3xl" aria-hidden="true"></div>
    <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1.1fr_.9fr] lg:px-8 lg:py-20">
        <div class="relative">
            <span class="inline-flex items-center rounded-lg bg-[#eebd65] px-3 py-1.5 text-xs font-extrabold uppercase tracking-[.1em] text-[#12355e]">{{ __('pages.home_eyebrow') }}</span>
            <h1 class="mt-6 max-w-3xl text-4xl font-black leading-tight tracking-tight sm:text-5xl">{{ \App\Support\Appearance::value('home','hero_title') }}</h1>
            <p class="mt-5 max-w-2xl text-sm leading-8 text-[#d9e5f1] sm:text-base">{{ \App\Support\Appearance::value('home','hero_intro') }}</p>
            <form action="{{ route('courses.index') }}" method="GET" role="search" class="mt-7 flex max-w-xl gap-2">
                <label for="hero-course-search" class="sr-only">{{ __('frontend.search_courses') }}</label>
                <input id="hero-course-search" type="search" name="q" maxlength="80" placeholder="{{ __('pages.hero_search') }}" class="min-w-0 flex-1 rounded-xl border border-white/10 bg-white px-4 py-3 text-sm text-[#122f58] outline-none focus:ring-2 focus:ring-[#f4c36e]" />
                <button type="submit" class="rounded-xl bg-[#f0be61] px-5 py-3 text-sm font-extrabold text-[#173760] hover:bg-[#ffda93]">{{ __('pages.search') }}</button>
            </form>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('courses.index') }}" class="rounded-lg border border-white/30 px-5 py-2.5 text-xs font-bold text-white hover:bg-white/10">{{ __('pages.browse_courses') }}</a>
                <a href="{{ route('instructors.index') }}" class="rounded-lg border border-white/30 px-5 py-2.5 text-xs font-bold text-white hover:bg-white/10">{{ __('pages.meet_instructors') }}</a>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:gap-4">
            @foreach([
                ['icon'=>'book-open','value'=>$publishedCourseCount,'label'=>__('pages.published_courses'),'url'=>route('courses.index')],
                ['icon'=>'tags','value'=>$publishedCategoryCount,'label'=>__('pages.learning_categories'),'url'=>route('categories.index')],
                ['icon'=>'newspaper','value'=>$publishedArticleCount,'label'=>__('pages.published_articles'),'url'=>route('articles.index')],
                ['icon'=>'award','value'=>6,'label'=>__('pages.cefr_levels'),'url'=>route('cefr.index')],
            ] as $tile)
                <a href="{{ $tile['url'] }}" class="group rounded-2xl border border-white/15 bg-[#173c68]/80 p-5 shadow-lg transition hover:border-[#f4c36e] hover:bg-[#234e80]">
                    <span class="flex justify-between gap-2"><x-workspace-icon :name="$tile['icon']" class="h-6 w-6 text-[#eebc61]" /><x-workspace-icon name="arrow-up-right" class="h-4 w-4 text-[#a6c6e6]" /></span>
                    <p class="mt-4 text-3xl font-black tabular-nums text-white" title="{{ number_format($tile['value']) }}">{{ \App\Support\DashboardNumber::compact($tile['value']) }}</p>
                    <p class="mt-1 text-xs font-semibold leading-5 text-[#c5d8ee]">{{ $tile['label'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="grid gap-5 lg:grid-cols-[1.1fr_1fr_1fr]">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#152e51]">
            <div class="flex items-center justify-between gap-3"><h2 class="text-lg font-black text-[#0e305d] dark:text-white">{{ __('pages.popular_pathways') }}</h2><a href="{{ route('categories.index') }}" class="text-xs font-bold text-[#b98223] hover:underline">{{ __('pages.view_all') }}</a></div>
            <div class="mt-5 space-y-3">
                @forelse($featuredCategories->take(5) as $category)
                    <a href="{{ route('categories.show',$category) }}" class="flex items-center gap-3 rounded-2xl border border-slate-100 p-3 transition hover:border-[#ecc775] hover:bg-amber-50 dark:border-slate-700 dark:hover:bg-[#214265]">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#f8ebce] text-xs font-black text-[#795018] dark:bg-[#4c4026] dark:text-[#ffe5aa]">{{ $category->code }}</span>
                        <span class="min-w-0 flex-1"><strong class="block truncate text-sm text-[#133761] dark:text-white">{{ $category->name }}</strong><small class="mt-1 block text-[11px] text-slate-500">{{ \App\Models\Category::GROUPS[$category->group] ?? __('pages.study_pathway') }}</small></span>
                        <x-workspace-icon name="arrow-up-right" class="h-4 w-4 text-[#bc902e]" />
                    </a>
                @empty
                    <p class="text-sm leading-7 text-slate-500">{{ __('pages.empty_pathways') }}</p>
                @endforelse
            </div>
        </section>
        <section class="flex flex-col gap-4">
            <a href="{{ route('courses.index') }}" class="flex-1 rounded-3xl bg-gradient-to-br from-[#123b6b] to-[#0a2854] p-6 text-white shadow-lg transition hover:from-[#1c4a7e]">
                <x-workspace-icon name="book-open" class="h-8 w-8 text-[#edbf68]" />
                <h2 class="mt-4 text-xl font-black">{{ __('pages.flexible_learning') }}</h2>
                <p class="mt-3 text-sm leading-7 text-[#cbdcf1]">{{ __('pages.flexible_learning_desc') }}</p>
                <span class="mt-4 inline-block text-xs font-black text-[#ffd480]">{{ __('pages.browse_courses') }} →</span>
            </a>
            <a href="{{ route('cefr.index') }}" class="flex-1 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-amber-400 dark:border-slate-700 dark:bg-[#152e51]">
                <x-workspace-icon name="award" class="h-8 w-8 text-[#d29a38]" />
                <h2 class="mt-4 text-lg font-black text-[#0c2d5b] dark:text-white">{{ __('pages.level_guidance') }}</h2>
                <p class="mt-2 text-sm leading-7 text-slate-500">{{ __('pages.level_guidance_desc') }}</p>
            </a>
        </section>
        <section class="rounded-3xl border border-[#cda95d] bg-[#10345f] p-6 text-white shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[.12em] text-[#f3c56d]">{{ __('pages.educator_workspace') }}</p>
            <h2 class="mt-5 text-xl font-black">{{ __('pages.instructor_support') }}</h2>
            <p class="mt-4 text-sm leading-8 text-[#d4e2f3]">{{ __('pages.instructor_support_desc') }}</p>
            <div class="mt-6 grid grid-cols-3 gap-2">
                @foreach([['school',__('pages.courses')],['clipboard-check',__('pages.assignments')],['chart-line',__('pages.progress')]] as [$icon,$label])
                    <div class="rounded-xl bg-white/10 px-2 py-4 text-center"><x-workspace-icon :name="$icon" class="mx-auto h-6 w-6 text-[#edbf68]" /><p class="mt-2 text-[11px] font-bold">{{ $label }}</p></div>
                @endforeach
            </div>
            <a href="{{ route('login') }}" class="mt-6 block rounded-xl bg-[#eebd64] px-4 py-3 text-center text-xs font-black text-[#123461] hover:bg-[#ffd78e]">{{ __('pages.sign_in_to_workspace') }}</a>
        </section>
    </div>
</section>

<section class="bg-[#0d2c57] py-12 text-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-widest text-[#e7b456]">{{ __('pages.find_your_level') }}</p><h2 class="mt-2 text-2xl font-black">{{ __('pages.study_corner') }}</h2></div><a href="{{ route('cefr.index') }}" class="text-xs font-bold text-[#f9ce7b] hover:underline">{{ __('pages.read_cefr') }}</a></div>
        <div class="mt-6 grid grid-cols-3 gap-3 sm:grid-cols-6">
            @foreach(['A1','A2','B1','B2','C1','C2'] as $level)
                <a href="{{ route('cefr.index') }}#{{ $level }}" class="rounded-xl border border-white/20 bg-white/10 py-5 text-center text-xl font-black transition hover:border-[#f3c064] hover:bg-[#24518a]">{{ $level }}</a>
            @endforeach
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><p class="text-xs font-bold uppercase tracking-widest text-[#b98027]">{{ __('pages.approved_courses') }}</p><h2 class="mt-2 text-2xl font-black text-[#0c2e5c] dark:text-white">{{ __('pages.start_a_course') }}</h2></div>
        <a href="{{ route('courses.index') }}" class="rounded-xl border border-[#e0b764] px-5 py-2.5 text-xs font-bold text-[#153b64] dark:text-[#f5ce82]">{{ __('pages.view_all') }} →</a>
    </div>
    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($featuredCourses as $course)
            <x-public-course-card :course="$course" />
        @empty
            <p class="col-span-full rounded-3xl border border-dashed border-slate-300 p-8 text-sm leading-8 text-slate-500 dark:border-slate-700">{{ __('frontend.no_courses') }}</p>
        @endforelse
    </div>
</section>

<section class="border-y border-slate-200 bg-white py-14 dark:border-slate-800 dark:bg-[#10294a]">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-widest text-[#b98027]">{{ __('pages.verified_profiles') }}</p><h2 class="mt-2 text-2xl font-black text-[#0c2e5c] dark:text-white">{{ __('pages.featured_instructors') }}</h2></div><a href="{{ route('instructors.index') }}" class="text-xs font-bold text-[#b98027] hover:underline">{{ __('pages.meet_instructors') }} →</a></div>
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse($featuredInstructors as $profile)
                <x-public-person-card :profile="$profile" kind="instructor" />
            @empty
                <p class="col-span-full rounded-2xl border border-dashed border-slate-300 p-7 text-sm text-slate-500 dark:border-slate-700">{{ __('people.no_instructors') }}</p>
            @endforelse
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
    <h2 class="text-2xl font-black text-[#102f5d] dark:text-white">{{ __('pages.how_it_works') }}</h2>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['compass',__('pages.step_discover'),__('pages.step_discover_desc'),route('courses.index')],
            ['user-round',__('pages.step_account'),__('pages.step_account_desc'),route('register')],
            ['user-check',__('pages.step_approval'),__('pages.step_approval_desc'),auth()->check() ? route(auth()->user()->homeRouteName()) : route('login')],
            ['book-open-check',__('pages.step_study'),__('pages.step_study_desc'),route('cefr.index')],
        ] as $step)
            <a href="{{ $step[3] }}" class="rounded-3xl border border-slate-200 bg-white p-6 transition hover:border-[#e2b76d] dark:border-slate-700 dark:bg-[#153052]">
                <span class="grid h-14 w-14 place-items-center rounded-full bg-[#f8eccd]"><x-workspace-icon :name="$step[0]" class="h-7 w-7 text-[#ae7724]" /></span>
                <h3 class="mt-5 text-sm font-black">{{ $step[1] }}</h3><p class="mt-2 text-xs leading-6 text-slate-500 dark:text-slate-300">{{ $step[2] }}</p>
            </a>
        @endforeach
    </div>
</section>

<section class="border-t border-slate-200 bg-[#f3f5fa] py-14 dark:border-slate-800 dark:bg-[#0d2444]">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-3"><h2 class="text-2xl font-black text-[#0b2e58] dark:text-white">{{ __('pages.study_resources') }}</h2><a href="{{ route('articles.index') }}" class="text-xs font-extrabold text-[#b17c23]">{{ __('pages.view_all') }} →</a></div>
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($latestArticles as $article)
                <a href="{{ route('articles.show',$article->slug) }}" class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#d8ad5b] dark:border-slate-700 dark:bg-[#153052]"><span class="text-[11px] font-black uppercase tracking-widest text-[#b17c23]">{{ __('frontend.study_resources') }}</span><h3 class="mt-3 text-lg font-black text-[#11325e] dark:text-white">{{ $article->title }}</h3><p class="mt-3 flex-1 text-sm leading-7 text-slate-500">{{ $article->summary }}</p><span class="mt-5 text-xs font-bold text-[#b17c23]">{{ __('frontend.read_article') }} →</span></a>
            @empty
                <p class="col-span-full rounded-2xl border border-dashed border-slate-300 p-7 text-sm text-slate-500">{{ __('frontend.no_articles') }}</p>
            @endforelse
        </div>
    </div>
</section>
@endsection
