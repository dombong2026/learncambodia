@extends('layouts.public')
@section('title', __('pages.home_title'))
@section('meta_description', __('pages.home_intro'))

@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/home.css') }}?v=2">
@endpush

@section('content')
@php
    $hero = \App\Support\Appearance::approved('home');
    $workspaceUser = auth()->user();
@endphp

<div class="lc-home">
    <section class="lc-home-hero" aria-labelledby="home-title">
        <div class="lc-home-wrap lc-home-hero-grid">
            <div class="lc-home-hero-copy">
                <h1 id="home-title">{{ $hero['hero_title'] }}</h1>
                @if(filled($hero['hero_intro']))
                    <p>{{ $hero['hero_intro'] }}</p>
                @endif
                <div class="lc-home-actions">
                    <a class="lc-home-button lc-home-button-primary" href="{{ route('courses.index') }}">{{ __('pages.browse_courses') }}</a>
                    <a class="lc-home-button lc-home-button-outline" href="{{ route('assessments.exams') }}">{{ __('pages.browse_exams') }}</a>
                </div>
            </div>
            <img class="lc-home-hero-image" src="{{ asset('images/home/hero.webp') }}" width="1024" height="576" alt="" fetchpriority="high" decoding="async">
        </div>
    </section>

    @if($featuredExams->isNotEmpty())
        <section id="practice-exams" class="lc-home-wrap lc-home-section" aria-labelledby="home-exams-title">
            <div class="lc-home-section-heading">
                <h2 id="home-exams-title">{{ __('pages.practice_exams') }}</h2>
                <a class="lc-home-text-link" href="{{ route('assessments.exams') }}">{{ __('pages.view_all_exams') }} <x-workspace-icon name="arrow-up-right" aria-hidden="true" /></a>
            </div>
            <div @class(['lc-home-cards', 'lc-home-cards-single' => $featuredExams->count() === 1])>
                @foreach($featuredExams as $exam)
                    @php($hasCover = $exam->featured_kind === 'image' && $exam->featuredMedia)
                    <a class="lc-home-card lc-home-exam" href="{{ $exam->publicUrl() }}">
                        <img src="{{ $hasCover ? route('editor-media.show', $exam->featuredMedia) : asset('images/home/exams.webp') }}"
                            width="1024" height="576" alt="{{ $hasCover ? ($exam->featured_alt ?? '') : '' }}" loading="lazy" decoding="async">
                        <div class="lc-home-card-body">
                            <h3>{{ $exam->title }}</h3>
                            <p>{{ trans_choice('detail.minutes', $exam->time_limit) }}</p>
                            <span class="lc-home-text-link">{{ __('pages.view_exam') }} <x-workspace-icon name="arrow-up-right" aria-hidden="true" /></span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($featuredCourses->isNotEmpty())
        <section class="lc-home-wrap lc-home-section" aria-labelledby="home-courses-title">
            <div class="lc-home-section-heading">
                <h2 id="home-courses-title">{{ __('pages.start_a_course') }}</h2>
                <a class="lc-home-text-link" href="{{ route('courses.index') }}">{{ __('pages.view_all') }} <x-workspace-icon name="arrow-up-right" aria-hidden="true" /></a>
            </div>
            <div @class(['lc-home-cards', 'lc-home-cards-single' => $featuredCourses->count() === 1])>
                @foreach($featuredCourses as $course)
                    @php($hasCover = $course->featured_kind === 'image' && $course->featuredMedia)
                    <a class="lc-home-card lc-home-course" href="{{ route('courses.show', $course->getRouteKey()) }}">
                        <img src="{{ $hasCover ? route('editor-media.show', $course->featuredMedia) : asset('images/home/courses.webp') }}"
                            width="1024" height="576" alt="{{ $hasCover ? ($course->featured_alt ?? '') : '' }}" loading="lazy" decoding="async">
                        <div class="lc-home-card-body">
                            <h3>{{ $course->title }}</h3>
                            <div class="lc-home-course-meta">
                                <x-course-price :course="$course" />
                                @if($course->cefr_level)<span>CEFR {{ $course->cefr_level }}</span>@endif
                            </div>
                            <span class="lc-home-text-link">{{ __('frontend.view_course') }} <x-workspace-icon name="chevron-right" aria-hidden="true" /></span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="lc-home-wrap lc-home-section lc-home-resources">
        <section class="lc-home-panel" aria-labelledby="home-cefr-title">
            <img src="{{ asset('images/home/levels.webp') }}" width="1024" height="576" alt="" loading="lazy" decoding="async">
            <div class="lc-home-panel-body">
                <div class="lc-home-section-heading">
                    <h2 id="home-cefr-title">{{ __('pages.cefr_guide') }}</h2>
                    <a class="lc-home-text-link" href="{{ route('cefr.index') }}">{{ __('pages.read_cefr') }} <x-workspace-icon name="arrow-up-right" aria-hidden="true" /></a>
                </div>
                <div class="lc-home-levels" data-home-levels>
                    @foreach(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as $level)
                        <a href="{{ route('cefr.index') }}#{{ $level }}">{{ $level }}</a>
                    @endforeach
                </div>
                <a class="lc-home-text-link" href="{{ route('categories.index') }}">{{ __('pages.explore_subjects') }} <x-workspace-icon name="arrow-up-right" aria-hidden="true" /></a>
            </div>
        </section>

        @if($latestArticles->isNotEmpty())
            <section class="lc-home-panel" aria-labelledby="home-articles-title">
                <img src="{{ asset('images/home/articles.webp') }}" width="1024" height="576" alt="" loading="lazy" decoding="async">
                <div class="lc-home-panel-body">
                    <div class="lc-home-section-heading">
                        <h2 id="home-articles-title">{{ __('pages.study_resources') }}</h2>
                        <a class="lc-home-text-link" href="{{ route('articles.index') }}">{{ __('pages.view_all') }} <x-workspace-icon name="arrow-up-right" aria-hidden="true" /></a>
                    </div>
                    <ul class="lc-home-articles">
                        @foreach($latestArticles as $article)
                            <li><a href="{{ route('articles.show', $article->getRouteKey()) }}">{{ $article->title }} <x-workspace-icon name="chevron-right" aria-hidden="true" /></a></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    </div>

    <section class="lc-home-wrap lc-home-educators" aria-labelledby="home-educators-title">
        <div class="lc-home-panel lc-home-educator-grid">
            <img src="{{ asset('images/home/educators.webp') }}" width="1024" height="576" alt="" loading="lazy" decoding="async">
            <div class="lc-home-panel-body lc-home-educator-body">
                <h2 id="home-educators-title">{{ __('pages.educator_workspace') }}</h2>
                <div class="lc-home-actions">
                    <a class="lc-home-text-link" href="{{ route($workspaceUser?->homeRouteName() ?? 'login') }}">
                        {{ $workspaceUser ? __('frontend.open_my_workspace') : __('pages.sign_in_to_workspace') }} <x-workspace-icon name="chevron-right" aria-hidden="true" />
                    </a>
                    <a class="lc-home-text-link" href="{{ route('instructors.index') }}">{{ __('pages.meet_instructors') }} <x-workspace-icon name="chevron-right" aria-hidden="true" /></a>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
