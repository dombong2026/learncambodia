@extends('layouts.public')
@section('title', \App\Support\Appearance::value('categories','heading'))
@section('meta_description', \App\Support\Appearance::value('categories','intro'))
@section('content')
@php
    $appearance = \App\Support\Appearance::approved('categories');
    $directoryParams = $query !== '' ? ['q' => $query] : [];
@endphp
<section class="relative isolate overflow-hidden bg-gradient-to-br from-[#08254d] via-[#113a6a] to-[#0a2c57] text-white">
    <div class="pointer-events-none absolute -right-28 -top-36 h-96 w-96 rounded-full border-[42px] border-[#e7b957]/10" aria-hidden="true"></div>
    <div class="mx-auto max-w-[1600px] px-4 py-14 sm:px-8 lg:px-12 lg:py-20">
        <nav aria-label="Breadcrumb" class="mb-9 flex gap-2 text-xs text-[#bed3ec]">
            <a href="{{ route('home') }}" class="hover:text-white hover:underline">{{ __('longpage.home') }}</a><span aria-hidden="true">/</span><span aria-current="page" class="font-bold text-[#f3c772]">{{ __('longpage.categories') }}</span>
        </nav>
        <div class="grid items-center gap-10 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,.75fr)]">
            <div>
                <p class="text-xs font-black uppercase tracking-[.18em] text-[#f2c878]">{{ __('longpage.learning_directory') }}</p>
                <h1 class="mt-4 max-w-4xl text-4xl font-black leading-tight sm:text-5xl xl:text-6xl">{{ $appearance['heading'] }}</h1>
                <p class="mt-5 max-w-3xl text-base leading-9 text-[#d9e5f4]">{{ $appearance['intro'] }}</p>
                <form action="{{ route('categories.index') }}" method="GET" role="search" class="mt-7 flex max-w-2xl gap-2">
                    <label for="category-hero-search" class="sr-only">{{ __('longpage.search_categories') }}</label>
                    <input id="category-hero-search" name="q" value="{{ $query }}" maxlength="80" type="search" placeholder="{{ __('longpage.search_categories') }}" class="min-w-0 flex-1 rounded-xl border border-white/20 bg-white px-4 py-3 text-sm text-[#122e57] outline-none focus:ring-2 focus:ring-[#f2c46c]">
                    @if($group)<input type="hidden" name="group" value="{{ $group }}">@endif
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#efbe5e] px-5 py-3 text-sm font-black text-[#0d2f5d] hover:bg-[#ffdb91]"><x-workspace-icon name="search" class="h-4 w-4"/>{{ __('longpage.search') }}</button>
                </form>
            </div>
            <div class="grid grid-cols-2 gap-4">
                @foreach([
                    ['book-open',$publishedCourses,__('longpage.published_courses'),route('courses.index')],
                    ['tags',$publishedCategories,__('longpage.published_categories'),route('categories.index')],
                    ['graduation',$cefrLevelCount,__('longpage.cefr_levels'),route('cefr.index')],
                    ['users',$featuredInstructors->count(),__('longpage.featured_educators'),route('instructors.index')],
                ] as [$icon,$value,$label,$href])
                    <a href="{{ $href }}" class="premium-card premium-card-on-navy group flex min-h-36 flex-col justify-between rounded-2xl border border-white/15 bg-white/10 p-5 transition hover:border-[#efc16b] hover:bg-white/20">
                        <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="$icon" /></span>
                        <span class="premium-card-content mt-7 text-4xl font-black tabular-nums" title="{{ number_format($value) }}">{{ \App\Support\DashboardNumber::compact($value) }}</span>
                        <span class="premium-card-content mt-2 text-xs font-semibold leading-5 text-[#d6e5f2]">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

