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
    const speed = player.querySelector<HTMLSelectElement>('[data-video-speed]');
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

    if (speed && volume && volumeValue) {
        const volumeMenu = player.querySelector<HTMLElement>('[data-video-volume-menu]')!;
        const volumeToggle = volumeMenu.querySelector<HTMLButtonElement>('[data-video-volume-toggle]')!;
        const volumePanel = volumeMenu.querySelector<HTMLElement>('[data-video-volume-panel]')!;
        const volumeSlider = volumePanel.querySelector<HTMLElement>('[data-video-volume-slider]')!;
        const deviceVolume = volumePanel.querySelector<HTMLElement>('[data-video-device-volume]')!;
        let idleTimer: ReturnType<typeof setTimeout>;
        let adjustingVolume = false;

        function setVolumePanel(open: boolean): void {
            volumePanel.hidden = !open;
            volumeToggle.setAttribute('aria-expanded', String(open));
        }

        const detectVolumeSupport = () => {
            const current = video.volume;
            const probe = current === 0.5 ? 0.6 : 0.5;
            video.volume = probe;
            const canAdjust = video.volume === probe;
            video.volume = current;
            volumeSlider.hidden = volumeValue.hidden = !canAdjust;
            deviceVolume.hidden = canAdjust;
        };

        function hideControls(): void {
            clearTimeout(idleTimer);
            if (adjustingVolume || volumePanel.matches(':has(:focus-visible)')) return;
            setVolumePanel(false);
            delete player.dataset.controlsActive;
        }

        function showControls(): void {
            if (player.hidden) return;
            clearTimeout(idleTimer);
            player.dataset.controlsActive = 'true';
            if (volumePanel.hidden) idleTimer = setTimeout(hideControls, 1200);
        }

        player.addEventListener('previewopen', () => {
            video.defaultPlaybackRate = 1;
            video.playbackRate = 1;
            speed.value = '1';
            status.hidden = true;
            setVolumePanel(false);
            showControls();
        });
        volumeToggle.addEventListener('click', () => {
            if (volumePanel.hidden) detectVolumeSupport();
            setVolumePanel(volumePanel.hidden);
            showControls();
        });
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
        volumeMenu.addEventListener('focusout', event => {
            if (event.relatedTarget && !volumeMenu.contains(event.relatedTarget as Node)) {
                setVolumePanel(false);
                showControls();
            }
        });
        player.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || volumePanel.hidden) return;
            event.preventDefault();
            event.stopPropagation();
            setVolumePanel(false);
            volumeToggle.focus();
            showControls();
        });
        player.addEventListener('pointermove', showControls);
        player.addEventListener('pointerdown', event => {
            if (!volumeMenu.contains(event.target as Node)) setVolumePanel(false);
            showControls();
        });
        player.addEventListener('pointerleave', event => {
            if (event.pointerType !== 'touch') hideControls();
        });
        player.addEventListener('pointercancel', hideControls);
        window.addEventListener('blur', hideControls);
        speed.addEventListener('change', () => {
            video.playbackRate = Number(speed.value);
            showControls();
        });
        video.addEventListener('ratechange', () => { speed.value = String(video.playbackRate); });
    }

    play.addEventListener('click', togglePlayback);
    video.addEventListener('click', togglePlayback);
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
        setLabel(fullscreen, document.fullscreenElement === player ? 'Exit fullscreen' : 'Fullscreen');
    });
    for (const event of ['loadedmetadata', 'durationchange', 'timeupdate', 'play', 'pause', 'ended', 'volumechange']) {
        video.addEventListener(event, update);
    }
    video.addEventListener('error', () => notify('This video is unavailable or uses an unsupported format.'));
    video.controls = false;
    controls.hidden = false;
    update();
});
