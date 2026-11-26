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
        if (options.shuffle) {
            for (let i = order.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [order[i], order[j]] = [order[j], order[i]];
            }
        }
        let index = 0;
        const tell = message => { status.textContent = message; status.hidden = !message; };
        const sync = () => {
            position.textContent = (index + 1) + ' / ' + order.length;
            prev.disabled = !options.loop && index === 0;
            next.disabled = !options.loop && index === order.length - 1;
            play.textContent = video.paused ? 'Play' : 'Pause';
            mute.textContent = video.muted ? 'Unmute' : 'Mute';
            root.querySelectorAll('[data-hhp-clip]').forEach(button => {
                if (Number(button.dataset.hhpClip) === order[index]) button.setAttribute('aria-current', 'true');
                else button.removeAttribute('aria-current');
            });
        };
        const attempt = () => video.play().catch(() => {
            video.controls = true;
            tell('Press play to continue.');
            sync();
        });
        const load = (target, start) => {
            if (target < 0 || target >= order.length) {
                if (!options.loop) return;
                target = (target + order.length) % order.length;
            }
            index = target;
            tell('');
            video.src = clips[order[index]].url;
            video.load();
            sync();
            if (start) void attempt();
        };
        video.controls = !!options.controls;
        video.muted = !!options.muted;
        play.hidden = !!options.controls;
        mute.hidden = !!options.controls;
        prev.addEventListener('click', () => load(index - 1, true));
        next.addEventListener('click', () => load(index + 1, true));
        play.addEventListener('click', () => video.paused ? void attempt() : video.pause());
        mute.addEventListener('click', () => { video.muted = !video.muted; sync(); });
        root.querySelectorAll('[data-hhp-clip]').forEach(button => {
            button.addEventListener('click', () => load(order.indexOf(Number(button.dataset.hhpClip)), true));
        });
        video.addEventListener('ended', () => {
            if (options.advance) load(index + 1, true);
            sync();
        });
        video.addEventListener('error', () => {
            tell('This clip is unavailable. Choose another video.');
            video.controls = true;
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