@php
    $preview = $preview ?? false;
    $compact = $compact ?? false;
    $demo = $preview && !$promo['tracks'];
    $tracks = collect($promo['tracks'])->map(fn ($track) => [
        'title' => $track['title'],
        'artist' => $track['artist'],
        'transcript' => $track['transcript'],
        'transcript_cues' => $track['transcript_cues'] ?? [],
        'url' => \App\Support\PromoPlayer::mediaUrl($track['id'], $preview),
    ])->values()->all();
    if ($demo) {
        $tracks = [[
            'title' => 'Audio preview',
            'artist' => 'LearnCambodia',
            'transcript' => null,
            'url' => asset('media/audio-player-preview.mp3'),
        ]];
    }
    $first = $tracks[0];
    $heading = preg_split('/\s+(?=\S+$)/u', trim($promo['title']), 2);
    $transcriptFallback = 'A transcript will appear here when one is available for this track.';
@endphp

@if($preview)
    @pushOnce('workspace_styles')
        <link rel="stylesheet" href="{{ asset('css/promo-player.css') }}?v=3">
        <script src="{{ asset('js/promo-player.js') }}?v=4" defer></script>
    @endPushOnce
@else
    @pushOnce('public_styles')
        <link rel="stylesheet" href="{{ asset('css/promo-player.css') }}?v=3">
        <script src="{{ asset('js/promo-player.js') }}?v=4" defer></script>
    @endPushOnce
@endif

