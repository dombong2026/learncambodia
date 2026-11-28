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
        let index = 0, generation = 0, wanted = false, failures = 0, watchdog = null;
        const stopWatchdog = () => { clearTimeout(watchdog); watchdog = null; };
        const armWatchdog = () => {
            stopWatchdog();
            if (!wanted || document.hidden) return;
            const token = generation;
            watchdog = setTimeout(() => { if (token === generation && wanted && !document.hidden) recover(); }, 18000);
        };
        const tell = message => { status.textContent = message; status.hidden = !message; };
        const sync = () => {
            root.dataset.playing = String(!video.paused && !video.ended);
            root.dataset.clipIndex = String(index + 1);
            root.dataset.clipTotal = String(order.length);
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
        const attempt = async () => {
            const token = generation;
            wanted = true;
            armWatchdog();
            try {
                await video.play();
            } catch (error) {
                if (token !== generation || !wanted) return;
                if (error?.name === 'NotAllowedError' && !video.muted) {
                    video.muted = true;
                    try { await video.play(); } catch (retry) {
                        if (token === generation && wanted) blocked(retry);
                        return;
                    }
                } else {
                    blocked(error);
                    return;
                }
            }
            if (token === generation && wanted) { tell(''); sync(); }
        };
        const blocked = error => {
            if (error?.name === 'NotSupportedError' || video.error) { recover(); return; }
            wanted = false;
            stopWatchdog();
            tell('Press play to continue.');
            sync();
        };
        const recover = () => {
            if (!wanted) return;
            stopWatchdog();
            failures++;
            if (options.advance && order.length > 1 && failures < order.length && (options.loop || index < order.length - 1)) {
                load(index + 1, true);
                return;
            }
            wanted = false;
            video.pause();
            delete root.dataset.transitioning;
            tell('Playback interrupted. Press play to retry.');
            sync();
        };
        const load = (target, start) => {
            if (target < 0 || target >= order.length) {
                if (!options.loop) return;
                target = (target + order.length) % order.length;
            }
            stopWatchdog();
            generation++;
            wanted = start;
            index = target;
            tell('');
            root.dataset.transitioning = 'true';
            video.loop = order.length === 1 && !!options.loop;
            video.playsInline = true;
            // Keep the muted property across source changes, including WebKit.
            video.defaultMuted = video.muted;
            video.src = clips[order[index]].url;
            video.load();
            sync();
            if (start) void attempt();
        };
        video.controls = false;
        video.playsInline = true;
        video.muted = !!options.muted;
        video.defaultMuted = video.muted;
        video.loop = order.length === 1 && !!options.loop;
        prev?.addEventListener('click', () => load(index - 1, true));
        next?.addEventListener('click', () => load(index + 1, true));
        play.addEventListener('click', () => {
            if (video.paused || video.ended) {
                failures = 0;
                if (video.error) load(index, true);
                else void attempt();
            } else {
                wanted = false;
                stopWatchdog();
                video.pause();
            }
        });
        mute.addEventListener('click', () => { video.muted = !video.muted; sync(); });
        root.querySelectorAll('[data-hhp-clip]').forEach(button => {
            button.addEventListener('click', () => load(order.indexOf(Number(button.dataset.hhpClip)), true));
        });
        video.addEventListener('ended', () => {
            stopWatchdog();
            failures = 0;
            if (wanted && options.advance && order.length > 1 && (options.loop || index < order.length - 1)) {
                load(index + 1, true);
            } else {
                wanted = false;
                sync();
            }
        });
        video.addEventListener('loadeddata', () => { delete root.dataset.transitioning; });
        video.addEventListener('playing', () => { delete root.dataset.transitioning; tell(''); armWatchdog(); sync(); });
        video.addEventListener('timeupdate', () => {
            if (wanted && !video.paused && video.currentTime > 0) { failures = 0; armWatchdog(); }
        });
        video.addEventListener('waiting', armWatchdog);
        video.addEventListener('stalled', armWatchdog);
        video.addEventListener('error', () => {
            delete root.dataset.transitioning;
            if (wanted) recover();
            else { tell('This clip is unavailable. Press play to retry.'); sync(); }
        });
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) stopWatchdog();
            else if (wanted) {
                if (video.paused && !video.ended) void attempt();
                else armWatchdog();
            }
        });
        ['play', 'pause', 'volumechange'].forEach(event => video.addEventListener(event, sync));
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const saveData = navigator.connection?.saveData || document.documentElement.dataset.dataSaver === 'true';
        const autoplay = options.autoplay && !reduced && !saveData && root.dataset.adminPreview !== 'true';
        root.dataset.autoplay = autoplay ? 'enabled' : (!options.autoplay ? 'off' : reduced ? 'reduced-motion' : saveData ? 'data-saver' : 'admin-preview');
        if (autoplay) { video.muted = true; video.defaultMuted = true; }
        // Use the same source activation path for the first and subsequent clips.
        load(0, autoplay);
        sync();
    }
})();