<section id="learning-focus" class="border-b border-slate-200 bg-white py-16 dark:border-slate-800 dark:bg-[#0d2546]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-[.16em] text-[#b1822c]">{{ __('longpage.choose_focus') }}</p>
                <h2 class="mt-2 text-3xl font-black text-[#0c315d] dark:text-white">{{ $appearance['groups_heading'] }}</h2>
                <p class="mt-3 max-w-3xl text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __('longpage.group_intro') }}</p>
            </div>
            <a href="{{ route('categories.index', $directoryParams) }}#category-directory" class="rounded-xl border border-[#ddb569] px-5 py-3 text-sm font-bold text-[#0d315d] transition hover:border-[#b98827] hover:bg-[#fff4dc] dark:text-[#f3cc80] dark:hover:bg-[#213e5d]">{{ __('longpage.view_all_categories') }}</a>
        </div>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach(\App\Models\Category::GROUPS as $slug=>$label)
                @php $icon = \App\Support\CategoryVisual::groupIcon($slug); @endphp
                <a href="{{ route('categories.index', array_merge($directoryParams, ['group' => $slug])) }}#category-directory" class="premium-card group flex min-h-52 flex-col rounded-3xl border border-slate-200 bg-[#f8fafc] p-6 transition hover:-translate-y-0.5 hover:border-[#e2b867] hover:shadow-xl dark:border-slate-700 dark:bg-[#163456]">
                    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="$icon" /></span>
                    <h3 class="premium-card-content mt-16 text-lg font-black leading-7 text-[#12345f] dark:text-white">{{ $label }}</h3>
                    <p class="premium-card-content mt-3 text-xs font-semibold text-slate-500 dark:text-slate-300">{{ number_format((int) ($groupCounts[$slug] ?? 0)) }} {{ (int) ($groupCounts[$slug] ?? 0) === 1 ? __('longpage.category_one') : __('longpage.category_many') }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section id="category-directory" class="py-16">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-widest text-[#ad7d26]">{{ __('longpage.real_catalog') }}</p>
                <h2 class="mt-2 text-3xl font-black text-[#0c315d] dark:text-white">{{ $appearance['pathways_heading'] }}</h2>
                <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __('longpage.directory_intro') }}</p>
            </div>
            <span class="rounded-full bg-[#fff1d2] px-4 py-2 text-xs font-black text-[#90631b] dark:bg-[#4b3c23] dark:text-[#f3cf87]">{{ number_format($categories->total()) }} {{ __('longpage.results') }}</span>
        </div>
        <nav class="mt-7 flex flex-wrap items-center gap-3" aria-label="{{ __('longpage.filter_by_group') }}">
            <a href="{{ route('categories.index', $directoryParams) }}#category-directory" aria-current="{{ !$group ? 'page' : 'false' }}" class="rounded-full px-5 py-2.5 text-xs font-extrabold transition {{ !$group ? 'bg-[#123863] text-white' : 'border border-slate-200 bg-white text-slate-700 hover:border-[#d2a958] dark:border-slate-700 dark:bg-[#153255] dark:text-white' }}">{{ __('longpage.all_groups') }}</a>
            @foreach(\App\Models\Category::GROUPS as $slug=>$label)
                <a href="{{ route('categories.index', array_merge($directoryParams, ['group' => $slug])) }}#category-directory" aria-current="{{ $group === $slug ? 'page' : 'false' }}" class="rounded-full px-5 py-2.5 text-xs font-extrabold transition {{ $group===$slug ? 'bg-[#123863] text-white' : 'border border-slate-200 bg-white text-slate-700 hover:border-[#d2a958] dark:border-slate-700 dark:bg-[#153255] dark:text-white' }}">{{ $label }}</a>
            @endforeach
            @if($query !== '' || $group)
                <a href="{{ route('categories.index') }}#category-directory" class="rounded-full border border-amber-300 px-4 py-2.5 text-xs font-extrabold text-[#9b661d] transition hover:bg-amber-50 dark:border-amber-600 dark:text-amber-200 dark:hover:bg-amber-950">{{ __('longpage.clear_filters') }}</a>
            @endif
        </nav>
        @if($query)
            <p class="mt-4 text-sm text-slate-500">{{ __('longpage.search_results') }} <strong>{{ $query }}</strong></p>
        @endif
        <div class="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse($categories as $category)
                <a href="{{ route('categories.show',$category) }}" class="premium-card group flex h-full flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-[#ddb15e] hover:shadow-xl dark:border-slate-700 dark:bg-[#132e52]">
                    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="\App\Support\CategoryVisual::iconFor($category)" /></span>
                    <div class="premium-card-content flex items-center justify-between gap-3">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br from-[#123b70] to-[#0d2a50] text-xs font-black text-[#f4cf87]">{{ $category->code }}</span>
                        
                    </div>
                    <p class="premium-card-content mt-5 text-[11px] font-bold uppercase tracking-wider text-[#a47321] dark:text-[#f2c46e]">{{ \App\Models\Category::GROUPS[$category->group] ?? $category->group }}</p>
                    <h3 class="premium-card-content mt-2 break-words text-xl font-black text-[#12335d] transition-colors group-hover:text-[#946315] group-hover:underline dark:text-white dark:group-hover:text-[#f7cf85]">{{ $category->name }}</h3>
                    <p class="premium-card-content mt-3 line-clamp-4 flex-1 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $category->description }}</p>
                    <div class="premium-card-content mt-6 flex items-center justify-between border-t border-slate-100 pt-4 text-xs dark:border-slate-700">
                        <span class="font-bold text-slate-500">{{ number_format($category->published_courses_count) }} {{ __('longpage.courses') }}</span>
                        <strong class="text-[#a77728] dark:text-[#f6c56e]">{{ __('longpage.explore') }}</strong>
                    </div>
                </a>
            @empty
                <x-public-empty-state :title="__('longpage.empty_category_results')" icon="search" :href="route('categories.index')" :action="__('longpage.clear_filters')" />
            @endforelse
        </div>
        <div class="mt-8">{{ $categories->links() }}</div>
    </div>
</section>

<section class="bg-[#10335e] py-16 text-white">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><p class="text-xs font-black uppercase tracking-[.16em] text-[#edc16b]">{{ __('longpage.learn_your_way') }}</p>
                <h2 class="mt-3 text-3xl font-black">{{ $appearance['explore_heading'] }}</h2>
            </div>
             
        </div>
        <div class="mt-8 grid gap-4 md:grid-cols-3">
            @foreach([
                ['book-open',__('longpage.explore_course_title'),__('longpage.explore_course_description'),route('courses.index')],
                ['award',__('longpage.cefr_path_title'),__('longpage.cefr_path_description'),route('cefr.index')],
                ['school',__('longpage.teachers_path_title'),__('longpage.teachers_path_description'),route('instructors.index')],
            ] as [$icon,$heading,$description,$url])
                <a href="{{ $url }}" class="premium-card group premium-card-on-navy flex flex-col rounded-3xl border border-white/15 bg-white/10 p-7 hover:border-[#f0c672] hover:bg-white/15">
                    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="$icon" /></span>
                    <h3 class="mt-5 text-xl font-black">{{ $heading }}</h3><p class="mt-3 flex-1 text-sm leading-8 text-[#d1e2f2]">{{ $description }}</p>
                    <span class="mt-5 text-xs font-black text-[#f2ca7a]">{{ __('longpage.explore') }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

@if($featuredInstructors->isNotEmpty())
<section class="bg-white py-16 dark:bg-[#0c2646]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-4"><h2 class="text-3xl font-black text-[#10335e] dark:text-white">{{ __('longpage.featured_educators') }}</h2><a href="{{ route('instructors.index') }}" class="font-bold text-[#a77725] hover:underline">{{ __('longpage.view_instructors') }}</a></div>
        <div class="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse($featuredInstructors as $profile)
                <x-public-person-card :profile="$profile" kind="instructor"/>
            @empty
                <p class="col-span-full rounded-2xl border border-dashed border-slate-200 p-8 text-sm text-slate-500 dark:border-slate-700">{{ __('people.no_instructors') }}</p>
            @endforelse
        </div>
    </div>
</section>
@endif

@if($articles->isNotEmpty())
<section class="border-y border-slate-200 bg-[#f1f4f8] py-16 dark:border-slate-800 dark:bg-[#102c4c]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-4"><h2 class="text-3xl font-black text-[#10335e] dark:text-white">{{ __('longpage.study_resources') }}</h2><a href="{{ route('articles.index') }}" class="font-bold text-[#a77725] hover:underline">{{ __('longpage.view_resources') }}</a></div>
        <div class="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($articles as $article)
                <a href="{{ route('articles.show',$article->slug) }}" class="premium-card group flex flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm hover:border-[#dfb465] dark:border-slate-700 dark:bg-[#163656]">
                    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon name="newspaper" /></span><p class="premium-card-content text-xs font-black uppercase tracking-[.14em] text-[#ac782a]">{{ __('longpage.approved_article') }}</p>
                    <h3 class="mt-4 text-lg font-black text-[#11345e] group-hover:underline dark:text-white">{{ $article->title }}</h3><p class="mt-3 flex-1 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ $article->summary }}</p>
                    <span class="mt-5 text-xs font-black text-[#aa782c]">{{ __('frontend.read_article') }}</span>
                </a>
            @empty
                <p class="col-span-full rounded-2xl border border-dashed border-slate-300 p-8 text-sm text-slate-500 dark:border-slate-700">{{ __('frontend.no_articles') }}</p>
            @endforelse
        </div>
    </div>
</section>
@endif

<section class="bg-gradient-to-r from-[#0b2850] to-[#163f70] py-14 text-white">
    <div class="mx-auto flex max-w-[1600px] flex-col justify-between gap-7 px-4 sm:px-8 lg:flex-row lg:items-center lg:px-12">
        <div><h2 class="text-3xl font-black">{{ $appearance['cta_heading'] }}</h2><p class="mt-3 max-w-3xl text-sm leading-8 text-[#d4e3f0]">{{ __('longpage.category_cta_description') }}</p></div>
        <a href="{{ route('courses.index') }}" class="self-start rounded-xl bg-[#efc46d] px-7 py-3.5 text-sm font-black text-[#0e3059] hover:bg-[#ffde9b]">{{ __('longpage.browse_course_catalog') }}</a>
    </div>
</section>
@endsection
