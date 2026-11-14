@extends('layouts.public')
@section('title',\App\Support\Appearance::value('author','heading'))
@section('meta_description',\App\Support\Appearance::value('author','intro'))
@section('content')
@php $appearance=\App\Support\Appearance::approved('author'); @endphp
<section class="bg-gradient-to-br from-[#09254c] via-[#163d6d] to-[#0d2d55] text-white">
    <div class="mx-auto max-w-[1600px] px-4 py-14 sm:px-8 lg:px-12 lg:py-20">
        <nav aria-label="Breadcrumb" class="mb-10 flex gap-2 text-xs text-[#cbdcf2]"><a href="{{ route('home') }}" class="hover:underline">{{ __('longpage.home') }}</a><span>/</span><span aria-current="page" class="font-bold text-[#f3ca76]">{{ __('longpage.authors') }}</span></nav>
        <div class="grid items-center gap-10 lg:grid-cols-[1.2fr_.8fr]">
            <div>
                <p class="text-xs font-black uppercase tracking-[.16em] text-[#f2c875]">{{ __('longpage.author_eyebrow') }}</p>
                <h1 class="mt-4 max-w-4xl text-4xl font-black leading-tight sm:text-5xl xl:text-6xl">{{ $appearance['heading'] }}</h1>
                <p class="mt-6 max-w-3xl text-base leading-9 text-[#d4e4f3]">{{ $appearance['intro'] }}</p>
                <div class="mt-7 flex flex-wrap gap-3"><a href="#author-contributions" class="rounded-xl bg-[#efc16a] px-6 py-3 text-sm font-black text-[#0b2e57]">{{ __('longpage.explore_articles') }}</a>
                    <a href="{{ route('information.show','about') }}" class="rounded-xl border border-white/30 px-6 py-3 text-sm font-bold hover:bg-white/10">{{ __('longpage.about') }}</a></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                @foreach([
                    ['newspaper',$articleCount,__('longpage.published_articles')],
                    ['users',$authors->count(),__('longpage.opted_in_authors')],
                    ['book-open',$courseCount,__('longpage.published_courses')],
                    ['tags',$categoryCount,__('longpage.published_categories')],
                ] as [$icon,$count,$label])
                    <div class="premium-card premium-card-on-navy relative min-h-36 rounded-2xl border border-white/20 bg-white/10 p-5"><x-workspace-icon :name="$icon" class="absolute right-4 top-4 h-6 w-6 text-[#f3c46c]"/>
                        <p class="mt-8 text-4xl font-black tabular-nums" title="{{ number_format($count) }}">{{ \App\Support\DashboardNumber::compact($count) }}</p>
                        <p class="mt-2 text-xs font-semibold leading-5 text-[#d2e1ef]">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<section class="bg-white py-16 dark:bg-[#10294b]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <p class="text-xs font-extrabold uppercase tracking-widest text-[#b17b26]">{{ __('longpage.author_quality_label') }}</p>
        <h2 class="mt-2 text-3xl font-black text-[#10325e] dark:text-white">{{ $appearance['method_heading'] }}</h2>
        <p class="mt-4 max-w-4xl text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __('longpage.author_quality_intro') }}</p>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            @foreach([
                ['pen-square','longpage.author_step_write','longpage.author_step_write_note'],
                ['shield-check','longpage.author_step_review','longpage.author_step_review_note'],
                ['newspaper','longpage.author_step_publish','longpage.author_step_publish_note'],
            ] as [$icon,$heading,$description])
                <article class="premium-card rounded-3xl border border-slate-200 bg-[#f7f9fc] p-7 dark:border-slate-700 dark:bg-[#193b5e]"><span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="$icon" /></span>
                    <h3 class="mt-5 text-xl font-black text-[#10355f] dark:text-white">{{ __($heading) }}</h3>
                    <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __($description) }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section id="author-contributions" class="scroll-mt-28 bg-[#f1f4f8] py-16 dark:bg-[#102d4f]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div><p class="text-xs font-black uppercase tracking-widest text-[#b27e25]">{{ __('longpage.editorial_library') }}</p>
                <h2 class="mt-2 text-3xl font-black text-[#11355e] dark:text-white">{{ $appearance['work_heading'] }}</h2>
                <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __('longpage.author_contributions_note') }}</p>
            </div>
            <a href="{{ route('articles.index') }}" class="rounded-xl border border-[#d7b16a] px-5 py-3 text-sm font-bold text-[#ab7b2e] dark:text-[#f3ca75]">{{ __('longpage.all_articles') }}</a>
        </div>
        <form action="{{ route('authors.index') }}#author-contributions" method="GET" class="mt-7 flex max-w-2xl gap-2" role="search">
            <label for="author-post-search" class="sr-only">{{ __('longpage.search_articles') }}</label>
            <input id="author-post-search" type="search" name="q" value="{{ $search }}" maxlength="80" placeholder="{{ __('longpage.search_articles') }}" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-[#162d4e]">
            <button type="submit" class="rounded-xl bg-[#12365f] px-5 py-3 text-xs font-black text-white">{{ __('longpage.search') }}</button>
        </form>
        <div class="mt-7 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($articles as $article)
                <a href="{{ route('articles.show',$article->slug) }}" class="premium-card group flex flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition hover:border-[#e0b766] hover:shadow-lg dark:border-slate-700 dark:bg-[#193653]">
                    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon name="newspaper" /></span><div class="premium-card-content flex items-center justify-between gap-4"><span class="rounded-full bg-[#f8ecd3] px-3 py-1 text-xs font-black text-[#946b22]">{{ __('longpage.approved_article') }}</span></div>
                    <h3 class="mt-5 break-words text-xl font-black text-[#12345e] group-hover:underline dark:text-white">{{ $article->title }}</h3>
                    <p class="mt-3 flex-1 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $article->summary }}</p>
                    <span class="mt-6 text-xs font-black text-[#a67b32]">{{ __('frontend.read_article') }}</span>
                </a>
            @empty
                <p class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-9 text-sm text-slate-500 dark:border-slate-700 dark:bg-[#193653]">{{ __('longpage.no_published_articles') }}</p>
            @endforelse
        </div>
        <div class="mt-8">{{ $articles->links() }}</div>
    </div>
</section>

<section class="bg-[#10345f] py-16 text-white">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-4"><div>
            <p class="text-xs font-black uppercase tracking-widest text-[#f3c874]">{{ __('longpage.public_contributors') }}</p>
            <h2 class="mt-2 text-3xl font-black">{{ $appearance['people_heading'] }}</h2>
            <p class="mt-3 max-w-4xl text-sm leading-8 text-[#d2e2f1]">{{ __('longpage.author_consent_note') }}</p>
        </div><a href="{{ route('instructors.index') }}" class="rounded-xl bg-[#efc26c] px-5 py-3 text-sm font-black text-[#0e315d]">{{ __('longpage.view_instructors') }}</a></div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse($authors as $profile)
                <a href="{{ route('authors.show',$profile) }}" class="premium-card group flex min-h-52 flex-col rounded-3xl border border-white/15 bg-white p-6 text-[#12335e] transition hover:border-[#edc774] hover:shadow-xl dark:bg-[#1b3b5e] dark:text-white">
                    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon name="user-round" /></span><div class="grid h-16 w-16 place-items-center rounded-full bg-gradient-to-br from-[#154b82] to-[#0e2d57] text-2xl font-black text-[#f4c774]">{{ mb_strtoupper(mb_substr($profile->display_name,0,1)) }}</div>
                    <h3 class="mt-5 break-words text-lg font-black">{{ $profile->display_name }}</h3>
                    <p class="mt-2 flex-1 text-xs leading-7 text-slate-500 dark:text-slate-300">{{ $profile->headline }}</p>
                    <span class="mt-5 text-xs font-bold text-[#ad7d29]">{{ __('longpage.view_contributions') }}</span>
                </a>
            @empty
                <p class="col-span-full rounded-2xl border border-white/20 bg-white/10 p-8 text-sm text-[#d5e4f0]">{{ __('longpage.no_consented_authors') }}</p>
            @endforelse
        </div>
    </div>
</section>

<section class="border-t border-slate-200 bg-white py-16 dark:border-slate-800 dark:bg-[#0e2846]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <h2 class="text-3xl font-black text-[#11355e] dark:text-white">{{ __('longpage.author_learning_tools') }}</h2>
        <p class="mt-4 max-w-4xl text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __('longpage.author_tools_note') }}</p>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            @foreach([
                ['book-open','longpage.author_course_tool','longpage.author_course_tool_note',route('courses.index')],
                ['award','longpage.author_cefr_tool','longpage.author_cefr_tool_note',route('cefr.index')],
                ['users','longpage.author_teacher_tool','longpage.author_teacher_tool_note',route('instructors.index')],
            ] as [$icon,$heading,$note,$url])
                <a href="{{ $url }}" class="premium-card group rounded-3xl border border-slate-200 bg-[#f8fafc] p-7 transition hover:border-[#d7ae64] dark:border-slate-700 dark:bg-[#193757]">
                    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="$icon" /></span>
                    <h3 class="mt-5 text-xl font-black text-[#11345d] dark:text-white">{{ __($heading) }}</h3>
                    <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __($note) }}</p>
                    <span class="mt-5 inline-block text-xs font-black text-[#b07b22]">{{ __('longpage.explore') }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="bg-gradient-to-r from-[#0a2851] to-[#194475] py-14 text-white">
    <div class="mx-auto flex max-w-[1600px] flex-col justify-between gap-6 px-4 sm:px-8 lg:flex-row lg:items-center lg:px-12">
        <div><h2 class="text-3xl font-black">{{ $appearance['cta_heading'] }}</h2><p class="mt-3 max-w-3xl text-sm leading-8 text-[#d4e3f1]">{{ __('longpage.author_cta_note') }}</p></div>
        <a href="{{ route('articles.index') }}" class="self-start rounded-xl bg-[#efc16a] px-7 py-3.5 text-sm font-black text-[#11335b]">{{ __('longpage.all_articles') }}</a>
    </div>
</section>
@endsection
