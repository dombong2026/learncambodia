@extends('layouts.focused-learning')
@section('title', __('course_support.inbox'))

@section('content')
<div class="fl-instructor">
    <a class="fl-back" href="{{ isset($threads) ? route('instructor.courses.index') : route('instructor.lesson-support.index') }}"><x-workspace-icon name="chevron-right" />{{ isset($threads) ? __('course_support.assigned_courses') : __('course_support.inbox') }}</a>
    <h1>{{ isset($threads) ? __('course_support.inbox') : $enrollment->course->title }}</h1>
    @if(isset($threads))
        <div class="fl-panel fl-tab-content">
            <ul class="fl-resources">
                @forelse($threads as $thread)
                    <li><a href="{{ route('instructor.lesson-support.show', [$thread->enrollment, $thread->lesson]) }}">
                        <x-workspace-icon name="life-buoy" />
                        <div>
                            <strong>{{ $thread->enrollment->student->name }} · {{ $thread->enrollment->course->title }}</strong>
                            <small>{{ $thread->lesson->title }}</small>
                        </div><span>{{ __('course_support.open') }}</span>
                    </a></li>
                @empty
                    <li class="fl-muted">{{ __('course_support.empty_inbox') }}</li>
                @endforelse
            </ul>
            {{ $threads->links() }}
        </div>
    @else
        <p class="fl-muted">{{ __('course_support.privacy', ['name' => $enrollment->student->name]) }}</p>
        <section id="lesson-section" class="fl-panel fl-tab-content">
            @if(session('status'))<p role="status" class="fl-muted">{{ session('status') }}</p>@endif
            <div class="fl-chat-scroll" data-message-scroll role="log" aria-label="{{ __('course_support.conversation') }}"
                @if($messages->onFirstPage()) data-refresh-url="{{ route('instructor.lesson-support.show', [$enrollment, $lesson, 'fragment' => 1]) }}" @endif>
                @include('learning.partials.support-messages')
            </div>
            <form class="fl-form" method="POST" action="{{ route('instructor.lesson-support.reply', [$enrollment, $lesson]) }}">
                @csrf
                <label for="instructor-reply">{{ __('course_support.reply') }}</label>
                <textarea id="instructor-reply" name="message" rows="3" maxlength="4000" required>{{ old('message') }}</textarea>
                @error('message')<p class="fl-error" role="alert">{{ $message }}</p>@enderror
                <button type="submit" class="fl-button fl-primary">{{ __('course_support.send_reply') }}</button>
            </form>
        </section>
    @endif
</div>
@endsection
