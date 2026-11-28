@extends('layouts.public')
@section('title', __('public_links.not_found'))
@section('meta_description', __('public_links.not_found_help'))
@section('public_not_found', 'enabled')
@push('public_styles')
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="{{ asset('css/public-not-found.css') }}?v=1">
@endpush

@section('content')
<article class="public-not-found" aria-labelledby="not-found-heading">
    <div class="public-not-found__hero">
        <div class="public-not-found__copy">
            <h1 id="not-found-heading">{{ __('public_links.not_found') }}</h1>
            <p>{{ request()->is('specialized-exams/attempts/*', 'account/assessment-attempts/*', 'account/enrollments/*/exam-attempts/*')
                ? __('public_links.attempt_help')
                : __('public_links.not_found_help') }}</p>
            <a class="public-not-found__home" href="{{ route('home', ['lang' => app()->getLocale()]) }}">
                <x-workspace-icon name="home" class="h-5 w-5" />
                {{ __('public_links.back_home') }}
            </a>
        </div>
        <span class="public-not-found__number" aria-hidden="true">404</span>
    </div>
    <nav class="public-not-found__options" aria-label="{{ __('public_links.browse') }}">
        @foreach([
            ['courses.index', 'public_links.courses', 'book-open'],
            ['assessments.exams', 'detail.browse_exams', 'clipboard-check'],
            ['assessments.quizzes', 'detail.browse_quizzes', 'list-check'],
        ] as [$route, $label, $icon])
            <a href="{{ route($route, ['lang' => app()->getLocale()]) }}">
                <x-workspace-icon :name="$icon" class="h-5 w-5" />
                {{ __($label) }}
                <span aria-hidden="true">→</span>
            </a>
        @endforeach
    </nav>
</article>
@endsection
