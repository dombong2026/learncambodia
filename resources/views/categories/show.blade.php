@extends('layouts.public')
@section('title', $category->name)
@section('meta_description', $category->description)
@section('content')
@php $appearance=\App\Support\Appearance::approved('single_category'); @endphp
<section class="relative overflow-hidden bg-gradient-to-br from-[#08244b] via-[#143b6c] to-[#0d2d58] text-white">
    <div class="pointer-events-none absolute -bottom-40 right-10 h-80 w-80 rounded-full border-[60px] border-[#e5ae4c]/15" aria-hidden="true"></div>
    <div class="mx-auto max-w-[1600px] px-4 py-14 sm:px-8 lg:px-12 lg:py-20">
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-xs text-[#c7dbf1]">
            <a href="{{ route('home') }}" class="hover:underline">{{ __('longpage.home') }}</a><span>/</span>
            <a href="{{ route('categories.index') }}" class="hover:underline">{{ __('longpage.categories') }}</a><span>/</span>
            <span aria-current="page" class="font-bold text-[#f6ce7b]">{{ $category->name }}</span>
        </nav>
        <div class="mt-10 grid items-center gap-10 lg:grid-cols-[1.25fr_.75fr]">
            <div>
                <p class="text-xs font-black uppercase tracking-[.16em] text-[#f0bd66]">{{ $appearance['eyebrow'] }} · {{ \App\Models\Category::GROUPS[$category->group] ?? $category->group }}</p>
                <h1 class="mt-4 max-w-4xl break-words text-4xl font-black leading-tight sm:text-5xl xl:text-6xl">{{ $category->name }}</h1>
                <p class="mt-6 max-w-3xl text-base leading-9 text-[#d9e5f2]">{{ $category->description }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="#category-courses" class="rounded-xl bg-[#eebd63] px-6 py-3 text-sm font-black text-[#11345d] hover:bg-[#ffdc95]">{{ __('longpage.view_courses') }}</a>
                    <a href="{{ route('categories.index') }}" class="rounded-xl border border-white/30 px-6 py-3 text-sm font-bold hover:bg-white/10">{{ __('longpage.all_categories') }}</a>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                @foreach([
                    ['book-open',$publishedCourseCount,__('longpage.published_courses')],
                    ['tags',1,__('longpage.current_pathway')],
                    ['school',$featuredInstructors->count(),__('longpage.public_educators')],
                    ['award',$cefrLevelCount,__('longpage.cefr_levels')],
                ] as [$icon,$value,$label])
                    <div class="premium-card premium-card-on-navy min-h-36 rounded-2xl border border-white/20 bg-white/10 p-5">
                        <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="$icon" /></span>
                        <p class="premium-card-content mt-8 text-4xl font-black tabular-nums" title="{{ number_format($value) }}">{{ \App\Support\DashboardNumber::compact($value) }}</p>
                        <p class="premium-card-content mt-2 text-xs font-bold text-[#d2e2f1]">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<section id="category-courses" class="scroll-mt-28 bg-[#f5f7fa] py-16 dark:bg-[#0d2342]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><p class="text-xs font-black uppercase tracking-widest text-[#b17e27]">{{ __('longpage.education_pathway') }}</p>
                <h2 class="mt-2 text-3xl font-black text-[#12345f] dark:text-white">{{ $appearance['course_heading'] }}</h2>
                <p class="mt-3 max-w-3xl text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __('longpage.published_course_notice') }}</p>
            </div>
            <a href="{{ route('courses.index',['category'=>$category->slug]) }}" class="rounded-xl border border-[#dbb264] px-5 py-3 text-sm font-bold text-[#ad7722] dark:text-[#f6ca75]">{{ __('longpage.all_courses_in_category') }}</a>
        </div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse($courses as $course)
                <x-public-course-card :course="$course"/>
            @empty
                <x-public-empty-state icon="book-open" :title="__('longpage.no_approved_courses')" :description="__('longpage.no_approved_courses_note')" :href="route('courses.index')" :action="__('longpage.browse_course_catalog')" />
            @endforelse
        </div>
        <div class="mt-8">{{ $courses->links() }}</div>
    </div>
</section>

<section class="border-y border-slate-200 bg-white py-16 dark:border-slate-800 dark:bg-[#102b4a]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <p class="text-xs font-black uppercase tracking-[.16em] text-[#a9782a]">{{ __('longpage.study_preparation') }}</p>
        <h2 class="mt-2 text-3xl font-black text-[#12345f] dark:text-white">{{ $appearance['resources_heading'] }}</h2>
        <p class="mt-4 max-w-4xl text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __('longpage.resource_guidance') }}</p>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            @foreach([
                ['book-open',__('longpage.course_plans'),__('longpage.course_plans_note'),route('courses.index',['category'=>$category->slug])],
                ['award',__('longpage.cefr_reference'),__('longpage.cefr_reference_note'),route('cefr.index')],
                ['newspaper',__('longpage.reading_resources'),__('longpage.reading_resources_note'),route('articles.index')],
            ] as [$icon,$title,$note,$url])
                <a href="{{ $url }}" class="premium-card group rounded-3xl border border-slate-200 bg-[#f8fafc] p-7 transition hover:border-[#e1b662] hover:shadow-lg dark:border-slate-700 dark:bg-[#183657]">
                    <span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon :name="$icon" /></span>
                    <h3 class="premium-card-content mt-20 text-xl font-black text-[#12345f] group-hover:underline dark:text-white">{{ $title }}</h3>
                    <p class="premium-card-content mt-3 text-sm leading-8 text-slate-500 dark:text-slate-300">{{ $note }}</p>
                    <p class="premium-card-content mt-6 text-xs font-black text-[#b27e2b]">{{ __('longpage.explore') }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>

@if($featuredInstructors->isNotEmpty())
<section class="bg-[#0d315e] py-16 text-white">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-5"><div>
            <p class="text-xs font-black uppercase tracking-[.16em] text-[#f1c675]">{{ __('longpage.opt_in_people') }}</p>
            <h2 class="mt-3 text-3xl font-black">{{ $appearance['teachers_heading'] }}</h2>
            <p class="mt-3 max-w-4xl text-sm leading-8 text-[#cadbec]">{{ __('longpage.instructor_privacy_notice') }}</p>
        </div><a href="{{ route('instructors.index') }}" class="rounded-xl bg-[#efc26a] px-5 py-3 text-sm font-black text-[#0e315d]">{{ __('longpage.view_instructors') }}</a></div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse($featuredInstructors as $profile)
                <x-public-person-card :profile="$profile" kind="instructor"/>
            @empty
                <p class="col-span-full rounded-2xl border border-white/20 bg-white/10 p-7 text-sm text-[#d7e5f3]">{{ __('longpage.no_public_teachers') }}</p>
            @endforelse
        </div>
    </div>
</section>
@endif

@if($relatedCategories->isNotEmpty())
<section class="bg-white py-16 dark:bg-[#0c2847]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-5"><h2 class="text-3xl font-black text-[#12345f] dark:text-white">{{ $appearance['related_heading'] }}</h2>
            <a href="{{ route('categories.index',['group'=>$category->group]) }}" class="text-sm font-bold text-[#ab792a] hover:underline">{{ __('longpage.all_related_categories') }}</a></div>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($relatedCategories as $related)
                <a href="{{ route('categories.show',$related) }}" class="group flex items-center gap-4 rounded-2xl border border-slate-200 bg-[#f7f9fc] p-5 transition hover:-translate-y-0.5 hover:border-[#d6ad63] hover:shadow-md dark:border-slate-700 dark:bg-[#183754]">
                    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-xl bg-[#133766] text-xs font-black text-[#edc06b]">{{ $related->code }}</span>
                    <div class="min-w-0 flex-1"><h3 class="break-words text-sm font-black text-[#12355e] group-hover:underline dark:text-white">{{ $related->name }}</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">{{ \App\Models\Category::GROUPS[$related->group] ?? $related->group }}</p></div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($articles->isNotEmpty())
<section class="border-y border-slate-200 bg-[#f1f4f9] py-16 dark:border-slate-800 dark:bg-[#102e50]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-5"><h2 class="text-3xl font-black text-[#10325a] dark:text-white">{{ __('longpage.latest_articles') }}</h2>
            <a href="{{ route('articles.index') }}" class="text-sm font-black text-[#b17b22]">{{ __('longpage.view_resources') }}</a></div>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            @forelse($articles as $article)
                <a href="{{ route('articles.show',$article->slug) }}" class="premium-card group flex flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm hover:border-[#dcb25e] dark:border-slate-700 dark:bg-[#183b60]"><span class="premium-corner-icon" aria-hidden="true"><x-workspace-icon name="newspaper" /></span>
                    <p class="text-xs font-extrabold uppercase tracking-widest text-[#ad7624]">{{ __('longpage.approved_article') }}</p>
                    <h3 class="mt-4 text-xl font-black text-[#12345e] group-hover:underline dark:text-white">{{ $article->title }}</h3>
                    <p class="mt-3 flex-1 text-sm leading-8 text-slate-500 dark:text-slate-300">{{ $article->summary }}</p>
                    <span class="mt-5 text-xs font-black text-[#b0812d]">{{ __('frontend.read_article') }}</span>
                </a>
            @empty
                <p class="col-span-full rounded-2xl border border-dashed border-slate-300 p-8 text-sm text-slate-500 dark:border-slate-700">{{ __('frontend.no_articles') }}</p>
            @endforelse
        </div>
    </div>
</section>
@endif

<section class="bg-gradient-to-br from-[#092651] to-[#153e6f] py-14 text-white">
    <div class="mx-auto flex max-w-[1600px] flex-col justify-between gap-6 px-4 sm:px-8 lg:flex-row lg:items-center lg:px-12">
        <div><h2 class="text-3xl font-black">{{ $appearance['cta_heading'] }}</h2><p class="mt-3 max-w-3xl text-sm leading-8 text-[#d6e6f5]">{{ __('longpage.enrollment_reminder') }}</p></div>
        <a href="{{ route('courses.index',['category'=>$category->slug]) }}" class="self-start rounded-xl bg-[#f0c36a] px-7 py-3.5 text-sm font-black text-[#102e56] hover:bg-[#ffdd9b]">{{ __('longpage.view_courses') }}</a>
    </div>
</section>
@endsection