<section @class(['promo-player', 'promo-player--compact' => $compact, 'promo-player--image' => $promo['background']])
    aria-label="{{ $promo['title'] }}" data-promo-player data-volume="{{ $promo['volume'] }}" data-loop="{{ $promo['loop'] ? 1 : 0 }}"
    style="--promo-accent: {{ $promo['accent'] }}">
    @if($promo['background'])
        <img class="promo-player__background" alt="" loading="lazy" decoding="async"
            src="{{ \App\Support\PromoPlayer::mediaUrl($promo['background']['id'], $preview) }}">
    @endif
    <div class="promo-player__deck">
        <header class="promo-player__head">
            <span class="promo-player__lamp" aria-hidden="true"></span>
            <h2>{{ $heading[0] }}@isset($heading[1]) <span>{{ $heading[1] }}</span>@endisset</h2>
            @if($promo['link_url'])
                <a class="promo-player__link" href="{{ $promo['link_url'] }}">{{ $promo['link_label'] }} <span aria-hidden="true">↗</span></a>
            @endif
        </header>
        <audio data-promo-audio hidden preload="none" src="{{ $first['url'] }}"></audio>
        <noscript><audio controls preload="none" src="{{ $first['url'] }}" aria-label="{{ $first['title'] }}"></audio></noscript>
        <div class="promo-player__grid" data-promo-ui hidden>
            <div class="promo-player__rack">
                <div class="promo-player__turntable" aria-hidden="true"><div class="promo-player__vinyl"></div></div>
                <div class="promo-player__queue" aria-label="Playlist">
                    <p>Playlist &amp; Queue</p>
                    <ol>
                        @foreach($tracks as $track)
                            <li><button type="button" data-promo-track="{{ $loop->index }}" @if($loop->first) aria-current="true" @endif>
                                <span aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span>{{ $track['title'] }}
                            </button></li>
                        @endforeach
                    </ol>
                </div>
            </div>
            <div class="promo-player__main">
                <div class="promo-player__screen">
                    <div class="promo-player__track-copy" aria-live="polite">
                        <span class="promo-player__badge">Campaign audio</span>
                        <h3 data-promo-title>{{ $first['title'] }}</h3>
                        <p data-promo-byline @unless($first['artist']) hidden @endunless>Artist: <span data-promo-artist>{{ $first['artist'] }}</span></p>
                    </div>
                    <canvas width="640" height="210" data-promo-wave aria-hidden="true"></canvas>
                    <div class="promo-player__timeline">
                        <span data-promo-current>0:00</span>
                        <span data-promo-time-pair aria-hidden="true">0:00 / –:–</span>
                        <span data-promo-duration>–:–</span>
                        <input type="range" min="0" max="1000" value="0" data-promo-seek aria-label="Playback position">
                    </div>
                </div>
                <div class="promo-player__transport">
                    <div class="promo-player__tone" data-promo-tone>
                        <label>Bass <input type="range" min="-8" max="8" value="0" data-promo-bass disabled></label>
                        <label>Treble <input type="range" min="-8" max="8" value="0" data-promo-treble disabled></label>
                    </div>
                    <label class="promo-player__volume">
                        <span class="promo-player__dial">
                            <input type="range" min="0" max="100" value="{{ $promo['volume'] }}" data-promo-volume>
                        </span>
                        Volume
                    </label>
                    <div class="promo-player__buttons">
                        <button type="button" class="promo-player__play" data-promo-play aria-label="Play" title="Play">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path data-promo-play-icon d="M8 4.5v15l12-7.5z"/><path data-promo-pause-icon d="M6 5h4v14H6zm8 0h4v14h-4z" hidden/></svg>
                        </button>
                        <button type="button" data-promo-stop aria-label="Stop" title="Stop">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6h12v12H6z"/></svg>
                        </button>
                        <button type="button" data-promo-prev aria-label="Previous track" title="Previous track">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5 2 12l10 7v-6l10 6V5l-10 6z"/></svg>
                        </button>
                        <button type="button" data-promo-next aria-label="Next track" title="Next track">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v6L2 5v14l10-6v6l10-7z"/></svg>
                        </button>
                    </div>
                </div>
                <p class="promo-player__error" data-promo-error role="status" hidden>Playback failed. Choose another track or try again.</p>
            </div>
            <div class="promo-player__effects">
                <div class="promo-player__meter">
                    <p>Visualizer &amp; Effects</p>
                    <svg viewBox="0 0 150 70" aria-hidden="true">
                        <path d="M21 47 Q75 -2 129 47" fill="none" stroke="currentColor" stroke-width="2"/>
                        <path d="M22 47l4 5m9-15 4 6m10-14 3 7m10-11 2 7m11-9v7m13-5-2 7m15-3-3 7m17 1-4 6m18 4-5 5" fill="none" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M104 33 Q120 40 129 47" fill="none" stroke="#a42612" stroke-width="2"/>
                        <g fill="currentColor" font-family="monospace" font-size="7" text-anchor="middle">
                            <text x="27" y="35">−20</text><text x="50" y="23">−10</text><text x="74" y="18">−3</text>
                            <text x="96" y="21">0</text><text x="119" y="30" fill="#a42612">+3</text><text x="75" y="52">VU</text>
                        </g>
                        <path d="M75 61 29 44" data-promo-needle transform="rotate(0 75 61)" stroke="#362411" stroke-width="2"/>
                        <circle cx="75" cy="61" r="4" fill="#362411"/>
                    </svg>
                    <div class="promo-player__presets" role="group" aria-label="Sound effects">
                        <button type="button" data-promo-preset="bass" aria-pressed="false" disabled>Bass</button>
                        <button type="button" data-promo-preset="treble" aria-pressed="false" disabled>Treble</button>
                        <button type="button" data-promo-preset="cinematic" aria-pressed="false" disabled>Cinematic</button>
                    </div>
                    <button class="promo-player__spatial" type="button" role="switch" aria-checked="false" data-promo-spatial disabled>
                        3D Audio <span aria-hidden="true"></span>
                    </button>
                </div>
                <details class="promo-player__transcript" data-promo-transcript data-empty-text="{{ $transcriptFallback }}" open>
                    <summary>Transcript</summary>
                    <p>{{ $first['transcript'] ?: $transcriptFallback }}</p>
                </details>
            </div>
        </div>
        <script type="application/json" data-promo-tracks>{!! \Illuminate\Support\Js::encode($tracks) !!}</script>
    </div>
</section>
