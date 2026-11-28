(() => {
    const players = new WeakMap();
    let currentAudio;
    const icon = (path) => `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${path}</svg>`;
    const playIcon = icon('<path d="m9 5 12 7-12 7z" fill="currentColor" stroke="none"/>');
    const pauseIcon = icon('<path d="M9 5v14m6-14v14" stroke-width="3"/>');
    const volumeIcon = icon('<path d="M11 5 6 9H3v6h3l5 4V5Zm4 4a4 4 0 0 1 0 6m3-9a8 8 0 0 1 0 12"/>');
    const mutedIcon = icon('<path d="M11 5 6 9H3v6h3l5 4V5Zm5 4 5 6m0-6-5 6"/>');
    const gearIcon = icon('<path d="M9.6 3 9 5.3l-1.3.7-2.2-.7-2 3.4 1.7 1.6v1.5l-1.7 1.6 2 3.4 2.2-.7 1.3.7.6 2.3h4l.6-2.3 1.3-.7 2.2.7 2-3.4-1.7-1.6v-1.5l1.7-1.6-2-3.4-2.2.7-1.3-.7-.6-2.3z" transform="translate(.4 1)"/><circle cx="12" cy="12" r="2.8"/>');
    const expandIcon = icon('<path d="M9 9 3.5 3.5M3.5 8.5v-5h5M15 9l5.5-5.5m-5 0h5v5M9 15l-5.5 5.5m0-5v5h5M15 15l5.5 5.5m0-5v5h-5"/>');
    const collapseIcon = icon('<path d="M3.5 3.5 9 9M4 9h5V4m11.5-.5L15 9m0-5v5h5M3.5 20.5 9 15m-5 0h5v5m11.5.5L15 15m0 5v-5h5"/>');

    function time(seconds) {
        if (!Number.isFinite(seconds)) return '––:––';
        const whole = Math.max(0, Math.floor(seconds));
        const minutes = Math.floor(whole / 60);
        const remainder = String(whole % 60).padStart(2, '0');
        return minutes < 60 ? `${String(minutes).padStart(2, '0')}:${remainder}`
            : `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}:${remainder}`;
    }

    function hasSource(audio) {
        return Boolean(audio.srcObject || audio.getAttribute('src')?.trim() || audio.querySelector('source[src]:not([src=""])'));
    }

    function mount(audio) {
        if (!audio.controls || audio.closest('[data-editor-visual], [contenteditable="true"]')) return;
        const existing = players.get(audio);
        if (existing) {
            if (!existing.panel.closest('.lc-audio-dialog') && existing.panel.previousElementSibling !== audio) audio.after(existing.panel);
            existing.syncVisibility();
            return;
        }
        if (!hasSource(audio)) return;

        const panel = document.createElement('div');
        panel.className = 'lc-audio';
        panel.setAttribute('role', 'group');
        const label = audio.getAttribute('aria-label') || (audio.closest('[data-exam-listening]') ? 'Listening audio' : 'Audio');
        panel.setAttribute('aria-label', label);
        panel.innerHTML = `
            <div class="lc-audio-bar">
                <button type="button" class="lc-audio-icon lc-audio-play" aria-label="Play" title="Play">${playIcon}</button>
                <input class="lc-audio-range lc-audio-seek" type="range" min="0" max="1000" value="0" aria-label="Playback position" disabled>
                <span class="lc-audio-time">00:00</span>
                <div class="lc-audio-volume-group">
                    <button type="button" class="lc-audio-icon lc-audio-mute" aria-label="Mute" title="Mute">${volumeIcon}</button>
                    <input class="lc-audio-range lc-audio-volume" type="range" min="0" max="100" step="1" value="100" aria-label="Volume">
                </div>
                <details class="lc-audio-settings">
                    <summary class="lc-audio-icon" aria-label="Playback settings" title="Playback settings">${gearIcon}</summary>
                    <div class="lc-audio-menu">
                        <label>Playback speed
                            <select class="lc-audio-speed">
                                <option value="0.5">0.5×</option><option value="0.75">0.75×</option><option value="1">1×</option>
                                <option value="1.25">1.25×</option><option value="1.5">1.5×</option><option value="2">2×</option>
                            </select>
                        </label>
                        <div class="lc-audio-jumps">
                            <button type="button" class="lc-audio-rewind" disabled>Back 10 seconds</button>
                            <button type="button" class="lc-audio-forward" disabled>Forward 10 seconds</button>
                        </div>
                    </div>
                </details>
                <button type="button" class="lc-audio-icon lc-audio-expand" aria-label="Expand player" title="Expand player">${expandIcon}</button>
            </div>
            <p class="lc-audio-status" role="status" hidden></p>`;

        const get = (part) => panel.querySelector('.lc-audio-' + part);
        const play = get('play');
        const mute = get('mute');
        const volume = get('volume');
        const speed = get('speed');
        const seek = get('seek');
        const rewind = get('rewind');
        const forward = get('forward');
        const clock = get('time');
        const settings = get('settings');
        const expand = get('expand');
        let dialog;
        const status = get('status');

        const notice = (message = '') => {
            status.textContent = message;
            status.hidden = !message;
        };
        const syncVisibility = () => {
            panel.hidden = audio.hidden || !hasSource(audio);
            if (panel.hidden && dialog?.open) dialog.close();
        };
        const syncPlayback = () => {
            const playing = !audio.paused && !audio.ended;
            play.setAttribute('aria-label', playing ? 'Pause' : 'Play');
            play.title = playing ? 'Pause' : 'Play';
            play.innerHTML = playing ? pauseIcon : playIcon;
        };
        const syncTime = () => {
            const duration = audio.duration;
            const canSeek = Number.isFinite(duration) && duration > 0;
            const position = canSeek ? Math.min(1, audio.currentTime / duration) : 0;
            seek.disabled = rewind.disabled = forward.disabled = !canSeek;
            seek.value = String(Math.round(position * 1000));
            seek.style.setProperty('--lc-fill', `${position * 100}%`);
            seek.setAttribute('aria-valuetext', `${time(audio.currentTime)} of ${time(duration)}`);
            clock.textContent = time(audio.currentTime || (canSeek ? duration : 0));
            clock.title = `${time(audio.currentTime)} / ${time(duration)}`;
        };
        const syncVolume = () => {
            const silent = audio.muted || audio.volume === 0;
            const value = audio.muted ? 0 : Math.round(audio.volume * 100);
            mute.setAttribute('aria-label', silent ? 'Unmute' : 'Mute');
            mute.title = silent ? 'Unmute' : 'Mute';
            mute.innerHTML = silent ? mutedIcon : volumeIcon;
            volume.value = String(value);
            volume.style.setProperty('--lc-fill', `${value}%`);
        };
        const jump = (seconds) => {
            if (Number.isFinite(audio.duration)) audio.currentTime = Math.max(0, Math.min(audio.duration, seconds));
        };

        play.addEventListener('click', async () => {
            if (!audio.paused) {
                audio.pause();
                return;
            }
            if (audio.error) audio.load();
            notice();
            try {
                await audio.play();
            } catch (error) {
                if (error.name !== 'AbortError') notice('Unable to play this recording. Try again.');
            }
        });
        rewind.addEventListener('click', () => jump(audio.currentTime - 10));
        forward.addEventListener('click', () => jump(audio.currentTime + 10));
        seek.addEventListener('input', () => jump(audio.duration * Number(seek.value) / 1000));
        speed.addEventListener('change', () => {
            audio.defaultPlaybackRate = audio.playbackRate = Number(speed.value);
        });
        mute.addEventListener('click', () => {
            if (audio.volume === 0) {
                audio.volume = 0.7;
                audio.muted = false;
            } else {
                audio.muted = !audio.muted;
            }
        });
        volume.addEventListener('input', () => {
            audio.volume = Number(volume.value) / 100;
            audio.muted = false;
            syncVolume();
        });
        settings.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !settings.open) return;
            settings.open = false;
            settings.querySelector('summary').focus();
            event.preventDefault();
        });
        const syncExpand = () => {
            const expanded = Boolean(dialog?.open);
            const action = expanded ? 'Exit expanded view' : 'Expand player';
            expand.setAttribute('aria-label', action);
            expand.title = action;
            expand.innerHTML = expanded ? collapseIcon : expandIcon;
        };
        const openDialog = () => {
            dialog = document.createElement('dialog');
            dialog.className = 'lc-audio-dialog';
            dialog.setAttribute('aria-label', label);
            dialog.append(panel);
            document.body.append(dialog);
            dialog.addEventListener('close', () => {
                if (audio.isConnected) audio.after(panel);
                dialog.remove();
                dialog = undefined;
                syncExpand();
                if (audio.isConnected && !panel.hidden) expand.focus();
            }, { once: true });
            dialog.showModal();
            syncExpand();
            expand.focus();
        };
        expand.addEventListener('click', () => {
            settings.open = false;
            if (dialog?.open) dialog.close();
            else openDialog();
        });
        ['loadedmetadata', 'durationchange', 'timeupdate', 'seeked', 'emptied'].forEach(event =>
            audio.addEventListener(event, syncTime));
        ['play', 'pause', 'ended', 'emptied'].forEach(event => audio.addEventListener(event, syncPlayback));
        audio.addEventListener('play', () => {
            if (currentAudio && audio !== currentAudio) currentAudio.pause();
            currentAudio = audio;
        });
        audio.addEventListener('playing', () => notice());
        audio.addEventListener('pause', () => { if (!audio.error) notice(); });
        audio.addEventListener('waiting', () => { if (!audio.paused) notice('Loading audio…'); });
        audio.addEventListener('loadstart', () => notice());
        audio.addEventListener('error', () => notice('This recording could not be loaded.'));
        audio.addEventListener('volumechange', syncVolume);
        audio.addEventListener('ratechange', () => { speed.value = String(audio.playbackRate); });

        audio.after(panel);
        audio.setAttribute('data-lc-audio-ready', '');
        players.set(audio, { panel, syncVisibility, closeDialog: () => dialog?.close() });
        syncVisibility();
        syncPlayback();
        syncTime();
        syncVolume();
        speed.value = String(audio.playbackRate);
    }

    const eachAudio = (element, callback) => {
        if (element instanceof HTMLAudioElement) callback(element);
        if (element instanceof HTMLSourceElement && element.parentElement instanceof HTMLAudioElement) callback(element.parentElement);
        element.querySelectorAll('audio[controls]').forEach(callback);
    };

    document.querySelectorAll('audio[controls]').forEach(mount);
    document.addEventListener('pointerdown', (event) => {
        document.querySelectorAll('.lc-audio-settings[open]').forEach((settings) => {
            if (!settings.contains(event.target)) settings.open = false;
        });
    });
    new MutationObserver((changes) => {
        for (const change of changes) {
            if (change.type === 'attributes') {
                const audio = change.target instanceof HTMLAudioElement ? change.target : change.target.parentElement;
                if (audio instanceof HTMLAudioElement) mount(audio);
                continue;
            }
            if (change.target instanceof HTMLAudioElement) mount(change.target);
            for (const node of change.addedNodes) {
                if (node instanceof Element && !node.closest('.lc-audio')) eachAudio(node, mount);
            }
            for (const node of change.removedNodes) {
                if (node instanceof Element) eachAudio(node, audio => {
                    if (!audio.isConnected) {
                        const entry = players.get(audio);
                        entry?.closeDialog();
                        entry?.panel.remove();
                        if (audio === currentAudio) {
                            audio.pause();
                            currentAudio = undefined;
                        }
                    }
                });
            }
        }
    }).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['src', 'controls', 'hidden'] });
})();
