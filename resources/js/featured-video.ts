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

    function setLabel(button: HTMLButtonElement, label: string): void {
        button.title = label;
        button.setAttribute('aria-label', label);
    }

    function update(): void {
        const duration = video.duration;
        const seekable = Number.isFinite(duration) && duration > 0;
        seek.disabled = !seekable;
        seek.value = String(seekable ? Math.round(video.currentTime / duration * 1000) : 0);
        seek.setAttribute('aria-valuetext', formatTime(video.currentTime));
        time.textContent = formatTime(video.currentTime) + ' / ' + formatTime(duration);
        setLabel(play, video.paused ? 'Play' : 'Pause');
        setLabel(mute, video.muted ? 'Unmute' : 'Mute');
        player.dataset.playing = String(!video.paused);
        player.dataset.muted = String(video.muted);
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

    play.addEventListener('click', togglePlayback);
    video.addEventListener('click', togglePlayback);
    mute.addEventListener('click', () => { video.muted = !video.muted; });
    seek.addEventListener('input', () => { video.currentTime = Number(seek.value) / 1000 * video.duration; });
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
