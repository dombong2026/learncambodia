import styles from '../css/lesson-media-player.css?inline';

const mediaPath = /^\/editor-media\/[1-9]\d*$/;
const playerTag = 'lesson-media-player';
const speeds = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
const icons = {
    play: '<path d="M8 5.5v13l11-6.5z" fill="currentColor" stroke="none"/>',
    pause: '<path d="M9 5v14M15 5v14" stroke-width="2.8"/>',
    volume: '<path d="M3 9v6h4l5 4V5L7 9H3m13 0a4 4 0 0 1 0 6m2.5-8a7 7 0 0 1 0 10"/>',
    mute: '<path d="M3 9v6h4l5 4V5L7 9H3m13 1 5 5m0-5-5 5"/>',
    speed: '<path d="M4.9 17a9 9 0 1 1 14.2 0M12 12l3.5-4M7 12h.01M12 7h.01M17 12h.01"/><circle cx="12" cy="12" r="1"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M10 3h4l.5 2.2 1.7 1 2.2-.7 2 3.5-1.6 1.5v2l1.6 1.5-2 3.5-2.2-.7-1.7 1-.5 2.2h-4l-.5-2.2-1.7-1-2.2.7-2-3.5 1.6-1.5v-2L3.6 9l2-3.5 2.2.7 1.7-1L10 3Z" transform="translate(0 1)"/>',
    expand: '<path d="M9 4H4v5m11-5h5v5m0 6v5h-5M4 15v5h5"/>',
    collapse: '<path d="M4 9h5V4m11 5h-5V4m5 11h-5v5M4 15h5v5"/>',
};

function icon(name: keyof typeof icons): string {
    return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + icons[name] + '</svg>';
}

const template = document.createElement('template');
template.innerHTML = `
    <style>${styles}</style>
    <div class="surface">
        <slot></slot>
        <div class="wave" aria-hidden="true">${[21,45,67,35,85,100,65,41,75,52,25].map((height, i) =>
            `<i style="--height:${height}%;--delay:${i * -65}ms"></i>`).join('')}</div>
        <button type="button" class="center" data-play aria-label="Play">${icon('play')}</button>
        <div class="loading" aria-hidden="true" hidden></div>
        <span class="sr" role="status" data-loading></span>
        <div class="message" data-error role="status" hidden>
            <span>This media could not play. Check your connection or open the file in a browser that supports its format.</span>
            <button type="button" data-retry>Try again</button>
        </div>
        <div class="overlay">
            <input type="range" data-seek min="0" max="1" step="0.01" value="0" aria-label="Playback position" disabled>
            <div class="row">
                <button type="button" data-play aria-label="Play" title="Play">${icon('play')}</button>
                <div class="volume">
                    <button type="button" data-toggle="volume" aria-label="Volume" title="Volume" aria-controls="volume" aria-expanded="false">${icon('volume')}</button>
                    <div class="panel volume-panel" id="volume" hidden>
                        <input type="range" data-volume min="0" max="100" step="1" aria-label="Volume level">
                        <span data-volume-note hidden>Use device volume</span>
                        <button type="button" data-mute aria-label="Mute" title="Mute">${icon('mute')}</button>
                    </div>
                </div>
                <span class="time" data-time>0:00 / –:––</span>
                <div class="spacer"></div>
                <button type="button" data-toggle="speed" aria-label="Playback speed" title="Playback speed" aria-controls="speed" aria-expanded="false">${icon('speed')}</button>
                <button type="button" data-toggle="settings" aria-label="Playback settings" title="Playback settings" aria-controls="settings" aria-expanded="false">${icon('settings')}</button>
                <button type="button" data-fullscreen aria-label="Full screen" title="Full screen">${icon('expand')}</button>
            </div>
            <div id="speed" class="panel options" role="group" aria-label="Playback speed" hidden>
                <span class="panel-title">Playback speed</span>
                <div class="rates">${speeds.map(rate =>
                    `<button type="button" data-rate="${rate}" aria-pressed="false">${rate}×</button>`).join('')}</div>
            </div>
            <div id="settings" class="panel options" role="group" aria-label="Playback settings" hidden>
                <div class="setting" data-resolution-row><span>Resolution</span><output data-resolution>Source</output></div>
                <label class="setting"><span>Loop playback</span><input type="checkbox" data-loop></label>
            </div>
        </div>
    </div>`;

