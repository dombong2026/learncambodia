@props(['selection'])
@php
    $isPlaylist = $selection['kind'] === 'playlist';
    $items = $isPlaylist ? $selection['items'] : collect([$selection['media']]);
    $clips = $items->map(fn ($media) => ['url' => route('editor-media.show', $media, false), 'name' => $media->original_name])->all();
    $options = array_replace(\App\Support\HomeHeroMedia::OPTIONS, $selection['options'] ?? []);
    if (!$isPlaylist) {
        $options['navigation'] = false;
        $options['show_list'] = false;
    }
@endphp
@once
    <script src="{{ asset('js/home-hero-playlist.js') }}?v=2" defer></script>
@endonce
<div class="hhp hhp-cinema" data-hero-playlist data-clips="{{ json_encode($clips, JSON_THROW_ON_ERROR) }}" data-options="{{ json_encode($options, JSON_THROW_ON_ERROR) }}" data-admin-preview="{{ request()->routeIs('admin.appearance.home-hero-media.index') ? 'true' : 'false' }}" data-crop-bars="{{ $options['crop_bars'] ? 'true' : 'false' }}" role="region" aria-label="{{ $selection['label'] }}">
    <video playsinline preload="metadata" src="{{ $clips[0]['url'] }}" aria-label="{{ $selection['label'] }}" @if($options['muted']) muted @endif disablepictureinpicture controlslist="nodownload noplaybackrate noremoteplayback" disableremoteplayback></video>
    <div class="hhp-overlay">
        <div class="hhp-actions">
            <button type="button" class="hhp-glass" data-hhp-play aria-label="Play video" title="Play video">
                <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path data-hhp-play-icon d="M8 5v14l11-7z"/><path data-hhp-pause-icon d="M7 5h4v14H7zm6 0h4v14h-4z" hidden/></svg>
            </button>
            <button type="button" class="hhp-glass" data-hhp-mute aria-label="Unmute video" title="Unmute video" @if(!$options['controls']) hidden @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h4l5-4v14l-5-4H3z"/><path data-hhp-muted-icon d="m16 9 5 6m0-6-5 6"/><path data-hhp-sound-icon d="M16 8q5 4 0 8m3-11q7 7 0 14" hidden/></svg>
            </button>
        </div>
        @if($isPlaylist && $options['navigation'])
            <button type="button" class="hhp-glass hhp-prev" data-hhp-prev aria-label="Previous video" title="Previous video"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg></button>
            <button type="button" class="hhp-glass hhp-next" data-hhp-next aria-label="Next video" title="Next video"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
            <span class="hhp-count" data-hhp-position aria-live="polite">1 / {{ count($clips) }}</span>
        @endif
        <p class="hhp-status" data-hhp-status role="status" hidden></p>
        @if($isPlaylist && $options['show_list'])
            <details class="hhp-list"><summary>Playlist</summary>
                <ol>@foreach($clips as $index => $clip)<li><button type="button" data-hhp-clip="{{ $index }}">{{ $clip['name'] }}</button></li>@endforeach</ol>
            </details>
        @endif
    </div>
    <noscript><p class="hhp-status">Enable JavaScript for continuous video playback.</p></noscript>
</div>
