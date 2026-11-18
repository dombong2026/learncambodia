@extends('layouts.focused-learning')
@section('title', $lesson->title.' · '.$course->title)

@section('content')
@php
    $done = in_array($lesson->id, $completedIds, true);
    $types = [
        'reading' => ['book-open', __('focused_learning.reading')],
        'video' => ['play', __('focused_learning.video')], 'youtube' => ['play', __('focused_learning.video')],
        'audio' => ['headphones', __('focused_learning.audio')], 'pdf' => ['file', 'PDF'],
        'document' => ['file', __('focused_learning.document')], 'quiz' => ['list-check', __('focused_learning.quiz')],
        'exam' => ['clipboard-check', __('focused_learning.assessment')],
    ];
    $lessonUrl = fn (array $query = []) => route('account.lessons.watch', [$enrollment, $lesson] + $query);
@endphp

<div class="fl-grid">
    <div class="fl-main">
        <a class="fl-back" href="{{ route('account.enrollments.show', $enrollment) }}">
            <x-workspace-icon name="chevron-right" /> <span>{{ $course->title }}</span>
        </a>
        <div class="fl-lesson-heading">
            <div>
                <p class="fl-lesson-meta">
                    <span>{{ $lesson->module?->title }}</span>
                    <span>{{ __('focused_learning.lesson_number', ['number' => $position + 1]) }}@if($lesson->duration_minutes > 0) · {{ __('focused_learning.duration', ['minutes' => $lesson->duration_minutes]) }} @endif</span>
                </p>
                <h1>{{ $lesson->title }}</h1>
            </div>
            <a class="fl-mobile-outline fl-button" href="#course-outline"><x-workspace-icon name="layers" /> {{ __('focused_learning.outline') }}</a>
        </div>
        @if(session('status'))<p class="fl-notice" role="status">{{ session('status') }}</p>@endif

        <section id="lesson-viewer" class="fl-panel fl-viewer" aria-label="Lesson viewer">
            <div class="fl-viewer-bar">
                <span><x-workspace-icon :name="$types[$viewer['type']][0]" />{{ $types[$viewer['type']][1] }}</span>
                @if(count($materials['viewers']) > 1)
                    <details class="fl-choose">
                        <summary>{{ __('focused_learning.choose') }} <x-workspace-icon name="chevron-down" /></summary>
                        <div>
                            @foreach($materials['viewers'] as $key => $material)
                                <a href="{{ $lessonUrl(['viewer' => $key, 'tab' => $tab, 'instructor' => $instructor?->id]) }}" @if($key === $viewerKey) aria-current="true" @endif>
                                    <x-workspace-icon :name="$types[$material['type']][0]" />{{ $material['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>
            @include('learning.partials.viewer')
        </section>

        <section class="fl-panel fl-tabs" aria-label="Lesson information">
            <nav aria-label="Lesson sections">
                @foreach(['details' => ['file',__('focused_learning.details')], 'comment' => ['messages-square',__('focused_learning.comment')], 'resources' => ['paperclip',__('focused_learning.resources')], 'support' => ['life-buoy',__('focused_learning.support')]] as $key => [$icon, $label])
                    <a href="{{ $lessonUrl(['viewer' => $viewerKey, 'tab' => $key]) }}#lesson-section"
                        @if($tab === $key) aria-current="page" @endif>
                        <x-workspace-icon :name="$icon" />{{ $label }}
                    </a>
                @endforeach
            </nav>
            <div id="lesson-section" class="fl-tab-content">
                @include('learning.partials.lesson-'.$tab)
            </div>
        </section>

        <div class="fl-lesson-actions">
            <form action="{{ route('account.progress.update', [$enrollment, $lesson]) }}" method="POST">
                @csrf
                <input type="hidden" name="complete" value="{{ $done ? '0' : '1' }}">
                <button class="fl-button {{ $done ? '' : 'fl-primary' }}" type="submit">
                    <x-workspace-icon name="check" />{{ $done ? __('focused_learning.review') : __('focused_learning.complete') }}
                </button>
            </form>
            <nav aria-label="Previous and next lesson">
                @if($previous)
                    <a rel="prev" class="fl-button" href="{{ route('account.lessons.watch', [$enrollment, $previous]) }}">← {{ __('focused_learning.previous') }}</a>
                @endif
                @if($next)
                    <a rel="next" class="fl-button" href="{{ route('account.lessons.watch', [$enrollment, $next]) }}">{{ __('focused_learning.next') }} →</a>
                @endif
            </nav>
        </div>
    </div>
    @include('learning.partials.outline')
</div>
@endsection
