(() => {
    'use strict';
    for (const root of document.querySelectorAll('[data-hero-playlist]')) {
        const clips = JSON.parse(root.dataset.clips);
        const options = JSON.parse(root.dataset.options);
        const video = root.querySelector('video');
        const prev = root.querySelector('[data-hhp-prev]');
        const next = root.querySelector('[data-hhp-next]');
        const play = root.querySelector('[data-hhp-play]');
        const mute = root.querySelector('[data-hhp-mute]');
        const position = root.querySelector('[data-hhp-position]');
        const status = root.querySelector('[data-hhp-status]');
        const order = clips.map((_, index) => index);
        if (!order.length) continue;
        if (options.shuffle) {
            for (let i = order.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [order[i], order[j]] = [order[j], order[i]];
            }
        }
        let index = 0, generation = 0;
        const tell = message => { status.textContent = message; status.hidden = !message; };
        const sync = () => {
            root.dataset.playing = String(!video.paused && !video.ended);
            if (position) position.textContent = (index + 1) + ' / ' + order.length;
            if (prev) prev.disabled = !options.loop && index === 0;
            if (next) next.disabled = !options.loop && index === order.length - 1;
            const paused = video.paused || video.ended;
            play.setAttribute('aria-label', paused ? 'Play video' : 'Pause video');
            play.title = paused ? 'Play video' : 'Pause video';
            root.querySelector('[data-hhp-play-icon]').toggleAttribute('hidden', !paused);
            root.querySelector('[data-hhp-pause-icon]').toggleAttribute('hidden', paused);
            mute.setAttribute('aria-label', video.muted ? 'Unmute video' : 'Mute video');
            mute.title = video.muted ? 'Unmute video' : 'Mute video';
            root.querySelector('[data-hhp-muted-icon]').toggleAttribute('hidden', !video.muted);
            root.querySelector('[data-hhp-sound-icon]').toggleAttribute('hidden', video.muted);
            root.querySelectorAll('[data-hhp-clip]').forEach(button => {
                if (Number(button.dataset.hhpClip) === order[index]) button.setAttribute('aria-current', 'true');
                else button.removeAttribute('aria-current');
            });
        };
        const attempt = () => {
            const token = generation;
            return Promise.resolve(video.play()).then(() => {
                if (token === generation) tell('');
            }).catch(() => {
                if (token !== generation) return;
                tell('Press play to continue.');
                sync();
            });
        };
        const load = (target, start) => {
            if (target < 0 || target >= order.length) {
                if (!options.loop) return;
                target = (target + order.length) % order.length;
            }
            generation++;
            index = target;
            tell('');
            root.dataset.transitioning = 'true';
            video.src = clips[order[index]].url;
            video.load();
            sync();
            if (start) void attempt();
        };
        video.controls = false;
        video.muted = !!options.muted;
        video.loop = order.length === 1 && !!options.loop;
        prev?.addEventListener('click', () => load(index - 1, true));
        next?.addEventListener('click', () => load(index + 1, true));
        play.addEventListener('click', () => {
            if (video.paused || video.ended) {
                if (video.error) load(index, true);
                else void attempt();
            } else video.pause();
        });
        mute.addEventListener('click', () => { video.muted = !video.muted; sync(); });
        root.querySelectorAll('[data-hhp-clip]').forEach(button => {
            button.addEventListener('click', () => load(order.indexOf(Number(button.dataset.hhpClip)), true));
        });
        video.addEventListener('ended', () => {
            if (options.advance && order.length > 1) load(index + 1, true);
            sync();
        });
        video.addEventListener('loadeddata', () => { delete root.dataset.transitioning; });
        video.addEventListener('playing', () => { delete root.dataset.transitioning; tell(''); sync(); });
        video.addEventListener('error', () => {
            delete root.dataset.transitioning;
            tell('This clip is unavailable. Press play to retry.');
            sync();
        });
        ['play', 'pause', 'volumechange'].forEach(event => video.addEventListener(event, sync));
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const saveData = navigator.connection?.saveData || document.documentElement.dataset.dataSaver === 'true';
        const autoplay = options.autoplay && !reduced && !saveData && root.dataset.adminPreview !== 'true';
        if (autoplay) video.muted = true;
        if (order[0] !== 0) load(0, autoplay);
        else if (autoplay) void attempt();
        sync();
    }
})();