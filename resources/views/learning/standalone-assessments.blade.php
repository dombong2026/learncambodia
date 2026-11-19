@extends('layouts.public')

@php
    $isQuiz = $kind === 'quiz';
    $title = $isQuiz ? 'Quick quizzes' : 'Standalone exams';
@endphp

@section('title', $title)

@if($isQuiz)
    @push('public_styles')
        <link rel="stylesheet" href="{{ asset('css/quiz-catalog.css') }}?v=1">
    @endpush
@endif

@section('content')
    @if($isQuiz)
        <section class="quiz-catalog-hero" aria-labelledby="quiz-catalog-title">
            <div class="quiz-catalog-hero__inner">
                <div class="quiz-catalog-hero__copy">
                    <h1 id="quiz-catalog-title">{{ $title }}</h1>
                    <p>Practice with short quizzes and get feedback as you go.</p>
                </div>
                <img class="quiz-catalog-hero__image" src="{{ asset('images/assessments/quick-quizzes-hero.webp') }}"
                    width="1152" height="768" alt="" fetchpriority="high" decoding="async">
            </div>
        </section>
    @else
        <x-learning-hero :title="$title" subtitle="Take published assessments on your own schedule." icon="clipboard-check" />
    @endif

    <x-learning-section :id="$isQuiz ? 'quiz-results' : null" :title="$isQuiz ? 'Available quizzes' : 'Available exams'" tone="white">
        <nav aria-label="Assessment catalogs" class="mb-5 flex flex-wrap gap-4 font-semibold">
            <a href="{{ route('assessments.exams') }}" @if(!$isQuiz) aria-current="page" @endif>Standalone exams</a>
            <a href="{{ route('assessments.quizzes') }}" @if($isQuiz) aria-current="page" @endif>Quick quizzes</a>
            <a href="{{ route('learning.exams') }}">Course assessments</a>
        </nav>
        <div class="{{ $isQuiz ? 'quiz-catalog-grid' : 'grid gap-5 md:grid-cols-2 xl:grid-cols-3' }}">
            @forelse($items as $item)
                @php($quote = \App\Support\CoursePricing::quote($item->pricing))
                <x-learning-card :href="$item->publicUrl()" :title="$item->title" icon="clipboard-check"
                    :image="$item->featured_kind === 'image' && $item->featuredMedia ? route('editor-media.show', $item->featuredMedia) : null" :image-alt="$item->featured_alt ?? ''"
                    :eyebrow="$item->type?->name" :meta="$item->time_limit.' min · '.ucfirst($quote['model'])" :action="$isQuiz ? 'View quiz' : 'View exam'" />
            @empty
                <p>There are no published {{ $isQuiz ? 'quizzes' : 'exams' }} yet.</p>
            @endforelse
        </div>
        @if($isQuiz)
            {{ $items->onEachSide(1)->links('learning.partials.quiz-catalog-pagination') }}
        @else
            <div class="mt-5">{{ $items->links() }}</div>
        @endif
    </x-learning-section>
@endsection
