@extends('layouts.focused-learning')
@section('title', 'Lesson support')

@section('content')
<div class="fl-instructor">
    <a class="fl-back" href="{{ isset($threads) ? route('instructor.courses.index') : route('instructor.lesson-support.index') }}"><x-workspace-icon name="chevron-right" />{{ isset($threads) ? 'My Assigned Courses' : 'Lesson support inbox' }}</a>
    <h1>{{ isset($threads) ? 'Lesson support' : $lesson->title }}</h1>
    @if(isset($threads))
        <div class="fl-panel fl-tab-content">
            <ul class="fl-resources">
                @forelse($threads as $thread)
                    <li><a href="{{ route('instructor.lesson-support.show', [$thread->enrollment, $thread->lesson]) }}">
                        <x-workspace-icon name="life-buoy" />
                        <div><strong>{{ $thread->lesson->title }}</strong><small>{{ $thread->enrollment->student->name }} · {{ $thread->enrollment->course->title }}</small></div><span>Open</span>
                    </a></li>
                @empty
                    <li class="fl-muted">No private lesson conversations yet.</li>
                @endforelse
            </ul>
            {{ $threads->links() }}
        </div>
    @else
        <p class="fl-muted">{{ $enrollment->student->name }} · {{ $enrollment->course->title }}</p>
        <section id="lesson-section" class="fl-panel fl-tab-content">
            <div class="fl-chat-scroll" data-message-scroll
                @if($messages->onFirstPage()) data-refresh-url="{{ route('instructor.lesson-support.show', [$enrollment, $lesson, 'fragment' => 1]) }}" @endif>
                @include('learning.partials.support-messages')
            </div>
            <form class="fl-form" method="POST" action="{{ route('instructor.lesson-support.reply', [$enrollment, $lesson]) }}">
                @csrf
                <label for="instructor-reply">Your reply</label>
                <textarea id="instructor-reply" name="message" rows="3" maxlength="4000" required>{{ old('message') }}</textarea>
                @error('message')<p class="fl-error" role="alert">{{ $message }}</p>@enderror
                <button type="submit" class="fl-button fl-primary">Send reply</button>
            </form>
        </section>
    @endif
</div>
@endsection
