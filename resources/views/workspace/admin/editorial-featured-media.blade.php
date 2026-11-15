@php
    $selectedKind = old('featured_kind', $entry->featured_kind ?? 'none');
    $selectedAsset = old('featured_media_id', $entry->featured_media_id);
    $storedAsset = (string) $selectedAsset === (string) $entry->featured_media_id ? $entry->featuredMedia : null;
@endphp
<section data-featured-editor class="space-y-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700" aria-labelledby="featured-heading">
    <h2 id="featured-heading" class="text-sm font-extrabold">Featured media</h2>
    <label for="featured_kind" class="block text-xs font-semibold">Media type</label>
    <select id="featured_kind" name="featured_kind" data-featured-kind class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
        @foreach(['none' => 'None', 'image' => 'Image', 'video' => 'Uploaded video', 'youtube' => 'YouTube', 'color' => 'Color'] as $value => $text)
            <option value="{{ $value }}" @selected($selectedKind === $value)>{{ $text }}</option>
        @endforeach
    </select>
    <div data-featured-asset class="space-y-3" @if(!in_array($selectedKind, ['image', 'video'], true)) hidden @endif>
        <input type="hidden" name="featured_media_id" data-featured-id value="{{ $selectedAsset }}">
        <p data-featured-name class="break-words text-xs" aria-live="polite">{{ $storedAsset?->original_name ?: ($selectedAsset ? 'Media #'.$selectedAsset : 'Choose a file') }}</p>
        <button type="button" data-open-media-library class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold dark:border-slate-600">Select from Media Library</button>
        <div data-featured-alt @if($selectedKind !== 'image') hidden @endif>
            <label for="featured_alt" class="mb-1 block text-xs font-semibold">Image description</label>
            <input id="featured_alt" name="featured_alt" maxlength="200" value="{{ old('featured_alt', $entry->featured_alt) }}" class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-xs dark:border-slate-700">
        </div>
    </div>
    <div data-featured-youtube @if($selectedKind !== 'youtube') hidden @endif>
        <label for="featured_youtube" class="mb-1 block text-xs font-semibold">YouTube link or video ID</label>
        <input id="featured_youtube" name="featured_youtube" value="{{ old('featured_youtube', $entry->featured_kind === 'youtube' ? $entry->featured_value : '') }}" type="text" class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2 text-xs dark:border-slate-700">
    </div>
    <div data-featured-color @if($selectedKind !== 'color') hidden @endif class="items-center gap-3">
        <label for="featured_color" class="text-xs font-semibold">Cover color</label>
        <input type="color" id="featured_color" name="featured_color" value="{{ old('featured_color', $entry->featured_kind === 'color' ? $entry->featured_value : '#123e71') }}" class="h-9 w-12 align-middle">
    </div>
    @foreach(['featured_kind', 'featured_media_id', 'featured_alt', 'featured_youtube', 'featured_color'] as $field)
        @error($field)<p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
    @endforeach
</section>
