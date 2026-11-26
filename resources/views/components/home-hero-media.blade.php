@props(['selection'])
@once
    <link rel="stylesheet" href="{{ asset('css/home-hero-media.css') }}?v=2">
@endonce
<div class="home-hero-media" data-home-hero-media="{{ $selection['kind'] }}">
    @if($selection['kind'] === 'playlist')
        <x-home-hero-playlist :selection="$selection"/>
    @elseif($selection['kind'] === 'video')
        <x-home-hero-playlist :selection="$selection"/>
    @else
        <img src="{{ route('editor-media.show', $selection['media']) }}" alt="{{ $selection['label'] }}" fetchpriority="high" decoding="async">
    @endif
</div>
