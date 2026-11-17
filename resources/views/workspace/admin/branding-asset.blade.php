@php
    $asset = isset($sharingLocale)
        ? \App\Support\PlatformBranding::sharingImage($sharingLocale)
        : \App\Support\PlatformBranding::asset($slot);
    $logo = str_starts_with($slot, 'logo-');
    $sharing = str_starts_with($slot, 'share-image');
    $label = ($sharingLabel ?? __($asset['label'])).($logo || $sharing ? '' : ' '.$asset['width'].' × '.$asset['height']);
    $hint = match (true) {
        $logo => __('PNG · 200–3,000 px wide · 40–1,200 px high'),
        $sharing => __('PNG, JPEG, or WebP · 1,200 × 630 px recommended · up to 2 MB'),
        default => $asset['width'].' × '.$asset['height'].' px · PNG',
    };
@endphp
<div class="min-w-0 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
    <label for="branding-{{ $slot }}" class="block text-sm font-semibold">{{ $label }}</label>
    <div class="branding-preview branding-preview--{{ $sharing ? 'share-image' : $slot }} my-3">
        @if($asset['url'])
            <img src="{{ $asset['url'] }}" width="{{ $asset['width'] }}" height="{{ $asset['height'] }}" alt="{{ $label }}">
        @else
            <span class="text-sm text-slate-600 dark:text-slate-300">{{ __('No image uploaded') }}</span>
        @endif
    </div>
    <p id="branding-{{ $slot }}-help" class="mb-2 text-xs text-slate-600 dark:text-slate-300">{{ $hint }}</p>
    <input type="file" id="branding-{{ $slot }}" name="uploads[{{ $slot }}]" accept="{{ $sharing ? 'image/png,image/jpeg,image/webp' : 'image/png' }}" aria-describedby="branding-{{ $slot }}-help" class="branding-upload block w-full text-sm">
</div>
