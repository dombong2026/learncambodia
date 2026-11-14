@extends('layouts.public')
@section('title', __('frontend.courses_title'))
@section('meta_description', __('frontend.courses_description'))
@section('content')
<section class="overflow-hidden bg-gradient-to-br from-[#0a2853] via-[#153e6e] to-[#0b2d59] text-white">
    <div class="mx-auto grid max-w-7xl items-center gap-6 px-4 py-12 sm:px-6 lg:grid-cols-[1.3fr_.7fr] lg:px-8">
        <div>
            <p class="text-xs font-black uppercase tracking-[.14em] text-[#f1c16c]">{{ __('pages.catalog_eyebrow') }}</p>
            <h1 class="mt-3 text-4xl font-black leading-tight sm:text-5xl">{{ __('pages.catalog_heading') }}</h1>
            <p class="mt-4 max-w-2xl text-sm leading-8 text-[#d2e3f2]">{{ __('frontend.courses_description') }}</p>
            <form method="GET" action="{{ route('courses.index') }}" role="search" class="mt-6 flex max-w-xl gap-2">
                <label for="catalog-hero-search" class="sr-only">{{ __('frontend.search_courses') }}</label>
                <input id="catalog-hero-search" name="q" type="search" maxlength="80" value="{{ $search }}" placeholder="{{ __('frontend.search_placeholder') }}" class="min-w-0 flex-1 rounded-xl border border-white/10 bg-white px-4 py-3 text-sm text-[#132e57] outline-none focus:ring-2 focus:ring-[#f3c268]">
                <button type="submit" class="rounded-xl bg-[#f1c16a] px-5 py-3 text-xs font-black text-[#102e55] hover:bg-[#ffda94]">{{ __('pages.search') }}</button>
            </form>
        </div>
        <div class="grid grid-cols-3 gap-3">
            @foreach([['compass',__('pages.explore_subjects'),route('categories.index')],['award',__('pages.study_levels'),route('cefr.index')],['school',__('pages.meet_teachers'),route('instructors.index')]] as $tile)
                <a href="{{ $tile[2] }}" class="flex flex-col items-center rounded-2xl border border-white/20 bg-white/10 p-4 text-center transition hover:border-[#f2c26d] hover:bg-white/20">
                    <x-workspace-icon :name="$tile[0]" class="h-8 w-8 text-[#f7cb7c]" /><span class="mt-3 text-[11px] font-black leading-5">{{ $tile[1] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<div class="mx-auto grid max-w-7xl gap-6 px-4 py-10 sm:px-6 lg:grid-cols-[230px_minmax(0,1fr)] lg:px-8 xl:grid-cols-[210px_minmax(0,1.2fr)_minmax(245px,.8fr)]">
    <aside class="self-start rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-[#142f51] lg:sticky lg:top-32">
        <div class="flex items-center justify-between"><h2 class="text-lg font-black text-[#10345f] dark:text-white">{{ __('pages.filters') }}</h2><a href="{{ route('courses.index') }}" class="text-xs font-bold text-[#ab7627] hover:underline">{{ __('pages.clear') }}</a></div>
        <form method="GET" action="{{ route('courses.index') }}" class="mt-5 space-y-5">
            <div><label for="catalog-q" class="mb-2 block text-xs font-bold">{{ __('frontend.search_courses') }}</label><input id="catalog-q" name="q" type="search" maxlength="80" value="{{ $search }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-[#0a2749]"></div>
            <div><label for="catalog-group" class="mb-2 block text-xs font-bold">{{ __('pages.subject_group') }}</label>
                <select id="catalog-group" name="group" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-[#0a2749]">
                    <option value="">{{ __('pages.all_subjects') }}</option>
                    @foreach(\App\Models\Category::GROUPS as $key=>$name)<option value="{{ $key }}" @selected($group===$key)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div><label for="catalog-category" class="mb-2 block text-xs font-bold">{{ __('frontend.category') }}</label>
                <select id="catalog-category" name="category" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-[#0a2749]">
                    <option value="">{{ __('frontend.all_categories') }}</option>
                    @foreach($categories as $item)<option value="{{ $item->slug }}" @selected($category===$item->slug)>{{ $item->name }}</option>@endforeach
                </select>
            </div>
            <div><label for="catalog-level" class="mb-2 block text-xs font-bold">{{ __('frontend.cefr') }}</label>
                <select id="catalog-level" name="level" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-[#0a2749]">
                    <option value="">{{ __('frontend.any_level') }}</option>
                    @foreach(['A1','A2','B1','B2','C1','C2'] as $lvl)<option value="{{ $lvl }}" @selected($level===$lvl)>{{ $lvl }}</option>@endforeach
                </select>
            </div>
            <div><label for="catalog-mode" class="mb-2 block text-xs font-bold">{{ __('pages.delivery_mode') }}</label>
                <select id="catalog-mode" name="mode" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-[#0a2749]">
                    <option value="">{{ __('pages.all_modes') }}</option>
                    @foreach(['online','onsite','hybrid'] as $choice)<option value="{{ $choice }}" @selected($mode===$choice)>{{ __('pages.mode_'.$choice) }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="w-full rounded-xl bg-[#113966] px-5 py-3 text-xs font-black text-white hover:bg-[#245487]">{{ __('frontend.apply') }}</button>
        </form>
    </aside>

    <main class="min-w-0">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-black text-[#0f335e] dark:text-white">{{ __('pages.course_directory') }}</h2>
            <span class="rounded-full bg-[#f7ecd5] px-3 py-1.5 text-xs font-bold text-[#835816] dark:bg-[#473a20] dark:text-[#f8d68a]">{{ number_format($courses->total()) }} {{ __('frontend.result_count') }}</span>
        </div>
        <p class="mt-2 text-xs leading-6 text-slate-500">{{ __('pages.course_catalog_note') }}</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-2">
            @forelse($courses as $course)
                <x-public-course-card :course="$course" />
            @empty
                <x-public-empty-state icon="book-open" :title="__('frontend.no_courses')" :href="route('courses.index')" :action="__('pages.clear')" />
            @endforelse
        </div>
        <div class="mt-8">{{ $courses->links() }}</div>
        <section class="mt-10 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#142f51]">
            <h2 class="text-lg font-black text-[#10345f] dark:text-white">{{ __('pages.academic_process') }}</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                @foreach([['compass',__('pages.step_discover')],['user-check',__('pages.step_approval')],['book-open',__('pages.step_study')]] as [$icon,$title])
                    <div class="rounded-2xl bg-slate-50 p-4 text-center dark:bg-[#1e3d61]"><x-workspace-icon :name="$icon" class="mx-auto h-7 w-7 text-[#c69438]" /><p class="mt-3 text-xs font-bold">{{ $title }}</p></div>
                @endforeach
            </div>
        </section>
    </main>
    <aside class="space-y-5 lg:col-span-2 xl:col-span-1">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#142f51]">
            <h2 class="text-lg font-black text-[#0d315c] dark:text-white">{{ __('pages.study_levels') }}</h2>
            <p class="mt-2 text-xs leading-6 text-slate-500">{{ __('pages.cefr_guide_note') }}</p>
            <div class="mt-4 grid grid-cols-3 gap-2">
                @foreach(['A1','A2','B1','B2','C1','C2'] as $lvl)
                    <a href="{{ route('courses.index',['level'=>$lvl]) }}" class="rounded-xl border border-slate-200 bg-[#f8fafc] p-3 text-center text-sm font-black text-[#173b67] transition hover:border-[#d6a84a] hover:bg-[#fff2d5] dark:border-[#36516e] dark:bg-[#234469] dark:text-white">{{ $lvl }}</a>
                @endforeach
            </div>
            <a href="{{ route('cefr.index') }}" class="mt-4 block text-center text-xs font-bold text-[#b27e2e] hover:underline">{{ __('pages.read_cefr') }} →</a>
        </section>
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#142f51]">
            <h2 class="text-lg font-black text-[#0d315c] dark:text-white">{{ __('pages.featured_instructors') }}</h2>
            <div class="mt-4 space-y-3">
                @forelse($featuredInstructors as $profile)
                    <a href="{{ route('instructors.show',$profile) }}" class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 transition hover:border-[#d5a84d] dark:border-slate-700">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#173f70] font-black text-[#e8be69]" aria-hidden="true">{{ mb_strtoupper(mb_substr($profile->display_name,0,1)) }}</span>
                        <span class="min-w-0"><strong class="block truncate text-xs">{{ $profile->display_name }}</strong><small class="mt-1 block truncate text-[11px] text-slate-500">{{ $profile->headline }}</small></span>
                    </a>
                @empty
                    <p class="text-xs leading-7 text-slate-500">{{ __('people.no_instructors') }}</p>
                @endforelse
            </div>
            <a href="{{ route('instructors.index') }}" class="mt-4 block rounded-xl bg-[#133967] px-4 py-3 text-center text-xs font-bold text-white">{{ __('pages.meet_teachers') }}</a>
        </section>
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-[#142f51]">
            <h2 class="text-lg font-black text-[#0d315c] dark:text-white">{{ __('pages.faq') }}</h2>
            <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-700">
                @foreach([['pages.faq_one','pages.faq_one_answer'],['pages.faq_two','pages.faq_two_answer'],['pages.faq_three','pages.faq_three_answer']] as [$q,$a])
                    <details class="group py-3"><summary class="flex cursor-pointer items-center justify-between gap-2 text-xs font-bold">{{ __($q) }}<x-workspace-icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 group-open:rotate-180"/></summary><p class="mt-3 text-xs leading-7 text-slate-500 dark:text-slate-300">{{ __($a) }}</p></details>
                @endforeach
            </div>
        </section>
    </aside>
</div>
@endsection
