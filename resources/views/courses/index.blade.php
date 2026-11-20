@extends('layouts.public')
@section('title', __('frontend.courses_title'))
@section('meta_description', __('frontend.courses_description'))

@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/assessment-catalog.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/course-catalog.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/catalog-card-grid.css') }}">
@endpush

@section('content')
<section class="assessment-catalog-hero course-catalog-hero" aria-labelledby="course-catalog-title">
    <div class="assessment-catalog-hero__inner course-catalog-container">
        <div class="assessment-catalog-hero__copy">
            <h1 id="course-catalog-title">{{ __('pages.catalog_heading') }}</h1>
            <form method="GET" action="{{ route('courses.index') }}" role="search" class="mt-6 flex max-w-lg gap-2">
                <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
                <label for="catalog-hero-search" class="sr-only">{{ __('frontend.search_courses') }}</label>
                <input id="catalog-hero-search" name="q" type="search" maxlength="80" value="{{ $search }}" placeholder="{{ __('frontend.search_placeholder') }}" class="min-w-0 flex-1 rounded-xl border border-white/10 bg-white px-4 py-3 text-sm text-[#132e57] outline-none focus:ring-2 focus:ring-[#f3c268]">
                <button type="submit" class="rounded-xl bg-[#f1c16a] px-5 py-3 text-sm font-black text-[#102e55] hover:bg-[#ffda94]">{{ __('pages.search') }}</button>
            </form>
            <a class="course-catalog-hero__link" href="{{ route('categories.index') }}">{{ __('pages.explore_subjects') }} <span aria-hidden="true">→</span></a>
        </div>
        <img class="assessment-catalog-hero__image" src="{{ asset('images/courses/course-catalog-student.webp') }}"
            width="1152" height="768" alt="" fetchpriority="high" decoding="async">
    </div>
</section>

