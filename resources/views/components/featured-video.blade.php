@props(['src' => null, 'label', 'compact' => false])
<div class="featured-video{{ $compact ? ' featured-video-compact' : '' }}" data-featured-video @if($compact) data-compact-video hidden @endif>
    <video controls playsinline preload="metadata" @if($src) src="{{ $src }}" @endif aria-label="{{ $label }}"></video>
    <div class="featured-video-controls" data-video-controls hidden>
        <button type="button" data-video-play aria-label="Play" title="Play">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path data-video-play-icon d="M7 5.9a1 1 0 0 1 1.5-.86l10 6.1a1 1 0 0 1 0 1.72l-10 6.1A1 1 0 0 1 7 18.1z"/>
                @if($compact)<path data-video-pause-icon d="M7 5h3v14H7zm7 0h3v14h-3z"/>@endif
            </svg>
        </button>
        <span class="featured-video-time" data-video-time aria-hidden="true">0:00 / 0:00</span>
        <input type="range" data-video-seek min="0" max="1000" value="0" step="1" aria-label="Seek">
        @if($compact)
            <div>
                <button type="button" data-video-toggle aria-label="Speed" aria-expanded="false" aria-controls="preview-speed-panel" title="Speed (1×)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4.1 17a9 9 0 1 1 15.8 0M12 6v2M6.9 8.9l1.4 1.4M17.1 8.9l-3.9 4"/><circle cx="12" cy="14" r="1.5"/>
                    </svg>
                </button>
                <div id="preview-speed-panel" class="featured-video-popover featured-video-options" data-video-panel role="group" aria-label="Playback speed" hidden>
                    <span class="featured-video-option-title">Playback speed</span>
                    @foreach(['0.5', '0.75', '1', '1.25', '1.5', '1.75', '2'] as $rate)
                        <button type="button" data-video-rate="{{ $rate }}" aria-pressed="{{ $rate === '1' ? 'true' : 'false' }}">{{ $rate }}×</button>
                    @endforeach
                </div>
            </div>
            <div class="featured-video-volume">
                <button type="button" data-video-toggle aria-label="Volume" aria-expanded="false" aria-controls="preview-volume-panel" title="Volume">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 9h4l5-4v14l-5-4H3z"/>
                        <path data-video-volume-waves d="M15 9a4 4 0 0 1 0 6m2-8a7 7 0 0 1 0 10"/>
                        <path data-video-volume-cross d="m16 10 5 5m0-5-5 5"/>
                    </svg>
                </button>
                <div id="preview-volume-panel" class="featured-video-popover featured-video-volume-panel" data-video-panel data-video-volume-panel hidden>
                    <output data-video-volume-value aria-hidden="true">100%</output>
                    <div class="featured-video-volume-slider" data-video-volume-slider>
                        <input type="range" data-video-volume min="0" max="100" value="100" step="1" aria-label="Volume level" aria-orientation="vertical">
                    </div>
                    <p data-video-device-volume hidden>Use your device volume controls.</p>
                    <button type="button" data-video-mute aria-label="Mute" title="Mute">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 9h4l5-4v14l-5-4H3z"/>
                            <path data-video-volume-waves d="M15 9a4 4 0 0 1 0 6m2-8a7 7 0 0 1 0 10"/>
                            <path data-video-volume-cross d="m16 10 5 5m0-5-5 5"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div>
                <button type="button" data-video-toggle aria-label="Settings" aria-expanded="false" aria-controls="preview-settings-panel" title="Settings">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10 3h4l.7 2.7 2 .9 2.5-1.1 2 3.5-2.1 1.7v2.6l2.1 1.7-2 3.5-2.5-1.1-2 .9L14 21h-4l-.7-2.7-2-.9-2.5 1.1-2-3.5 2.1-1.7v-2.6L2.8 9l2-3.5 2.5 1.1 2-.9z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
                <div id="preview-settings-panel" class="featured-video-popover featured-video-options" data-video-panel role="group" aria-label="Playback settings" hidden>
                    <label class="featured-video-loop"><span>Loop video</span><input type="checkbox" data-video-loop></label>
                </div>
            </div>
        @else
            <button type="button" data-video-mute aria-label="Mute" title="Mute">
                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 9h4l5-4v14l-5-4H3zM15 9a4 4 0 0 1 0 6m2-8a7 7 0 0 1 0 10"/></svg>
            </button>
        @endif
        <button type="button" data-video-fullscreen aria-label="Full Screen" title="Full Screen">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path data-video-expand-icon d="M4 9V4h5m6 0h5v5m0 6v5h-5m-6 0H4v-5"/>
                @if($compact)<path data-video-collapse-icon d="M4 9h5V4m11 5h-5V4m5 11h-5v5M4 15h5v5"/>@endif
            </svg>
        </button>
    </div>
    <p data-video-status class="featured-video-status" role="status" hidden></p>
</div>
