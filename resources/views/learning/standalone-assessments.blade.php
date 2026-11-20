@extends('layouts.public')

@php
    $isQuiz = $kind === 'quiz';
    $title = $isQuiz ? 'Quick quizzes' : 'Standalone exams';
    $subtitle = $isQuiz ? 'Practice with short quizzes and get feedback as you go.' : 'Take published assessments on your own schedule.';
    $heroImage = $isQuiz ? 'quick-quizzes-hero.webp' : 'standalone-exams-hero.webp';
@endphp

@section('title', $title)

@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/assessment-catalog.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/catalog-card-grid.css') }}">
@endpush

@section('content')
    <section class="assessment-catalog-hero" aria-labelledby="assessment-catalog-title">
        <div class="assessment-catalog-hero__inner">
            <div class="assessment-catalog-hero__copy">
                <h1 id="assessment-catalog-title">{{ $title }}</h1>
                <p>{{ $subtitle }}</p>
            </div>
            <img class="assessment-catalog-hero__image" src="{{ asset('images/assessments/'.$heroImage) }}"
                width="1152" height="768" alt="" fetchpriority="high" decoding="async">
        </div>
    </section>

    <x-learning-section :id="$items->fragment()" :title="$isQuiz ? 'Available quizzes' : 'Available exams'" tone="white">
        <nav aria-label="Assessment catalogs" class="mb-5 flex flex-wrap gap-4 font-semibold">
            <a href="{{ route('assessments.exams') }}" @if(!$isQuiz) aria-current="page" @endif>Standalone exams</a>
            <a href="{{ route('assessments.quizzes') }}" @if($isQuiz) aria-current="page" @endif>Quick quizzes</a>
            <a href="{{ route('learning.exams') }}">Course assessments</a>
        </nav>
        <div class="assessment-catalog-grid">
            @forelse($items as $item)
                @php($quote = \App\Support\CoursePricing::quote($item->pricing))
                <x-learning-card :href="$item->publicUrl()" :title="$item->title" :icon="$isQuiz ? 'study-quiz' : 'study-exam'"
                    :image="$item->featured_kind === 'image' && $item->featuredMedia ? route('editor-media.show', $item->featuredMedia) : null" :image-alt="$item->featured_alt ?? ''"
                    :eyebrow="$item->type?->name" :meta="$item->time_limit.' min · '.ucfirst($quote['model'])" :action="$isQuiz ? 'View quiz' : 'View exam'" />
            @empty
                <p class="col-span-full">There are no published {{ $isQuiz ? 'quizzes' : 'exams' }} yet.</p>
            @endforelse
        </div>
        {{ $items->onEachSide(1)->links('learning.partials.assessment-catalog-pagination', [
            'label' => $isQuiz ? 'Quiz pages' : 'Exam pages',
            'itemLabel' => $isQuiz ? 'quizzes' : 'exams',
        ]) }}
    </x-learning-section>
@endsection
