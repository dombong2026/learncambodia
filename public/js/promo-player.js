(() => {
    const format = (seconds) => {
        if (!Number.isFinite(seconds)) return '–:–';
        const time = Math.floor(seconds);
        return `${Math.floor(time / 60)}:${String(time % 60).padStart(2, '0')}`;
    };

    function mount(deck) {
        const element = (name) => deck.querySelector(`[data-promo-${name}]`);
        const tracks = JSON.parse(element('tracks').textContent);
        const queue = [...deck.querySelectorAll('[data-promo-track]')];
        const audio = element('audio');
        const play = element('play');
        const seek = element('seek');
        const volume = element('volume');
        const bass = element('bass');
        const treble = element('treble');
        const presets = [...deck.querySelectorAll('[data-promo-preset]')];
        const spatial = element('spatial');
        const transcript = element('transcript');
        const transcriptText = transcript.querySelector('p');
        let transcriptCues = [];
        let transcriptLines = [];
        let activeCue = -1;
        const error = element('error');
        const canvas = element('wave');
        const artwork = canvas.getContext('2d');
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        let index = 0;
        let frame;
        let graph;
        const effects = [bass, treble, ...presets, spatial];
        const soundPresets = { bass: [6, 0], treble: [0, 5], cinematic: [4, 3] };
        let tryEffects = Boolean(AudioContext);
        let selectedPreset = null;

        effects.forEach((control) => { control.disabled = !tryEffects; });

        const revealTranscriptLine = () => {
            const line = transcriptLines[activeCue];
            if (!transcript.open || !line) return;
            if (activeCue === 0) {
                transcriptText.scrollTop = 0;
                return;
            }
            const panel = transcriptText.getBoundingClientRect();
            const current = line.getBoundingClientRect();
            if (current.top >= panel.top && current.bottom <= panel.bottom - 12) return;
            transcriptText.scrollTo({
                top: Math.max(0, transcriptText.scrollTop + current.top - panel.top - 8),
                behavior: motion.matches ? 'auto' : 'smooth',
            });
        };

        const syncTranscript = () => {
            if (!transcriptCues.length) return;
            let next = -1;
            for (const cue of transcriptCues) {
                if (cue.start > audio.currentTime + .06) break;
                next++;
            }
            if (next === activeCue) return;
            transcriptLines[activeCue]?.removeAttribute('aria-current');
            activeCue = next;
            if (next < 0) {
                transcriptText.scrollTop = 0;
                return;
            }
            transcriptLines[next].setAttribute('aria-current', 'true');
            revealTranscriptLine();
        };

        const loadTranscript = (track) => {
            transcriptCues = track.transcript_cues || [];
            activeCue = -1;
            transcriptText.textContent = '';
            transcriptLines = transcriptCues.map((cue, index) => {
                const line = document.createElement('span');
                line.className = 'promo-player__transcript-line';
                line.textContent = cue.text;
                if (index) transcriptText.append(' ');
                transcriptText.append(line);
                return line;
            });
            if (!transcriptLines.length) transcriptText.textContent = track.transcript || transcript.dataset.emptyText;
            transcriptText.scrollTop = 0;
        };

        const updateTime = () => {
            const ready = Number.isFinite(audio.duration) && audio.duration > 0;
            seek.disabled = !ready;
            seek.value = ready ? Math.round((audio.currentTime / audio.duration) * 1000) : 0;
            seek.setAttribute('aria-valuetext', `${format(audio.currentTime)} / ${format(audio.duration)}`);
            seek.style.setProperty('--promo-progress', `${Number(seek.value) / 10}%`);
            element('current').textContent = format(audio.currentTime);
            element('duration').textContent = format(audio.duration);
            element('time-pair').textContent = seek.getAttribute('aria-valuetext');
            syncTranscript();
        };

        const draw = () => {
            if (!artwork) return;
            artwork.clearRect(0, 0, canvas.width, canvas.height);
            const active = graph && !audio.paused && !audio.ended && !motion.matches;
            const frequencies = active ? graph.frequencies : null;
            if (active) graph.analyser.getByteFrequencyData(frequencies);
            const center = canvas.height / 2;
            const accent = deck.style.getPropertyValue('--promo-accent');
            for (let column = 0; column < 84; column++) {
                const taper = Math.sin(((column + .5) / 84) * Math.PI) ** .52;
                const ripple = .14 + .36 * Math.abs(Math.sin(column * .67))
                    + .29 * Math.abs(Math.sin(column * .291 + .1)) + .21 * Math.abs(Math.cos(column * 1.83));
                const band = Math.min(column, 83 - column);
                const signal = frequencies ? .52 + .48 * frequencies[band + 1] / 255 : .86;
                const span = Math.floor(16 * ripple * taper * signal);
                for (let dot = -span; dot <= span; dot++) {
                    artwork.fillStyle = Math.abs(dot) < 3 ? '#ffd488' : accent;
                    artwork.globalAlpha = Math.max(.37, .99 - Math.abs(dot) * .048);
                    artwork.beginPath();
                    artwork.arc((column + .5) * (canvas.width / 84), center + dot * 6.2, 2.15, 0, Math.PI * 2);
                    artwork.fill();
                }
            }
            artwork.globalAlpha = 1;

            // The level meter follows RMS, while the display follows the spectrum.
            let level = 0;
            if (active) {
                graph.analyser.getByteTimeDomainData(graph.samples);
                level = Math.sqrt(graph.samples.reduce((sum, value) => sum + ((value - 128) / 128) ** 2, 0) / graph.samples.length);
            }
            element('needle').setAttribute('transform', `rotate(${Math.min(126, level * 350)} 75 61)`);
            if (active) frame = requestAnimationFrame(draw);
        };

        const updatePlayback = () => {
            const active = !audio.paused && !audio.ended;
            const label = active ? 'Pause' : 'Play';
            deck.classList.toggle('is-playing', active);
            play.setAttribute('aria-label', label);
            play.title = label;
            element('play-icon').toggleAttribute('hidden', active);
            element('pause-icon').toggleAttribute('hidden', !active);
            cancelAnimationFrame(frame);
            draw();
        };

        const updateVolume = () => {
            const value = Number(volume.value) / 100;
            if (graph) graph.gain.gain.value = value;
            else audio.volume = value;
            volume.setAttribute('aria-valuetext', `${volume.value}%`);
            volume.parentElement.style.setProperty('--promo-knob-angle', `${-135 + 270 * value}deg`);
        };

        const updateSound = () => {
            if (!graph) return;
            const now = graph.context.currentTime;
            graph.low.gain.setTargetAtTime(Number(bass.value), now, .025);
            graph.high.gain.setTargetAtTime(Number(treble.value), now, .025);
            if (graph.wet) {
                const enabled = spatial.getAttribute('aria-checked') === 'true';
                graph.wet.gain.setTargetAtTime(enabled ? .16 : 0, now, .04);
                graph.dry.gain.setTargetAtTime(enabled ? .84 : 1, now, .04);
            }
        };

        function prepareAudio() {
            if (!tryEffects || graph) return graph;
            try {
                const context = new AudioContext();
                const low = context.createBiquadFilter();
                const high = context.createBiquadFilter();
                const analyser = context.createAnalyser();
                const gain = context.createGain();
                const dry = context.createGain();
                low.type = 'lowshelf';
                low.frequency.value = 160;
                high.type = 'highshelf';
                high.frequency.value = 3200;
                analyser.fftSize = 256;
                low.connect(high).connect(dry).connect(gain).connect(analyser).connect(context.destination);

                let wet;
                if (typeof context.createStereoPanner === 'function') {
                    wet = context.createGain();
                    wet.gain.value = 0;
                    wet.connect(gain);
                    // Early reflections spread the sound across the stereo field.
                    for (const [seconds, pan] of [[.015, -.85], [.024, .85]]) {
                        const delay = context.createDelay(.05);
                        const panner = context.createStereoPanner();
                        delay.delayTime.value = seconds;
                        panner.pan.value = pan;
                        high.connect(delay).connect(panner).connect(wet);
                    }
                } else {
                    spatial.disabled = true;
                }
                const source = context.createMediaElementSource(audio);
                source.connect(low);
                graph = { context, low, high, dry, wet, analyser, gain,
                    frequencies: new Uint8Array(analyser.frequencyBinCount), samples: new Uint8Array(analyser.fftSize) };
                audio.volume = 1;
                updateSound();
                updateVolume();
            } catch {
                tryEffects = false;
                effects.forEach((control) => { control.disabled = true; });
            }
            return graph;
        }

        function setPreset(preset) {
            selectedPreset = preset;
            presets.forEach((button) => {
                button.setAttribute('aria-pressed', String(button.dataset.promoPreset === preset));
            });
        }

        async function start() {
            error.hidden = true;
            try {
                const prepared = prepareAudio()?.context.resume();
                await Promise.all([prepared, audio.play()]);
            } catch (failure) {
                if (failure.name !== 'AbortError') error.hidden = false;
            }
        }

        function selectTrack(position, autoplay) {
            const track = tracks[position];
            if (position !== index) {
                index = position;
                audio.src = track.url;
                error.hidden = true;
                element('title').textContent = track.title;
                element('artist').textContent = track.artist || '';
                element('byline').hidden = !track.artist;
                loadTranscript(track);
                queue.forEach((item, number) => {
                    if (number === index) item.setAttribute('aria-current', 'true');
                    else item.removeAttribute('aria-current');
                });
                updateTime();
            }
            if (autoplay) start();
        }

        play.addEventListener('click', () => audio.paused ? start() : audio.pause());
        element('stop').addEventListener('click', () => {
            audio.pause();
            if (Number.isFinite(audio.duration)) audio.currentTime = 0;
            updateTime();
        });
        element('prev').disabled = element('next').disabled = tracks.length < 2;
        element('prev').addEventListener('click', () => selectTrack((index + tracks.length - 1) % tracks.length, true));
        element('next').addEventListener('click', () => selectTrack((index + 1) % tracks.length, true));
        queue.forEach((item, number) => item.addEventListener('click', () => selectTrack(number, true)));
        seek.addEventListener('input', () => {
            if (seek.disabled) return;
            const target = Number(seek.value) / 1000 * audio.duration;
            // Keep explicit seeks within decodable audio. Playback can still end naturally.
            audio.currentTime = Math.min(target, Math.max(0, audio.duration - .1));
        });
        volume.addEventListener('input', updateVolume);
        [bass, treble].forEach((control) => {
            control.addEventListener('input', () => {
                setPreset(null);
                updateSound();
            });
        });
        presets.forEach((button) => button.addEventListener('click', () => {
            const preset = button.dataset.promoPreset;
            setPreset(selectedPreset === preset ? null : preset);
            [bass.value, treble.value] = soundPresets[selectedPreset] || [0, 0];
            updateSound();
        }));
        spatial.addEventListener('click', () => {
            spatial.setAttribute('aria-checked', String(spatial.getAttribute('aria-checked') !== 'true'));
            updateSound();
        });
        transcript.addEventListener('toggle', revealTranscriptLine);
        ['timeupdate', 'seeked', 'loadedmetadata', 'durationchange', 'emptied'].forEach((event) => audio.addEventListener(event, updateTime));
        ['playing', 'pause', 'ended', 'emptied'].forEach((event) => audio.addEventListener(event, updatePlayback));
        audio.addEventListener('error', () => { error.hidden = false; });
        audio.addEventListener('ended', () => {
            if (index + 1 < tracks.length) selectTrack(index + 1, true);
            else if (deck.dataset.loop === '1') {
                if (tracks.length === 1) audio.currentTime = 0;
                selectTrack(0, true);
            }
        });
        motion.addEventListener?.('change', updatePlayback);
        loadTranscript(tracks[0]);
        updateVolume();
        updateTime();
        draw();
        element('ui').hidden = false;
    }

    document.querySelectorAll('[data-promo-player]').forEach(mount);

    const form = document.querySelector('[data-promo-form]');
    if (!form) return;

    const list = form.querySelector('[data-promo-track-list]');
    const template = form.querySelector('template');
    const add = form.querySelector('[data-promo-add]');
    let nextIndex = Number(list.dataset.nextIndex);

    function updateRows() {
        const rows = [...list.children];
        add.disabled = rows.length >= 8;
        rows.forEach((row, index) => {
            row.querySelector('legend').textContent = `Track ${index + 1}`;
            row.querySelector('[data-promo-row-action="up"]').disabled = index === 0;
            row.querySelector('[data-promo-row-action="down"]').disabled = index === rows.length - 1;
        });
    }

    add.addEventListener('click', () => {
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[name]').forEach((field) => { field.name = field.name.replace('TRACK_INDEX', String(nextIndex)); });
        nextIndex++;
        list.append(row);
        updateRows();
        row.querySelector('input[type=text]').focus();
    });

    list.addEventListener('click', (event) => {
        const control = event.target.closest('[data-promo-row-action]');
        if (!control) return;
        const row = control.closest('fieldset');
        if (control.dataset.promoRowAction === 'remove') {
            const nearest = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            (nearest?.querySelector('input[type=text]') || add).focus();
        } else if (control.dataset.promoRowAction === 'up') {
            row.previousElementSibling.before(row);
        } else {
            row.nextElementSibling.after(row);
        }
        updateRows();
    });

    updateRows();
})();