<div class="course-catalog-container course-catalog-body">
    <div class="course-catalog-layout">
        <aside data-course-filters class="self-start rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-[#142f51]">
            <div class="flex items-center justify-between"><h2 class="text-lg font-black text-[#10345f] dark:text-white">{{ __('pages.filters') }}</h2><a href="{{ route('courses.index') }}" class="text-xs font-bold text-[#895b1c] hover:underline dark:text-[#f3c36c]">{{ __('pages.clear') }}</a></div>
            <form method="GET" action="{{ route('courses.index') }}" class="mt-5 space-y-5">
                <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
                <div><label for="catalog-q" class="mb-2 block text-xs font-bold">{{ __('frontend.search_courses') }}</label><input id="catalog-q" name="q" type="search" maxlength="80" value="{{ $search }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-[#0a2749]"></div>
                <div><label for="catalog-group" class="mb-2 block text-xs font-bold">{{ __('pages.subject_group') }}</label>
                    <select id="catalog-group" name="group" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-[#0a2749]">
                        <option value="">{{ __('pages.all_subjects') }}</option>
                        @foreach(\App\Models\Category::GROUPS as $key=>$name)<option value="{{ $key }}" @selected($group===$key)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div><label for="catalog-category" class="mb-2 block text-xs font-bold">{{ __('frontend.category') }}</label>
                    <select id="catalog-category" name="category" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-[#0a2749]">
                        <option value="">{{ __('frontend.all_categories') }}</option>
                        @foreach($categories as $item)<option value="{{ $item->slug }}" @selected($category===$item->slug)>{{ $item->name }}</option>@endforeach
                    </select>
                </div>
                <div><label for="catalog-level" class="mb-2 block text-xs font-bold">{{ __('frontend.cefr') }}</label>
                    <select id="catalog-level" name="level" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-[#0a2749]">
                        <option value="">{{ __('frontend.any_level') }}</option>
                        @foreach(['A1','A2','B1','B2','C1','C2'] as $lvl)<option value="{{ $lvl }}" @selected($level===$lvl)>{{ $lvl }}</option>@endforeach
                    </select>
                </div>
                <div><label for="catalog-mode" class="mb-2 block text-xs font-bold">{{ __('pages.delivery_mode') }}</label>
                    <select id="catalog-mode" name="mode" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-[#0a2749]">
                        <option value="">{{ __('pages.all_modes') }}</option>
                        @foreach(['online','onsite','hybrid'] as $choice)<option value="{{ $choice }}" @selected($mode===$choice)>{{ __('pages.mode_'.$choice) }}</option>@endforeach
                    </select>
                </div>
                <button type="submit" class="w-full rounded-xl bg-[#113966] px-5 py-3 text-xs font-black text-white hover:bg-[#245487]">{{ __('frontend.apply') }}</button>
            </form>
        </aside>


        <section id="course-results" aria-labelledby="course-results-title" class="min-w-0 scroll-mt-32">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="course-results-title" class="course-catalog-heading">{{ __('pages.course_directory') }}</h2>
                <span class="rounded-full bg-[#f7ecd5] px-3 py-1.5 text-xs font-bold text-[#835816] dark:bg-[#473a20] dark:text-[#f8d68a]">
                    {{ trans_choice('frontend.catalog_count', $courses->total()) }}
                </span>
            </div>
            <div class="course-catalog-grid">
                @forelse($courses as $course)
                    <x-public-course-card :course="$course" :show-summary="false" />
                @empty
                    <div class="col-span-full">
                        <x-public-empty-state icon="book-open" :title="__('frontend.no_courses')" :href="route('courses.index')" :action="__('pages.clear')" />
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="course-catalog-guides">
        <section id="course-levels" data-course-cefr-section class="course-catalog-panel" aria-labelledby="course-levels-title">
            <div class="course-catalog-panel__header">
                <div>
                    <h2 id="course-levels-title" class="course-catalog-heading">{{ __('pages.study_levels') }}</h2>
                    <p class="course-catalog-description">{{ __('pages.cefr_guide_note') }}</p>
                </div>
                <a href="{{ route('cefr.index') }}" class="course-catalog-link">{{ __('pages.read_cefr') }} <span aria-hidden="true">→</span></a>
            </div>
            <div data-course-cefr-row role="group" aria-label="{{ __('pages.study_levels') }}" class="mt-5 flex w-full min-w-0 max-w-full flex-nowrap gap-3 overflow-x-auto overscroll-x-contain snap-x snap-mandatory pb-2">
                @foreach(['A1','A2','B1','B2','C1','C2'] as $lvl)
                    <a data-course-cefr-level="{{ $lvl }}" href="{{ route('courses.index',['level'=>$lvl]) }}" class="premium-card course-catalog-level min-w-[5.75rem] flex-1 shrink-0 snap-start">{{ $lvl }}</a>
                @endforeach
            </div>
        </section>

        <div class="course-catalog-guides__columns">
            <section id="course-instructors" class="course-catalog-panel" aria-labelledby="course-instructors-title">
                <div class="course-catalog-panel__header">
                    <h2 id="course-instructors-title" class="course-catalog-heading">{{ __('pages.featured_instructors') }}</h2>
                    <a href="{{ route('instructors.index') }}" class="course-catalog-link">{{ __('pages.meet_teachers') }} <span aria-hidden="true">→</span></a>
                </div>
                @if($featuredInstructors->isNotEmpty())
                    <div class="course-catalog-instructors">
                        @foreach($featuredInstructors as $profile)
                            <a href="{{ route('instructors.show', $profile) }}" class="course-catalog-profile">
                                <span class="course-catalog-profile__initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($profile->display_name, 0, 1)) }}</span>
                                <span class="min-w-0">
                                    <strong class="block break-words text-sm">{{ $profile->display_name }}</strong>
                                    @if($profile->headline)<span class="course-catalog-description block">{{ $profile->headline }}</span>@endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="course-catalog-instructor-empty">
                        <x-workspace-icon name="school" class="h-7 w-7 shrink-0" />
                        <p>{{ __('people.no_instructors') }}</p>
                    </div>
                @endif
            </section>

            <section id="course-faq" class="course-catalog-panel" aria-labelledby="course-faq-title">
                <h2 id="course-faq-title" class="course-catalog-heading">{{ __('pages.faq') }}</h2>
                <div class="course-catalog-faq">
                    @foreach(['one', 'two', 'three'] as $question)
                        <details>
                            <summary>
                                {{ __('pages.faq_'.$question) }}
                                <x-workspace-icon name="chevron-down" class="h-4 w-4 shrink-0" />
                            </summary>
                            <p>{{ __('pages.faq_'.$question.'_answer') }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        </div>

        <section id="course-process" class="course-catalog-panel" aria-labelledby="course-process-title">
            <h2 id="course-process-title" class="course-catalog-heading">{{ __('pages.academic_process') }}</h2>
            <ol class="course-catalog-steps">
                @foreach([['compass', 'discover'], ['user-check', 'approval'], ['book-open', 'study']] as [$icon, $step])
                    <li>
                        <span class="course-catalog-step-icon" aria-hidden="true"><x-workspace-icon :name="$icon" class="h-5 w-5" /></span>
                        <h3>{{ __('pages.step_'.$step) }}</h3>
                        <p>{{ __('pages.step_'.$step.'_desc') }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    {{ $courses->onEachSide(1)->links('learning.partials.assessment-catalog-pagination', [
        'label' => __('frontend.course_pages'),
        'itemLabel' => __('frontend.result_count'),
    ]) }}
</div>
@endsection
