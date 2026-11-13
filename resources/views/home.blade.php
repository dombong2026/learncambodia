@extends('layouts.public')
@section('title', 'Learn English with confidence')
@section('content')
<section class="relative isolate overflow-hidden bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950 text-white">
    <div class="pointer-events-none absolute -right-32 top-0 h-96 w-96 rounded-full bg-blue-500/20 blur-3xl" aria-hidden="true"></div>
    <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 py-20 sm:px-6 lg:grid-cols-[1.15fr_.85fr] lg:px-8 lg:py-28">
        <div>
            <p class="mb-5 inline-flex rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[.16em] text-blue-100">English learning • Exam preparation • Teacher development</p>
            <h1 class="max-w-2xl text-4xl font-black tracking-tight sm:text-5xl lg:text-6xl">{{ \App\Support\Appearance::value('home','hero_title') }}</h1>
            <p class="mt-6 max-w-xl text-base leading-8 text-slate-300 sm:text-lg">{{ \App\Support\Appearance::value('home','hero_intro') }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a class="rounded-full bg-white px-6 py-3 text-sm font-bold text-slate-950 transition hover:-translate-y-0.5 hover:bg-blue-50" href="{{ route('categories.index') }}">{{ __('ui.explore_categories') }}</a>
                <a class="rounded-full border border-white/30 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10" href="{{ route('cefr.index') }}">{{ __('ui.view_cefr') }}</a>
            </div>
        </div>
        <div class="rounded-3xl border border-white/15 bg-white/10 p-6 shadow-2xl backdrop-blur-md sm:p-8">
            <p class="mb-5 text-sm font-semibold text-blue-100">Find your pathway</p>
            <div class="grid grid-cols-3 gap-3">
                @foreach (['A1','A2','B1','B2','C1','C2'] as $level)
                    <a href="{{ route('cefr.index') }}#{{ $level }}" class="rounded-2xl border border-white/15 bg-white/10 p-5 text-center text-xl font-black transition hover:-translate-y-0.5 hover:bg-white/20">{{ $level }}</a>
                @endforeach
            </div>
            <p class="mt-5 text-xs leading-6 text-blue-100">CEFR levels are a language proficiency framework, not an examination score or certification.</p>
        </div>
    </div>
</section>
<section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-bold uppercase tracking-widest text-blue-700 dark:text-blue-400">Learning pathways</p><h2 class="mt-2 text-3xl font-extrabold tracking-tight">Choose where you want to grow</h2></div>
        <a href="{{ route('categories.index') }}" class="text-sm font-semibold text-blue-700 hover:underline dark:text-blue-400">Browse all categories →</a>
    </div>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($featuredCategories as $category)
            <a href="{{ route('categories.show',$category) }}" class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-blue-300 hover:shadow-xl dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-700">
                <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ $category->code }}</span>
                <h3 class="mt-5 text-xl font-bold group-hover:text-blue-700 dark:group-hover:text-blue-400">{{ $category->name }}</h3>
                <p class="mt-3 line-clamp-3 text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $category->description }}</p>
                <span class="mt-6 inline-block text-sm font-semibold text-blue-700 dark:text-blue-400">Explore pathway →</span>
            </a>
        @empty
            <p class="text-slate-500">The category catalogue is being prepared.</p>
        @endforelse
    </div>
</section>
<section class="border-y border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-14 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
        <div><h2 class="text-2xl font-extrabold">Know your level. Plan your next step.</h2><p class="mt-2 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-400">See the six CEFR proficiency levels, from everyday introductions to advanced professional communication.</p></div>
        <a href="{{ route('cefr.index') }}" class="self-start rounded-full bg-blue-700 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-800">Explore A1–C2 levels</a>
    </div>
</section>
<section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-xs font-black uppercase tracking-[.16em] text-[#b27b24] dark:text-[#f2c46c]">{{ __('frontend.public_featured') }}</p><h2 class="mt-2 text-3xl font-black tracking-tight text-[#0c2d5b] dark:text-white">{{ __('frontend.available_courses') }}</h2></div>
        <a href="{{ route('courses.index') }}" class="rounded-full border border-[#e3b864] px-5 py-2.5 text-sm font-bold text-[#0c2d5b] hover:bg-amber-50 dark:text-[#f2c46c] dark:hover:bg-slate-900">{{ __('frontend.courses_title') }} →</a>
    </div>
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
    @forelse($featuredCourses as $course)
        <x-public-course-card :course="$course" />
    @empty
        <p class="col-span-full rounded-3xl border border-dashed border-slate-300 p-8 text-sm leading-7 text-slate-500 dark:border-slate-700">{{ __('frontend.no_courses') }} <a class="font-bold text-blue-700 hover:underline dark:text-blue-400" href="{{ route('categories.index') }}">{{ __('frontend.explore_categories') }}</a></p>
    @endforelse
    </div>
</section>
<section class="border-y border-[#dce5ef] bg-white py-16 dark:border-slate-800 dark:bg-[#0c213f]">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-7 flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-xs font-black uppercase tracking-[.16em] text-[#b27b24]">{{ __('frontend.study_resources') }}</p><h2 class="mt-2 text-3xl font-black text-[#0c2d5b] dark:text-white">{{ __('frontend.articles') }}</h2></div>
            <a href="{{ route('articles.index') }}" class="text-sm font-bold text-blue-700 hover:underline dark:text-[#f0c274]">{{ __('frontend.all_articles') }} →</a>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($latestArticles as $article)
                <a href="{{ route('articles.show',$article->slug) }}" class="group flex flex-col rounded-3xl border border-slate-200 bg-[#f8fafc] p-6 shadow-sm hover:border-[#d6b16d] dark:border-slate-700 dark:bg-[#163458]"><h3 class="text-xl font-black leading-snug text-[#0c2d5b] group-hover:underline dark:text-white">{{ $article->title }}</h3><p class="mt-4 flex-1 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $article->summary }}</p><span class="mt-5 text-xs font-bold text-blue-700 dark:text-[#f5c974]">{{ __('frontend.read_article') }} →</span></a>
            @empty
                <p class="col-span-full rounded-2xl border border-dashed border-slate-300 p-8 text-sm text-slate-500 dark:border-slate-700">{{ __('frontend.no_articles') }}</p>
            @endforelse
        </div>
    </div>
</section>
@endsection
