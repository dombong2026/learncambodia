@props(['surface' => 'adaptive', 'height' => 'h-9', 'imageClass' => ''])
@php
    $base = $height.' w-auto max-w-full object-contain object-left '.$imageClass;
    $variants = match ($surface) {
        'light', 'dark' => [$surface => ''],
        default => ['light' => ' dark:hidden', 'dark' => ' hidden dark:block'],
    };
@endphp
<span {{ $attributes->class(['inline-flex min-w-0 max-w-full items-center']) }}>
    @foreach($variants as $variant => $visibility)
        @php $logo = \App\Support\PlatformBranding::asset('logo-'.$variant); @endphp
        <img src="{{ $logo['url'] }}" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}"
             class="{{ $base.$visibility }}" alt="{{ \App\Support\PlatformBranding::name() }}" decoding="async">
    @endforeach
</span>
