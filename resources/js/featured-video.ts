function formatTime(seconds: number): string {
    const total = Number.isFinite(seconds) ? Math.floor(seconds) : 0;
    const part = String(total % 60).padStart(2, '0');
    const minutes = Math.floor(total / 60);
    return minutes >= 60 ? `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}:${part}` : `${minutes}:${part}`;
}

document.querySelectorAll<HTMLElement>('[data-featured-video]').forEach(player => {
    const video = player.querySelector('video')!;
    const controls = player.querySelector<HTMLElement>('[data-video-controls]')!;
    const play = player.querySelector<HTMLButtonElement>('[data-video-play]')!;
    const mute = player.querySelector<HTMLButtonElement>('[data-video-mute]')!;
    const fullscreen = player.querySelector<HTMLButtonElement>('[data-video-fullscreen]')!;
    const seek = player.querySelector<HTMLInputElement>('[data-video-seek]')!;
    const time = player.querySelector<HTMLElement>('[data-video-time]')!;
    const status = player.querySelector<HTMLElement>('[data-video-status]')!;
    const volume = player.querySelector<HTMLInputElement>('[data-video-volume]');
    const volumeValue = player.querySelector<HTMLOutputElement>('[data-video-volume-value]');
    let previousVolume = video.volume || 1;

    function setLabel(button: HTMLButtonElement, label: string): void {
        button.title = label;
        button.setAttribute('aria-label', label);
    }

    function update(): void {
        const duration = video.duration;
        const seekable = Number.isFinite(duration) && duration > 0;
        seek.disabled = !seekable;
        seek.value = String(seekable ? Math.round(video.currentTime / duration * 1000) : 0);
        if (volume) seek.style.setProperty('--video-progress', Number(seek.value) / 10 + '%');
        seek.setAttribute('aria-valuetext', formatTime(video.currentTime));
        time.textContent = formatTime(video.currentTime) + ' / ' + formatTime(duration);
        setLabel(play, video.paused ? 'Play' : 'Pause');
        const silent = video.muted || (volume !== null && video.volume === 0);
        setLabel(mute, silent ? 'Unmute' : 'Mute');
        player.dataset.playing = String(!video.paused);
        player.dataset.muted = String(silent);
        if (volume && volumeValue) {
            const level = Math.round((silent ? 0 : video.volume) * 100);
            volume.value = String(level);
            volume.setAttribute('aria-valuetext', level + '%');
            volume.style.setProperty('--video-progress', level + '%');
            volumeValue.textContent = level + '%';
        }
    }

    function notify(message: string): void {
        status.textContent = message;
        status.hidden = false;
    }

    function togglePlayback(): void {
        if (video.paused) {
            status.hidden = true;
            void video.play().catch(() => notify('This video could not be played.'));
        } else video.pause();
    }

    if (volume && volumeValue) {
        const volumePanel = player.querySelector<HTMLElement>('[data-video-volume-panel]')!;
        const volumeSlider = volumePanel.querySelector<HTMLElement>('[data-video-volume-slider]')!;
        const deviceVolume = volumePanel.querySelector<HTMLElement>('[data-video-device-volume]')!;
        const rateOptions = Array.from(player.querySelectorAll<HTMLButtonElement>('[data-video-rate]'));
        const speedToggle = player.querySelector<HTMLButtonElement>('[aria-controls="preview-speed-panel"]')!;
        const loop = player.querySelector<HTMLInputElement>('[data-video-loop]')!;
        let idleTimer: ReturnType<typeof setTimeout>;
        let adjustingVolume = false;
        let openMenu: { toggle: HTMLButtonElement; panel: HTMLElement } | undefined;

        function closeMenu(restoreFocus = false): void {
            if (!openMenu) return;
            const { toggle, panel } = openMenu;
            openMenu = undefined;
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            if (restoreFocus) toggle.focus({ preventScroll: true });
        }

        const detectVolumeSupport = () => {
            const current = video.volume;
            const probe = current === 0.5 ? 0.75 : 0.5;
            video.volume = probe;
            const canAdjust = video.volume === probe;
            video.volume = current;
            volumeSlider.hidden = volumeValue.hidden = !canAdjust;
            deviceVolume.hidden = canAdjust;
        };

        function hideControls(): void {
            clearTimeout(idleTimer);
            if (adjustingVolume || openMenu) return;
            closeMenu();
            delete player.dataset.controlsActive;
        }

        function showControls(): void {
            if (player.hidden) return;
            clearTimeout(idleTimer);
            player.dataset.controlsActive = 'true';
            if (!openMenu) idleTimer = setTimeout(hideControls, 1800);
        }

        const updateSpeed = () => {
            speedToggle.title = `Speed (${video.playbackRate}×)`;
            for (const option of rateOptions) {
                option.setAttribute('aria-pressed', String(Number(option.dataset.videoRate) === video.playbackRate));
            }
        };

        player.addEventListener('previewopen', () => {
            video.defaultPlaybackRate = 1;
            video.playbackRate = 1;
            updateSpeed();
            status.hidden = true;
            closeMenu();
            showControls();
        });
        for (const toggle of Array.from(player.querySelectorAll<HTMLButtonElement>('[data-video-toggle]'))) {
            const panel = toggle.parentElement!.querySelector<HTMLElement>('[data-video-panel]')!;
            toggle.addEventListener('click', () => {
                const wasOpen = openMenu?.toggle === toggle;
                closeMenu();
                if (!wasOpen) {
                    if (panel === volumePanel) detectVolumeSupport();
                    panel.hidden = false;
                    toggle.setAttribute('aria-expanded', 'true');
                    openMenu = { toggle, panel };
                }
                showControls();
            });
        }
        volume.addEventListener('pointerdown', () => { adjustingVolume = true; });
        const finishVolumeAdjustment = () => {
            if (!adjustingVolume) return;
            adjustingVolume = false;
            showControls();
        };
        window.addEventListener('pointerup', finishVolumeAdjustment);
        window.addEventListener('pointercancel', finishVolumeAdjustment);
        volume.addEventListener('input', () => {
            video.volume = Number(volume.value) / 100;
            video.muted = false;
            if (video.volume > 0) previousVolume = video.volume;
            update();
            showControls();
        });
        volume.addEventListener('keydown', event => {
            if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;
            event.preventDefault();
            if (event.key === 'ArrowUp') volume.stepUp();
            else volume.stepDown();
            volume.dispatchEvent(new Event('input'));
        });
        player.addEventListener('focusout', event => {
            if (event.relatedTarget && openMenu && !openMenu.toggle.parentElement?.contains(event.relatedTarget as Node)) {
                closeMenu();
                showControls();
            }
        });
        player.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || !openMenu) return;
            event.preventDefault();
            event.stopPropagation();
            closeMenu(true);
            showControls();
        });
        player.addEventListener('pointerenter', showControls);
        player.addEventListener('pointermove', showControls);
        // An open popover remains interactive while the pointer crosses its edge.
        // A click outside the player dismisses it explicitly.
        document.addEventListener('pointerdown', event => {
            if (openMenu && event.target instanceof Node && !player.contains(event.target)) {
                closeMenu();
                hideControls();
            }
        });
        player.addEventListener('pointerdown', event => {
            if (openMenu && !openMenu.toggle.parentElement?.contains(event.target as Node)) closeMenu();
            showControls();
        });
        player.addEventListener('pointerleave', event => {
            if (event.pointerType !== 'touch') hideControls();
        });
        player.addEventListener('pointercancel', hideControls);
        window.addEventListener('blur', () => {
            closeMenu();
            hideControls();
        });
        for (const option of rateOptions) {
            option.addEventListener('click', () => {
                video.playbackRate = Number(option.dataset.videoRate);
                updateSpeed();
                closeMenu(option.matches(':focus-visible'));
                showControls();
            });
        }
        video.addEventListener('ratechange', updateSpeed);
        loop.addEventListener('change', () => { video.loop = loop.checked; });
    }

    play.addEventListener('click', togglePlayback);
    if (player.hasAttribute('data-compact-video')) {
        // Native video hit-testing can obscure overlays on WebKit. The wrapper
        // owns surface clicks while actual controls keep their own handlers.
        player.addEventListener('click', event => {
            if (event.target === player || event.target === video) togglePlayback();
        });
    } else {
        video.addEventListener('click', togglePlayback);
    }
    mute.addEventListener('click', () => {
        if (volume && video.volume === 0) {
            video.volume = previousVolume;
            video.muted = false;
        } else {
            video.muted = !video.muted;
        }
    });
    seek.addEventListener('input', () => {
        if (!seek.disabled) video.currentTime = Number(seek.value) / 1000 * video.duration;
    });
    const mobileVideo = video as HTMLVideoElement & { webkitEnterFullscreen?: () => void };
    fullscreen.hidden = !player.requestFullscreen && !mobileVideo.webkitEnterFullscreen;
    fullscreen.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement === player) await document.exitFullscreen();
            else if (player.requestFullscreen) await player.requestFullscreen();
            else mobileVideo.webkitEnterFullscreen?.();
        } catch {
            notify('Fullscreen is unavailable on this device.');
        }
    });
    document.addEventListener('fullscreenchange', () => {
        const active = document.fullscreenElement === player;
        setLabel(fullscreen, active ? 'Exit Full Screen' : 'Full Screen');
        player.dataset.fullscreen = String(active);
    });
    for (const event of ['loadedmetadata', 'durationchange', 'timeupdate', 'play', 'pause', 'ended', 'volumechange']) {
        video.addEventListener(event, update);
    }
    video.addEventListener('error', () => notify('This video is unavailable or uses an unsupported format.'));
    video.controls = false;
    controls.hidden = false;
    update();
});
