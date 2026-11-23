@extends('layouts.public')
@section('title', __('pages.home_title'))
@section('meta_description', __('pages.home_intro'))

@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/home.css') }}?v=4">
@endpush

@section('content')
@php
    $hero = \App\Support\Appearance::approved('home');
    $workspaceUser = auth()->user();
@endphp

<div class="lc-home">
    @if(($hero['hero_mode'] ?? 'default') === 'course_player' && $heroCourses->isNotEmpty())
        @include('partials.home-course-player-hero')
    @else
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
            <div class="lc-home-hero-art">
                <img class="lc-home-hero-image" src="{{ asset('images/home/hero-cutout.webp') }}" width="1320" height="743" alt="" fetchpriority="high" decoding="async">
            </div>
        </div>
    </section>
    @endif

@include('promo-player.placement', ['placement' => 'homepage'])

    <div class="lc-home-wrap lc-home-sections">
        @if($featuredExams->isNotEmpty())
            <x-home-section id="practice-exams" :title="__('pages.practice_exams')" image="exams"
                :items="$featuredExams->map(fn ($exam) => ['title' => $exam->title, 'url' => $exam->publicUrl()])"
                :catalog-url="route('assessments.exams')" :catalog-label="__('pages.view_all_exams')" />
        @endif

        @if($featuredQuizzes->isNotEmpty())
            <x-home-section id="practice-quizzes" :title="__('pages.practice_quizzes')" image="quizzes"
                :items="$featuredQuizzes->map(fn ($quiz) => ['title' => $quiz->title, 'url' => $quiz->publicUrl()])"
                :catalog-url="route('assessments.quizzes')" :catalog-label="__('pages.view_all_quizzes')" />
        @endif

        @if($featuredCourses->isNotEmpty())
            <x-home-section id="home-courses" :title="__('pages.start_a_course')" image="courses"
                :items="$featuredCourses->map(fn ($course) => ['title' => $course->title, 'url' => route('courses.show', $course->getRouteKey())])"
                :catalog-url="route('courses.index')" :catalog-label="__('pages.view_all_courses')" />
        @endif

        <x-home-section id="home-cefr" :title="__('pages.cefr_guide')" image="levels"
            :items="[
                ['title' => __('pages.read_cefr'), 'url' => route('cefr.index')],
                ['title' => __('pages.explore_subjects'), 'url' => route('categories.index')],
            ]">
            <div class="lc-home-levels" data-home-levels>
                @foreach(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as $level)
                    <a href="{{ route('cefr.index') }}#{{ $level }}">{{ $level }}</a>
                @endforeach
            </div>
        </x-home-section>

        @if($latestArticles->isNotEmpty())
            <x-home-section id="home-articles" :title="__('pages.study_resources')" image="articles"
                :items="$latestArticles->map(fn ($article) => ['title' => $article->title, 'url' => route('articles.show', $article->getRouteKey())])"
                :catalog-url="route('articles.index')" :catalog-label="__('pages.view_all_articles')" />
        @endif

        <x-home-section id="home-educators" :title="__('pages.educator_workspace')" image="educators"
            :items="[
                [
                    'title' => $workspaceUser ? __('frontend.open_my_workspace') : __('pages.sign_in_to_workspace'),
                    'url' => route($workspaceUser?->homeRouteName() ?? 'login'),
                ],
                ['title' => __('pages.meet_instructors'), 'url' => route('instructors.index')],
            ]" />
    </div>
</div>
@endsection
