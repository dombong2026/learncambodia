@props(['src' => null, 'label', 'compact' => false])
<div class="featured-video{{ $compact ? ' featured-video-compact' : '' }}" data-featured-video @if($compact) data-compact-video hidden @endif>
    <video controls playsinline preload="metadata" @if($src) src="{{ $src }}" @endif aria-label="{{ $label }}"></video>
    <div class="featured-video-controls" data-video-controls hidden>
        <button type="button" data-video-play aria-label="Play" title="Play">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
        </button>
        <span class="featured-video-time" data-video-time aria-hidden="true">0:00 / 0:00</span>
        <input type="range" data-video-seek min="0" max="1000" value="0" step="1" aria-label="Seek">
        <button type="button" data-video-mute aria-label="Mute" title="Mute">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 9h4l5-4v14l-5-4H3zM15 9a4 4 0 0 1 0 6m2-8a7 7 0 0 1 0 10"/></svg>
        </button>
        @if($compact)
            <select class="featured-video-speed" data-video-speed aria-label="Speed" title="Playback speed">
                <option value="0.5">0.5×</option>
                <option value="0.75">0.75×</option>
                <option value="1" selected>1×</option>
                <option value="1.25">1.25×</option>
                <option value="1.5">1.5×</option>
                <option value="1.75">1.75×</option>
                <option value="2">2×</option>
            </select>
        @endif
        <button type="button" data-video-fullscreen aria-label="Fullscreen" title="Fullscreen">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 9V4h5m6 0h5v5m0 6v5h-5m-6 0H4v-5"/></svg>
        </button>
    </div>
    <p data-video-status class="featured-video-status" role="status" hidden></p>
</div>
