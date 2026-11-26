@props(['selection'])
@php
    $clips = $selection['items']->map(fn ($media) => ['url' => route('editor-media.show', $media, false), 'name' => $media->original_name])->all();
@endphp
@once
    <script src="{{ asset('js/home-hero-playlist.js') }}" defer></script>
@endonce
<div class="hhp" data-hero-playlist data-clips="{{ json_encode($clips, JSON_THROW_ON_ERROR) }}" data-options="{{ json_encode($selection['options'], JSON_THROW_ON_ERROR) }}" data-admin-preview="{{ request()->routeIs('admin.*') ? 'true' : 'false' }}">
    <video controls playsinline preload="none" src="{{ $clips[0]['url'] }}" aria-label="{{ $selection['label'] }}" @if($selection['options']['muted']) muted @endif></video>
    <div class="hhp-bar">
        <button type="button" data-hhp-prev aria-label="Previous video" title="Previous video">‹</button>
        <button type="button" data-hhp-play aria-label="Play or pause" hidden>Play</button>
        <button type="button" data-hhp-mute aria-label="Mute or unmute" hidden>Unmute</button>
        <span data-hhp-position aria-live="polite">1 / {{ count($clips) }}</span>
        <button type="button" data-hhp-next aria-label="Next video" title="Next video">›</button>
    </div>
    <p class="hhp-status" data-hhp-status role="status" hidden></p>
    @if($selection['options']['show_list'])
        <details class="hhp-list"><summary>Promotional playlist</summary>
            <ol>@foreach($clips as $index => $clip)<li><button type="button" data-hhp-clip="{{ $index }}">{{ $clip['name'] }}</button></li>@endforeach</ol>
        </details>
    @endif
</div>
