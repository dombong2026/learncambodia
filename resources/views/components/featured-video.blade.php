@props(['src', 'label'])
<div class="featured-video" data-featured-video>
    <video controls playsinline preload="metadata" src="{{ $src }}" aria-label="{{ $label }}"></video>
    <div class="featured-video-controls" data-video-controls hidden>
        <button type="button" data-video-play aria-label="Play" title="Play">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
        </button>
        <span class="featured-video-time" data-video-time aria-hidden="true">0:00 / 0:00</span>
        <input type="range" data-video-seek min="0" max="1000" value="0" step="1" aria-label="Seek">
        <button type="button" data-video-mute aria-label="Mute" title="Mute">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 9h4l5-4v14l-5-4H3zM15 9a4 4 0 0 1 0 6m2-8a7 7 0 0 1 0 10"/></svg>
        </button>
        <button type="button" data-video-fullscreen aria-label="Fullscreen" title="Fullscreen">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 9V4h5m6 0h5v5m0 6v5h-5m-6 0H4v-5"/></svg>
        </button>
    </div>
    <p data-video-status class="featured-video-status" role="status" hidden></p>
</div>
