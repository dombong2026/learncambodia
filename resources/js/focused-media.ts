import '@videojs/html/audio/player';
import '@videojs/html/audio/skin';
import '@videojs/html/video/player';
import '@videojs/html/video/skin';

// Native controls remain available if the enhanced player cannot load.
document.querySelectorAll<HTMLMediaElement>(
    'video-player[data-focused-media] video, audio-player[data-focused-media] audio',
).forEach((media) => {
    const skin = media.parentElement;
    if (skin?.shadowRoot) media.controls = false;
});
