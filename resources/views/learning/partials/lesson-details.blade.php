@if($viewer['type'] === 'reading')
    <details class="fl-instructions">
        <summary><h2>{{ __("focused_learning.instructions") }}</h2><x-workspace-icon name="chevron-down" /></summary>
        @include('learning.partials.reading-content')
    </details>
@else
    <h2>{{ __("focused_learning.instructions") }}</h2>
    @include('learning.partials.reading-content')
@endif
<details id="private-lesson-notes" class="fl-note">
    <summary><x-workspace-icon name="notebook-pen" /> {{ __("focused_learning.notes") }}</summary>
    <form method="POST" action="{{ $studyNote ? route('account.notes.update', $studyNote) : route('account.notes.store', [$enrollment, $lesson]) }}" class="fl-form">
        @csrf
        @if($studyNote) @method('PATCH') @endif
        <label for="lesson-note-title">{{ __("focused_learning.note_title") }}</label>
        <input id="lesson-note-title" name="title" maxlength="140" value="{{ old('title', $studyNote?->title ?? $lesson->title) }}" required>
        @error('title')<p class="fl-error" role="alert">{{ $message }}</p>@enderror
        <label for="lesson-note-body">{{ __("focused_learning.private_note") }}</label>
        <textarea id="lesson-note-body" name="body" maxlength="10000" rows="4" required>{{ old('body', $studyNote?->body) }}</textarea>
        @error('body')<p class="fl-error" role="alert">{{ $message }}</p>@enderror
        <label class="fl-check-field">
            <input type="hidden" name="is_pinned" value="0">
            <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $studyNote?->is_pinned ?? false))> Pin in my notes
        </label>
        <div class="fl-form-actions">
            <button type="submit" class="fl-button fl-primary">{{ __("focused_learning.save_note") }}</button>
            <a class="fl-link" href="{{ route('account.notes.index', ['enrollment' => $enrollment->id]) }}">{{ __("focused_learning.all_notes") }}</a>
        </div>
    </form>
</details>
