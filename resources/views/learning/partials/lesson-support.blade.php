<h2>{{ __("focused_learning.private_support") }}</h2>
@if($instructor)
    <div class="fl-support-heading">
        <p class="fl-muted">{{ __('focused_learning.private_notice', ['name' => $instructor->name]) }}</p>
        @if($instructors->count() > 1)
            <form method="GET" action="{{ $lessonUrl() }}#lesson-section" class="fl-form fl-picker">
                <input type="hidden" name="tab" value="support">
                <input type="hidden" name="viewer" value="{{ $viewerKey }}">
                <label for="lesson-instructor">{{ __("focused_learning.instructor") }}</label>
                <select id="lesson-instructor" name="instructor">
                    @foreach($instructors as $recipient)
                        <option value="{{ $recipient->id }}" @selected($recipient->id === $instructor->id)>{{ $recipient->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="fl-button">{{ __("focused_learning.open_conversation") }}</button>
            </form>
        @endif
    </div>
    <div class="fl-chat-scroll" data-message-scroll
        @if($messages->onFirstPage()) data-refresh-url="{{ $lessonUrl(['tab' => 'support', 'instructor' => $instructor->id, 'viewer' => $viewerKey, 'fragment' => 1]) }}" @endif>
        @include('learning.partials.support-messages')
    </div>
    <form class="fl-form" action="{{ route('account.lessons.support', [$enrollment, $lesson]) }}" method="POST">
        @csrf
        <input type="hidden" name="viewer" value="{{ $viewerKey }}">
        <input type="hidden" name="instructor_id" value="{{ $instructor->id }}">
        <label for="support-message">{{ __("focused_learning.message") }}</label>
        <textarea id="support-message" name="message" rows="3" maxlength="4000" required>{{ old('message') }}</textarea>
        @error('message')<p class="fl-error" role="alert">{{ $message }}</p>@enderror
        <button type="submit" class="fl-button fl-primary">{{ __("focused_learning.send_message") }}</button>
    </form>
@else
    <p class="fl-muted">{{ __("focused_learning.no_instructor") }}</p>
    <a class="fl-link" href="{{ route('support.tickets.create') }}">{{ __("focused_learning.contact") }}</a>
@endif
