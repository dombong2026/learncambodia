@props(['surface' => 'adaptive', 'height' => 'h-9', 'imageClass' => ''])
@php
    // surface=dark is for fixed navy UI such as the auth modal and sidebar.
    // surface=adaptive swaps the approved navy/white lettering with the theme.
    $base = $height.' w-auto max-w-full object-contain object-left '.$imageClass;
@endphp
<span {{ $attributes->class(['inline-flex min-w-0 max-w-full items-center']) }}>
    @if($surface === 'dark')
        <img src="{{ asset('brand/logo-dark.png') }}" width="1902" height="375"
             class="{{ $base }}" alt="LearnCambodia" decoding="async">
    @elseif($surface === 'light')
        <img src="{{ asset('brand/logo-light.png') }}" width="1924" height="383"
             class="{{ $base }}" alt="LearnCambodia" decoding="async">
    @else
        <img src="{{ asset('brand/logo-light.png') }}" width="1924" height="383"
             class="{{ $base }} dark:hidden" alt="LearnCambodia" decoding="async">
        <img src="{{ asset('brand/logo-dark.png') }}" width="1902" height="375"
             class="{{ $base }} hidden dark:block" alt="" aria-hidden="true" decoding="async">
    @endif
</span>