function time(seconds: number): string {
    const total = Math.floor(seconds);
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor(total / 60) % 60;
    const remainder = String(total % 60).padStart(2, '0');
    return hours ? `${hours}:${String(minutes).padStart(2, '0')}:${remainder}` : `${minutes}:${remainder}`;
}

class LessonMediaPlayer extends HTMLElement {
    private listeners?: AbortController;
    private media?: HTMLMediaElement;
    private idle?: ReturnType<typeof setTimeout>;

    connectedCallback(): void {
        // A pasted or cloned wrapper can be connected before its children arrive.
        queueMicrotask(() => {
            const media = this.querySelector<HTMLMediaElement>(':scope > video, :scope > audio');
            if (!this.isConnected || this.listeners || !media || !hasSource(media)) return;
            this.media = media;
            this.listeners = new AbortController();
            this.mount(media, this.listeners.signal);
        });
    }

    disconnectedCallback(): void {
        this.media?.pause();
        this.listeners?.abort();
        this.listeners = undefined;
        clearTimeout(this.idle);
        delete this.dataset.expanded;
        delete this.dataset.idle;
    }

    private mount(media: HTMLMediaElement, signal: AbortSignal): void {
        const shadow = this.shadowRoot ?? this.attachShadow({ mode: 'open' });
        shadow.replaceChildren(template.content.cloneNode(true));
        const get = <T extends HTMLElement>(selector: string): T => shadow.querySelector<T>(selector)!;
        const play = Array.from(shadow.querySelectorAll<HTMLButtonElement>('[data-play]'));
        const knobs = Array.from(shadow.querySelectorAll<HTMLButtonElement>('[data-toggle]'));
        const seek = get<HTMLInputElement>('[data-seek]');
        const volume = get<HTMLInputElement>('[data-volume]');
        const mute = get<HTMLButtonElement>('[data-mute]');
        const full = get<HTMLButtonElement>('[data-fullscreen]');
        const retry = get<HTMLButtonElement>('[data-retry]');
        const loading = get<HTMLElement>('.loading');
        const message = get<HTMLElement>('[data-error]');
        const loop = get<HTMLInputElement>('[data-loop]');
        let activePanel: HTMLButtonElement | undefined;
        let seeking = false;
        let waiting = false;
        let failed = Boolean(media.error);
        let requested = false;

        this.contentEditable = 'false';
        this.dataset.kind = media instanceof HTMLVideoElement ? 'video' : 'audio';
        media.controls = false;
        media.preload = this.dataset.kind === 'video' ? 'metadata' : 'none';
        if (media instanceof HTMLVideoElement) media.playsInline = true;
        get('[data-resolution-row]').hidden = this.dataset.kind === 'audio';

        const closePanel = (restore = false): void => {
            if (!activePanel) return;
            get('#' + activePanel.dataset.toggle).hidden = true;
            activePanel.setAttribute('aria-expanded', 'false');
            if (restore) activePanel.focus({ preventScroll: true });
            activePanel = undefined;
        };

        const wake = (): void => {
            clearTimeout(this.idle);
            delete this.dataset.idle;
            if (media.paused) return;
            this.idle = setTimeout(() => {
                if (media.paused || shadow.querySelector(':focus-visible') || seeking || activePanel) return;
                this.dataset.idle = '';
            }, 5000);
        };

        const updateProgress = (): void => {
            const finite = Number.isFinite(media.duration) && media.duration > 0;
            const duration = finite ? media.duration : 0;
            const position = seeking ? Number(seek.value) : media.currentTime;
            seek.disabled = !finite;
            seek.max = String(duration || 1);
            if (!seeking) seek.value = String(position);
            seek.style.setProperty('--played', (finite ? (position / duration) * 100 : 0) + '%');
            let buffered = 0;
            for (let i = 0; i < media.buffered.length; i++) {
                if (media.buffered.start(i) <= media.currentTime) buffered = media.buffered.end(i);
            }
            seek.style.setProperty('--buffered', (finite ? Math.min(buffered / duration, 1) * 100 : 0) + '%');
            const elapsed = time(position);
            get('[data-time]').textContent = elapsed + ' / ' + (finite ? time(duration) : '–:––');
            seek.setAttribute('aria-valuetext', elapsed + (finite ? ' of ' + time(duration) : ''));
            if (media instanceof HTMLVideoElement) {
                get('[data-resolution]').textContent = media.videoWidth > 0
                    ? `${media.videoWidth} × ${media.videoHeight}` : 'Source';
            }
        };

        const update = (): void => {
            const playing = !media.paused && !media.ended;
            this.toggleAttribute('data-playing', playing);
            for (const button of play) {
                button.innerHTML = icon(playing ? 'pause' : 'play');
                button.setAttribute('aria-label', playing ? 'Pause' : 'Play');
                button.title = playing ? 'Pause' : 'Play';
            }
            play[0].hidden = playing || waiting || failed;
            loading.hidden = !waiting || failed;
            get('[data-loading]').textContent = waiting && !failed ? 'Loading media' : '';
            message.hidden = !failed;
            const muted = media.muted || media.volume === 0;
            get<HTMLButtonElement>('[data-toggle="volume"]').innerHTML = icon(muted ? 'mute' : 'volume');
            mute.innerHTML = icon(muted ? 'volume' : 'mute');
            mute.title = muted ? 'Unmute' : 'Mute';
            mute.setAttribute('aria-label', mute.title);
            volume.value = String(media.muted ? 0 : Math.round(media.volume * 100));
            loop.checked = media.loop;
            const speed = get<HTMLButtonElement>('[data-toggle="speed"]');
            speed.title = `Playback speed (${media.playbackRate}×)`;
            for (const option of Array.from(shadow.querySelectorAll<HTMLButtonElement>('[data-rate]'))) {
                option.setAttribute('aria-pressed', String(Number(option.dataset.rate) === media.playbackRate));
            }
            const expanded = document.fullscreenElement === this || this.hasAttribute('data-expanded');
            full.innerHTML = icon(expanded ? 'collapse' : 'expand');
            full.title = expanded ? 'Exit full screen' : 'Full screen';
            full.setAttribute('aria-label', full.title);
            updateProgress();
        };

        const begin = async (): Promise<void> => {
            if (requested) return;
            requested = true;
            if (failed) media.load();
            failed = false;
            waiting = media.readyState < HTMLMediaElement.HAVE_FUTURE_DATA;
            update();
            try {
                await media.play();
            } catch (error) {
                if (signal.aborted) return;
                waiting = false;
                failed = !(error instanceof DOMException && error.name === 'AbortError');
                update();
            } finally {
                requested = false;
            }
        };

        const togglePlayback = (): void => {
            if (media.paused || media.ended) void begin();
            else media.pause();
        };

        for (const button of play) button.addEventListener('click', togglePlayback);
        media.addEventListener('click', togglePlayback, { signal });
        retry.addEventListener('click', () => { void begin(); });
        for (const knob of knobs) {
            knob.addEventListener('click', () => {
                const opening = activePanel !== knob;
                closePanel();
                if (opening) {
                    get('#' + knob.dataset.toggle).hidden = false;
                    knob.setAttribute('aria-expanded', 'true');
                    activePanel = knob;
                    if (knob.dataset.toggle === 'volume') {
                        const current = media.volume;
                        const probe = current === 0.5 ? 0.75 : 0.5;
                        media.volume = probe;
                        volume.hidden = media.volume !== probe;
                        media.volume = current;
                        get('[data-volume-note]').hidden = !volume.hidden;
                    }
                }
                wake();
            });
        }
        shadow.addEventListener('focusout', event => {
            if (event instanceof FocusEvent && event.relatedTarget instanceof Node && shadow.contains(event.relatedTarget)) return;
            closePanel();
            wake();
        }, { signal });
        for (const option of Array.from(shadow.querySelectorAll<HTMLButtonElement>('[data-rate]'))) {
            option.addEventListener('click', () => {
                media.playbackRate = Number(option.dataset.rate);
                closePanel(true);
                wake();
            });
        }
        loop.addEventListener('change', () => { media.loop = loop.checked; });
        mute.addEventListener('click', () => {
            const muted = media.muted || media.volume === 0;
            if (media.volume === 0) media.volume = 0.75;
            media.muted = !muted;
        });
        volume.addEventListener('input', () => {
            media.volume = Number(volume.value) / 100;
            media.muted = false;
        });
        seek.addEventListener('pointerdown', () => { seeking = true; });
        seek.addEventListener('input', () => {
            media.currentTime = Number(seek.value);
            updateProgress();
            wake();
        });
        const endSeek = (): void => { seeking = false; wake(); };
        for (const event of ['pointerup', 'pointercancel', 'blur', 'change']) seek.addEventListener(event, endSeek);

        full.addEventListener('click', async () => {
            closePanel();
            if (document.fullscreenElement === this) await document.exitFullscreen();
            else if (this.hasAttribute('data-expanded')) delete this.dataset.expanded;
            else if (this.requestFullscreen && document.fullscreenEnabled && !document.fullscreenElement) {
                try { await this.requestFullscreen(); }
                catch { if (this.isConnected) this.dataset.expanded = ''; }
            } else this.dataset.expanded = '';
            update();
            wake();
        });

        this.addEventListener('fullscreenchange', () => { update(); wake(); }, { signal });
        for (const event of ['pointermove', 'pointerenter', 'pointerleave', 'pointerdown', 'focusin']) {
            this.addEventListener(event, wake, { signal });
        }
        this.addEventListener('pointerleave', event => {
            if (event.pointerType === 'mouse' && !shadow.querySelector(':focus-visible')) closePanel();
            wake();
        }, { signal });
        shadow.addEventListener('keydown', event => {
            if (!(event instanceof KeyboardEvent)) return;
            if (event.key === 'Escape' && (activePanel || this.hasAttribute('data-expanded'))) {
                event.preventDefault();
                event.stopPropagation();
                if (activePanel) closePanel(true);
                else delete this.dataset.expanded;
                update();
                wake();
            }
        }, { signal });
        // Slider and checkbox changes belong to playback, never the lesson form.
        for (const event of ['input', 'change']) shadow.addEventListener(event, e => e.stopPropagation(), { signal });

        for (const event of ['timeupdate', 'durationchange', 'loadedmetadata', 'progress', 'resize']) {
            media.addEventListener(event, updateProgress, { signal });
        }
        for (const event of ['volumechange', 'ratechange']) media.addEventListener(event, update, { signal });
        for (const event of ['play', 'playing', 'pause', 'ended', 'waiting', 'stalled', 'canplay', 'error', 'emptied']) {
            media.addEventListener(event, () => {
                if (event === 'error') failed = true;
                waiting = !media.paused && ['waiting', 'stalled', 'play'].includes(event)
                    && media.readyState < HTMLMediaElement.HAVE_FUTURE_DATA;
                update();
                wake();
            }, { signal });
        }
        update();
        wake();
    }
}

function hasSource(media: HTMLMediaElement): boolean {
    const own = media.getAttribute('src');
    return own ? mediaPath.test(own) : Array.from(media.querySelectorAll('source')).some(source =>
        mediaPath.test(source.getAttribute('src') ?? ''));
}

customElements.define(playerTag, LessonMediaPlayer);

export function enhanceLessonMedia(scope: ParentNode): void {
    scope.querySelectorAll<HTMLMediaElement>('video, audio').forEach(media => {
        if (!hasSource(media) || media.closest(playerTag)) return;
        const player = document.createElement(playerTag);
        media.before(player);
        player.append(media);
    });
}

// This module is shared by the workspace and the protected student lesson view.
document.querySelectorAll('.lesson-rich-content').forEach(enhanceLessonMedia);
