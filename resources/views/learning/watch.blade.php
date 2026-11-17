@extends('layouts.public')
@section('title', \App\Support\Appearance::value('course_watch', 'hero_heading'))

@push('public-styles')
    <link rel="stylesheet" href="{{ asset('css/course-watch.css') }}?v=1">
@endpush

@section('content')
@php $appearance = \App\Support\Appearance::approved('course_watch'); @endphp
<div class="cw" data-course-watch>
    <div class="cw-shell">
        <section class="cw-hero" aria-labelledby="watch-title">
            <div class="cw-hero-copy">
                <div class="cw-hero-context"><x-workspace-icon name="book-open" />{{ __('course_watch.course_watch') }}</div>
                <h1 id="watch-title">{{ $appearance['hero_heading'] }}</h1>
                <p>{{ $appearance['hero_intro'] }}</p>
                <div class="cw-actions">
                    @if($resumeCourses->isNotEmpty())
                        <a class="cw-button cw-button-accent" href="#learning-courses">{{ __('course_watch.continue_learning') }} <x-workspace-icon name="chevron-right" /></a>
                        <a class="cw-button cw-button-on-hero" href="{{ route('courses.index') }}">{{ __('course_watch.browse_courses') }}</a>
                    @else
                        <a class="cw-button cw-button-accent" href="{{ route('courses.index') }}">{{ __('course_watch.browse_courses') }} <x-workspace-icon name="chevron-right" /></a>
                        @guest
                            <a class="cw-button cw-button-on-hero" href="{{ route('login') }}">{{ __('course_watch.sign_in') }}</a>
                        @endguest
                    @endif
                </div>
            </div>
        </section>

        @if($student)
            <section id="learning-courses" class="cw-section" aria-labelledby="watch-enrollments-title">
                <div class="cw-section-head">
                    <h2 id="watch-enrollments-title">{{ __('course_watch.your_courses') }}</h2>
                    <a class="cw-text-link" href="{{ route('account.enrollments.index') }}">{{ __('course_watch.all_enrollments') }} <x-workspace-icon name="chevron-right" /></a>
                </div>
                @if($resumeCourses->isEmpty())
                    <div class="cw-panel cw-empty">
                        <span class="cw-tile-icon"><x-workspace-icon name="book-open" /></span>
                        <p>{{ __('course_watch.empty_enrollments') }}</p>
                    </div>
                @else
                    <div class="cw-resume-grid">
                        @foreach($resumeCourses as $entry)
                            @php
                                $enrollment = $entry['enrollment'];
                                $nextLesson = $entry['nextLesson'];
                                $completed = $entry['completedCount'];
                                $total = $entry['totalLessons'];
                                $action = match (true) {
                                    $nextLesson === null => __('course_watch.view_curriculum'),
                                    $completed === 0 => __('course_watch.start_course'),
                                    default => __('course_watch.resume_lesson'),
                                };
                                $target = $nextLesson
                                    ? route('account.lessons.watch', [$enrollment, $nextLesson])
                                    : route('account.enrollments.show', $enrollment);
                            @endphp
                            <article class="cw-panel cw-resume-card">
                                <div class="cw-resume-lead">
                                    <span class="cw-tile-icon"><x-workspace-icon name="book-open" /></span>
                                    <div>
                                        <h3>{{ $enrollment->course->title }}</h3>
                                        @if($nextLesson)
                                            <p>{{ $nextLesson->title }}</p>
                                        @elseif($total > 0)
                                            <p>{{ __('course_watch.course_complete') }}</p>
                                        @endif
                                    </div>
                                </div>
                                @if($total > 0)
                                    <div class="cw-progress">
                                        <label for="watch-progress-{{ $enrollment->id }}">{{ __('course_watch.completion_count', ['done' => $completed, 'total' => $total]) }}</label>
                                        <progress id="watch-progress-{{ $enrollment->id }}" value="{{ $completed }}" max="{{ $total }}"></progress>
                                    </div>
                                @endif
                                <div class="cw-actions">
                                    <a class="cw-button cw-button-primary" href="{{ $target }}" data-watch-resume>{{ $action }} <x-workspace-icon name="chevron-right" /></a>
                                    @if($nextLesson)
                                        <a class="cw-button cw-button-quiet" href="{{ route('account.enrollments.show', $enrollment) }}">{{ __('course_watch.view_curriculum') }}</a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        <section @unless($student) id="learning-courses" @endunless class="cw-section" aria-labelledby="watch-catalog-title">
            <div class="cw-section-head">
                <h2 id="watch-catalog-title">{{ $appearance['curriculum_heading'] }}</h2>
                <a class="cw-text-link" href="{{ route('courses.index') }}">{{ __('course_watch.all_courses') }} <x-workspace-icon name="chevron-right" /></a>
            </div>
            <div class="cw-catalog-grid">
                @forelse($courses as $course)
                    <x-public-course-card :course="$course" />
                @empty
                    <p class="cw-muted">{{ __('course_watch.empty_catalog') }}</p>
                @endforelse
            </div>
        </section>

        <section class="cw-section" aria-labelledby="watch-tools-title">
            <div class="cw-section-head">
                <h2 id="watch-tools-title">{{ $appearance['features_heading'] }}</h2>
            </div>
            <div class="cw-tools-grid">
                @foreach([
                    ['chart-line', 'your_progress', 'account.progress.index', null],
                    ['list-check', 'practice', 'account.practice.index', 'learning.exams'],
                    ['notebook-pen', 'assignments', 'account.homework.index', null],
                ] as [$icon, $label, $accountRoute, $publicRoute])
                    <a class="cw-panel cw-tool" href="{{ route($student ? $accountRoute : ($publicRoute ?? 'login')) }}">
                        <span class="cw-tile-icon"><x-workspace-icon :name="$icon" /></span>
                        <span>{{ __('course_watch.'.$label) }}</span>
                        <x-workspace-icon name="chevron-right" class="cw-tool-arrow" />
                    </a>
                @endforeach
            </div>
        </section>

        <div class="cw-landing-footer">
            <a class="cw-text-link" href="{{ route('information.show', 'privacy') }}">{{ $appearance['privacy_heading'] }}</a>
            <a class="cw-text-link" href="{{ route($student ? 'account.enrollments.index' : 'courses.index') }}">{{ $appearance['cta_heading'] }} <x-workspace-icon name="chevron-right" /></a>
        </div>
    </div>
</div>
@endsection
