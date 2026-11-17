@extends('layouts.public')
@section('title', $lesson->title.' · '.$course->title)

@push('public-styles')
    <link rel="stylesheet" href="{{ asset('css/course-watch.css') }}?v=1">
@endpush

@section('content')
@php
    $totalLessons = $course->modules->sum(fn ($module) => $module->lessons->count());
    $completedCount = count($completedIds);
    $done = in_array($lesson->id, $completedIds, true);
    $hasQuiz = filled($lesson->quiz_questions);
@endphp
<div class="cw cw-lesson" data-course-lesson>
    <div class="cw-shell">
        <header class="cw-lesson-header">
            <div>
                <a class="cw-text-link cw-back" href="{{ route('account.enrollments.show', $enrollment) }}">
                    <x-workspace-icon name="chevron-right" />{{ $course->title }}
                </a>
                <h1>{{ $lesson->title }}</h1>
                <div class="cw-lesson-meta">
                    @if($position !== false)
                        <span>{{ __('course_watch.lesson_of', ['number' => $position + 1, 'total' => $totalLessons]) }}</span>
                    @endif
                    @if($lesson->duration_minutes > 0)
                        <span>{{ __('course_watch.duration', ['minutes' => $lesson->duration_minutes]) }}</span>
                    @endif
                    @if($done)
                        <span class="cw-complete-tag"><x-workspace-icon name="check-check" />{{ __('course_watch.completed') }}</span>
                    @endif
                </div>
            </div>
            <nav class="cw-lesson-shortcuts" aria-label="{{ __('course_watch.lesson_sections') }}">
                <a class="cw-button cw-button-quiet" href="#course-curriculum"><x-workspace-icon name="layers" />{{ __('course_watch.curriculum') }}</a>
                @if($hasQuiz)
                    <a class="cw-button cw-button-quiet" href="#lesson-quiz-{{ $lesson->id }}"><x-workspace-icon name="list-check" />{{ __('course_watch.lesson_quiz') }}</a>
                @endif
                <a class="cw-button cw-button-quiet" href="#private-lesson-notes"><x-workspace-icon name="notebook-pen" />{{ __('course_watch.notes') }}</a>
            </nav>
        </header>

        @if(session('status'))
            <p class="cw-feedback" role="status"><x-workspace-icon name="check-check" />{{ session('status') }}</p>
        @endif

        <div class="cw-lesson-grid">
            <div class="cw-lesson-main">
                <article id="lesson-content" class="cw-panel cw-study-panel" aria-label="{{ __('course_watch.lesson_content') }}">
                    <x-lesson-content :lesson="$lesson" class="cw-content" />
                    <x-lesson-quiz :lesson="$lesson" :enrollment="$enrollment" />

                    <div class="cw-completion">
                        <div>
                            <strong>{{ __('course_watch.lesson_progress') }}</strong>
                            <span>{{ $done ? __('course_watch.completed') : __('course_watch.in_progress') }}</span>
                        </div>
                        <form action="{{ route('account.progress.update', [$enrollment, $lesson]) }}" method="POST">
                            @csrf
                            <input type="hidden" name="complete" value="{{ $done ? '0' : '1' }}">
                            <button type="submit" class="cw-button {{ $done ? 'cw-button-quiet' : 'cw-button-primary' }}">
                                @unless($done)<x-workspace-icon name="check-check" />@endunless
                                {{ $done ? __('course_watch.mark_for_review') : __('course_watch.mark_complete') }}
                            </button>
                        </form>
                    </div>
                </article>

                @if($previous || $next)
                    <nav class="cw-lesson-pagination" aria-label="{{ __('course_watch.lesson_navigation') }}">
                        @if($previous)
                            <a class="cw-panel cw-page-link cw-page-link-prev" href="{{ route('account.lessons.watch', [$enrollment, $previous]) }}" rel="prev">
                                <x-workspace-icon name="chevron-right" />
                                <span><small>{{ __('course_watch.previous_lesson') }}</small><strong>{{ $previous->title }}</strong></span>
                            </a>
                        @endif
                        @if($next)
                            <a class="cw-panel cw-page-link cw-page-link-next" href="{{ route('account.lessons.watch', [$enrollment, $next]) }}" rel="next">
                                <span><small>{{ __('course_watch.next_lesson') }}</small><strong>{{ $next->title }}</strong></span>
                                <x-workspace-icon name="chevron-right" />
                            </a>
                        @endif
                    </nav>
                @endif

                <section id="private-lesson-notes" class="cw-panel cw-notes" aria-labelledby="lesson-notes-heading">
                    <div class="cw-section-head">
                        <h2 id="lesson-notes-heading">{{ __('course_watch.private_notes') }}</h2>
                        <a class="cw-text-link" href="{{ route('account.notes.index') }}">{{ __('course_watch.all_notes') }} <x-workspace-icon name="chevron-right" /></a>
                    </div>
                    <form method="POST" action="{{ $studyNote ? route('account.notes.update', $studyNote) : route('account.notes.store', [$enrollment, $lesson]) }}">
                        @csrf
                        @if($studyNote) @method('PATCH') @endif
                        <label class="cw-field">
                            <span>{{ __('course_watch.note_title') }}</span>
                            <input id="lesson-note-title" name="title" maxlength="140" required value="{{ old('title', $studyNote?->title ?? $lesson->title) }}">
                            @error('title')<span class="cw-field-error" role="alert">{{ $message }}</span>@enderror
                        </label>
                        <label class="cw-field">
                            <span>{{ __('course_watch.private_note') }}</span>
                            <textarea id="lesson-note-body" name="body" maxlength="10000" rows="4" required>{{ old('body', $studyNote?->body) }}</textarea>
                            @error('body')<span class="cw-field-error" role="alert">{{ $message }}</span>@enderror
                        </label>
                        <div class="cw-note-actions">
                            <label>
                                <input type="hidden" name="is_pinned" value="0">
                                <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $studyNote?->is_pinned ?? false))>
                                {{ __('course_watch.pin_note') }}
                            </label>
                            <button class="cw-button cw-button-primary" type="submit">{{ __('course_watch.save_note') }}</button>
                        </div>
                    </form>
                </section>
            </div>

            <aside id="course-curriculum" class="cw-sidebar">
                <div class="cw-panel cw-curriculum">
                    <div class="cw-curriculum-head">
                        <h2 id="course-curriculum-title">{{ __('course_watch.curriculum') }}</h2>
                        <div class="cw-progress">
                            <label for="lesson-course-progress">{{ __('course_watch.completion_count', ['done' => $completedCount, 'total' => $totalLessons]) }}</label>
                            <progress id="lesson-course-progress" value="{{ $completedCount }}" max="{{ $totalLessons }}"></progress>
                        </div>
                    </div>
                    <nav class="cw-module-list" aria-labelledby="course-curriculum-title">
                        @foreach($course->modules as $module)
                            <details class="cw-module" @if($module->lessons->contains('id', $lesson->id)) open @endif>
                                <summary>
                                    <span>{{ $module->title }}</span>
                                    <x-workspace-icon name="chevron-down" />
                                </summary>
                                <ul>
                                    @foreach($module->lessons as $item)
                                        @php
                                            $isCurrent = $item->id === $lesson->id;
                                            $isComplete = in_array($item->id, $completedIds, true);
                                        @endphp
                                        <li>
                                            <a href="{{ route('account.lessons.watch', [$enrollment, $item]) }}" @if($isCurrent) aria-current="page" @endif>
                                                <span class="cw-lesson-state @if($isComplete) cw-state-complete @endif">
                                                    <x-workspace-icon :name="$isComplete ? 'check-check' : 'file'" />
                                                </span>
                                                <span>{{ $item->title }}@if($isComplete)<span class="sr-only"> · {{ __('course_watch.completed') }}</span>@endif</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endforeach
                    </nav>
                </div>

                <nav class="cw-sidebar-links" aria-label="{{ __('course_watch.study_tools') }}">
                    <a class="cw-text-link" href="{{ route('account.progress.index', ['enrollment' => $enrollment->id]) }}"><x-workspace-icon name="chart-line" />{{ __('course_watch.your_progress') }}</a>
                    <a class="cw-text-link" href="{{ route('account.practice.index') }}"><x-workspace-icon name="list-check" />{{ __('course_watch.practice') }}</a>
                    <a class="cw-text-link" href="{{ route('account.homework.index') }}"><x-workspace-icon name="notebook-pen" />{{ __('course_watch.assignments') }}</a>
                </nav>
            </aside>
        </div>
    </div>
</div>
@endsection
