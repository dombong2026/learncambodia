@props(['record', 'kind'])

<div {{ $attributes->class('grid min-w-0 gap-4') }}>
    <label class="builder-field">
        {{ __('detail.requirement') }}
        <textarea name="public_requirement" rows="3" maxlength="2000">{{ old('public_requirement', $record->public_requirement) }}</textarea>
        @error('public_requirement')<span class="text-xs text-red-700 dark:text-red-300">{{ $message }}</span>@enderror
    </label>
    <label class="builder-field">
        {{ __('detail.'.$kind.'_language') }}
        <input name="content_language" value="{{ old('content_language', $record->content_language) }}" maxlength="100">
        @error('content_language')<span class="text-xs text-red-700 dark:text-red-300">{{ $message }}</span>@enderror
    </label>
</div>
