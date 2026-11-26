<section class="wsm-panel wsm-schedule" id="hero-playlist" data-playlist-builder
    data-current="{{ json_encode($playlistItems, JSON_THROW_ON_ERROR) }}"
    data-preset="{{ json_encode($promoPreset, JSON_THROW_ON_ERROR) }}"
    data-library-url="{{ route('admin.media-library.index', [], false) }}">
    <script src="{{ asset('js/home-hero-playlist-admin.js') }}" defer></script>
    <style>.hhp-setting-switch{appearance:none;width:42px;height:24px;flex-shrink:0;border-radius:999px;background:#8995a3;border:1px solid #697887;cursor:pointer;position:relative;transition:background .2s}.hhp-setting-switch:before{content:"";position:absolute;width:18px;height:18px;top:2px;left:2px;border-radius:50%;background:#fff;transition:transform .2s}.hhp-setting-switch:checked{background:#315e48;border-color:#315e48}.hhp-setting-switch:checked:before{transform:translateX(18px)}.hhp-setting-switch:focus-visible{outline:2px solid #ba8c40;outline-offset:3px}@media(prefers-reduced-motion:reduce){.hhp-setting-switch,.hhp-setting-switch:before{transition:none}}</style>
    <div class="wsm-name">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 6h12M4 12h12M4 18h8M16 16l6 4-6 4z"/></svg>
        <div><p class="wsm-eyebrow">04 · Continuous attraction</p><h2>Video Playlist</h2></div>
    </div>
    <p>Choose 2–20 videos, reorder them and activate as one homepage attraction. Selected files become public.</p>
    <div class="wsm-actions">
        <button type="button" class="wsm-button wsm-outline" data-playlist-preset @disabled(!$promoPreset)>Load Learn Cambodia parts 1–9, 11</button>
        @if(!$promoPreset)<p>Preset unavailable: one or more exact filenames are missing or duplicated. Select the clips manually.</p>@endif
    </div>
    <div class="wsm-schedule-form">
        <label>Find library videos<input type="search" data-playlist-query maxlength="80" placeholder="Learn Cambodia part"></label>
        <button type="button" class="wsm-button wsm-outline" data-playlist-search>Search videos</button>
    </div>
    <div data-playlist-results></div>
    <div class="wsm-actions">
        <button type="button" data-playlist-prev class="wsm-button wsm-outline" disabled>Previous</button>
        <button type="button" data-playlist-next class="wsm-button wsm-outline" disabled>Next</button>
    </div>
    <p role="status" data-playlist-message></p>
    <form method="POST" action="{{ route('admin.appearance.home-hero-media.update') }}" data-playlist-form>
        @csrf<input type="hidden" name="source" value="playlist">
        <h3>Playback order</h3><div data-playlist-order></div>
        <label>Playlist title<input class="w-full rounded-lg border bg-transparent p-2 text-sm" name="label" maxlength="200" value="{{ ($current['kind'] ?? null) === 'playlist' ? $current['label'] : 'Learn Cambodia promotional playlist' }}"></label>
        <div class="wsm-grid" style="margin:16px 0">
            @foreach(['autoplay'=>'Autoplay (muted, when permitted)','muted'=>'Start muted','advance'=>'Automatically advance','loop'=>'Loop playlist','shuffle'=>'Shuffle playback order','controls'=>'Floating sound control','navigation'=>'Previous / Next controls','crop_bars'=>'Crop embedded black bars (zoom)','show_list'=>'Show video list'] as $key=>$label)
                <label class="wsm-history-row"><span>{{ $label }}</span><input type="hidden" name="options[{{ $key }}]" value="0"><input type="checkbox" role="switch" class="hhp-setting-switch" name="options[{{ $key }}]" value="1" @checked($playlistOptions[$key])></label>
            @endforeach
        </div>
        <p>The hero uses a responsive 16:9 cinema frame, without native controls or a bottom bar. Previous / Next can be enabled independently. Sound and pause are floating controls. Autoplay is muted and respects reduced-motion and data-saving preferences. Zoom crops the edges of all playlist clips; enable only if needed for embedded black bars.</p>
        <button class="wsm-button" type="submit" data-playlist-save disabled>Activate video playlist</button>
    </form>
    <noscript>Enable JavaScript to select and reorder playlist videos.</noscript>
</section>
