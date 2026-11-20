@extends('layouts.public')
@php($isQuiz = ($attempt->snapshot['kind'] ?? $exam->kind) === 'quiz')
@section('title', $attempt->snapshot['title'] ?? $exam->title)
@section('public_robots', 'noindex,nofollow')
@section('study_catalog', $isQuiz ? 'assessments.quizzes' : 'assessments.exams')

@push('public_styles')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite('resources/js/workspace.ts')
    <link rel="stylesheet" href="{{ asset('css/specialized-exam.css') }}?v=3">
    @if(isset($attempt) && $attempt->submitted_at)
        <link rel="stylesheet" href="{{ asset('css/assessment-results.css') }}">
    @endif
@endpush

@section('content')
<div class="specialized-exam">
    <div class="specialized-exam-shell{{ isset($attempt) && $attempt->submitted_at ? ' specialized-exam-shell--result' : '' }}">
        <h1>{{ $attempt->snapshot['title'] ?? $exam->title }}</h1>
        @if(session('status'))
            <p class="specialized-exam-status" role="status">{{ session('status') }}</p>
        @endif
        @if($errors->any())
            <ul class="specialized-exam-status" role="alert">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif

        @if(!isset($attempt))
            <section class="specialized-exam-card">
                <p>
                    {{ __('specialized_exam.questions', ['count' => count($exam->questions ?? [])]) }} ·
                    {{ trans_choice('detail.minutes', $exam->time_limit) }} ·
                    @if($isQuiz){{ trans_choice('detail.attempts', $exam->attempt_limit) }} · @endif
                    {{ __('detail.passing_score', ['score' => $exam->passing_percent]) }}
                </p>
                <p>{{ __($isQuiz ? 'specialized_exam.quiz_intro' : 'specialized_exam.intro') }}</p>
                @if($active)
                    <a class="specialized-exam-action" href="{{ route($active->resultRoute(), $active) }}">{{ __($isQuiz ? 'specialized_exam.quiz_resume' : 'specialized_exam.resume') }}</a>
                @elseif($history->count() < $exam->attempt_limit)
                    <form method="post" action="{{ route('specialized-exams.start', $exam) }}">
                        @csrf
                        <button class="specialized-exam-action">{{ __($isQuiz ? 'specialized_exam.quiz_start' : 'specialized_exam.start') }}</button>
                    </form>
                @else
                    <p>{{ __('specialized_exam.limit') }}</p>
                @endif
            </section>
            @if($history->isNotEmpty())
                <section class="specialized-exam-card">
                    <h2>{{ __('specialized_exam.history') }}</h2>
                    <ul>
                        @foreach($history as $previous)
                            <li><a href="{{ route($previous->resultRoute(), $previous) }}">{{ __('specialized_exam.attempt', ['number' => $history->count() - $loop->index]) }}</a>
                                · {{ $previous->submitted_at ? __($isQuiz ? 'specialized_exam.quiz_submitted' : 'specialized_exam.submitted') : ($previous->expires_at->isFuture() ? __('specialized_exam.in_progress') : __('specialized_exam.expired')) }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @elseif($attempt->submitted_at)
            <x-assessment-result-layout :attempt="$attempt">
                <h3 class="assessment-result-heading">{{ __($isQuiz ? 'specialized_exam.quiz_submitted' : 'specialized_exam.submitted') }}</h3>
                @if($attempt->score !== null)
                    <p><strong class="assessment-result-score">{{ $attempt->score }}%</strong> · {{ __($attempt->passed ? 'specialized_exam.passed' : 'specialized_exam.below_pass') }}</p>
                    <p>{{ __('detail.passing_score', ['score' => $attempt->snapshot['passing_percent']]) }}</p>
                @else
                    <p>{{ __(collect($attempt->snapshot['questions'])->contains(\App\Support\AssessmentQuestions::manual(...))
                        ? 'specialized_exam.awaiting_grade' : 'specialized_exam.recorded') }}</p>
                @endif
                @if($owned && $student && $attempt->passed)
                    <x-certificate-award :certificate="$certificate" :claim="route('account.certificates.claim-assessment', $attempt)" />
                @endif
                @if($isQuiz)
                    <p class="specialized-exam-status">{{ __($owned ? 'specialized_exam.quiz_account_saved' : 'specialized_exam.quiz_browser_saved') }}</p>
                @elseif($owned)
                    <p class="specialized-exam-status">{{ __('specialized_exam.account_saved') }}</p>
                @else
                    @php($next = route($attempt->resultRoute(), ['attempt' => $attempt, 'lang' => app()->getLocale()], absolute: false))
                    <section class="specialized-exam-card">
                        <h3 class="assessment-result-heading">{{ __('specialized_exam.save_heading') }}</h3>
                        <p>{{ __('specialized_exam.saved_browser') }}</p>
                        @if($student)
                            <form method="post" action="{{ route('specialized-exams.attempts.claim', $attempt) }}">
                                @csrf
                                <button class="specialized-exam-action">{{ __('specialized_exam.save_account') }}</button>
                            </form>
                        @else
                            @guest
                                <div class="specialized-exam-actions">
                                    <a class="specialized-exam-action" href="{{ route('register', ['next' => $next]) }}">{{ __('specialized_exam.register') }}</a>
                                    <a class="specialized-exam-action specialized-exam-secondary" href="{{ route('login', ['next' => $next]) }}">{{ __('specialized_exam.login') }}</a>
                                </div>
                            @endguest
                            @if($attempt->guest_email)
                                <p>{{ __('specialized_exam.email_saved') }}</p>
                            @endif
                            <form class="specialized-exam-email" method="post" action="{{ route('specialized-exams.attempts.email', $attempt) }}">
                                @csrf
                                <label for="specialized-email">{{ __('specialized_exam.email') }}</label>
                                <p id="specialized-email-help">{{ __('specialized_exam.email_help') }}</p>
                                <input id="specialized-email" type="email" name="email" maxlength="255" autocomplete="email" required aria-describedby="specialized-email-help">
                                <button class="specialized-exam-action specialized-exam-secondary">{{ __('specialized_exam.save_email') }}</button>
                            </form>
                        @endif
                    </section>
                @endif
            </x-assessment-result-layout>
        @elseif(!$attempt->expires_at->isFuture())
            <p class="specialized-exam-status">{{ __('specialized_exam.expired') }}</p>
        @else
            <div class="specialized-exam-timer">
                {{ __('specialized_exam.remaining') }}
                <output data-exam-countdown data-expires="{{ $attempt->expires_at->getTimestamp() }}" class="tabular-nums"></output>
                <noscript>{{ $attempt->expires_at->timezone('Asia/Phnom_Penh')->format('H:i T') }}</noscript>
            </div>
            @if($attempt->snapshot['instructions_html'])
                <section class="specialized-exam-card">
                    <h2>{{ __('specialized_exam.instructions') }}</h2>
                    <x-lesson-content :lesson="(object) ['content_html' => $attempt->snapshot['instructions_html'], 'content' => '']" />
                </section>
            @endif
            <form data-exam-attempt class="specialized-exam-questions" method="post" enctype="multipart/form-data"
                action="{{ route('specialized-exams.attempts.submit', $attempt) }}"
                data-feedback-url="{{ route('specialized-exams.attempts.feedback', $attempt) }}">
                @csrf
                @if($attempt->snapshot['gamified'])
                    <label><span data-exam-progress></span>
                        <progress data-exam-meter value="0" max="{{ count($attempt->snapshot['questions']) }}"></progress>
                    </label>
                @endif
                @foreach($attempt->snapshot['questions'] as $index => $question)
                    <div class="specialized-exam-question">
                        @include('workspace.student.assessment-question')
                    </div>
                @endforeach
                <button type="submit" class="specialized-exam-action">{{ __($isQuiz ? 'specialized_exam.quiz_submit' : 'specialized_exam.submit') }}</button>
            </form>
        @endif
        @if(isset($attempt) && $exam->published && ($attempt->submitted_at || !$attempt->expires_at->isFuture()))
            <a class="specialized-exam-back" href="{{ $exam->publicUrl() }}">{{ __($isQuiz ? 'specialized_exam.quiz_back' : 'specialized_exam.back') }}</a>
        @endif
    </div>
</div>
@endsection
