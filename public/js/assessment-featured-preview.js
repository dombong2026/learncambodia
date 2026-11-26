/* Assessment-only preview; existing Media Library and form submission stay authoritative. */
(() => {
    const panel = document.querySelector('[data-assessment-media-panel]');
    if (!panel) return;
    const preview = panel.querySelector('[data-assessment-media-preview]');
    const placeholder = panel.querySelector('[data-assessment-media-placeholder]');
    const message = panel.querySelector('[data-assessment-media-message]');
    const pick = panel.querySelector('[data-assessment-media-pick]');
    const kind = panel.querySelector('[data-featured-kind]');
    const assetId = panel.querySelector('[data-featured-id]');
    const assetName = panel.querySelector('[data-featured-name]');
    const alt = panel.querySelector('[name="featured_alt"]');
    const youtube = panel.querySelector('[name="featured_youtube"]');
    const color = panel.querySelector('[name="featured_color"]');
    const library = panel.querySelector('[data-open-media-library]');
    if (![preview, placeholder, message, pick, kind, assetId, assetName, alt, youtube, color, library].every(Boolean)) return;
    let current = null;
    let signature = '';
    function youtubeId(value) {
        const input = value.trim();
        if (/^[A-Za-z0-9_-]{11}$/.test(input)) return input;
        try {
            const url = new URL(input);
            if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password || url.port) return '';
            let id = '';
            if (url.hostname === 'youtu.be') id = url.pathname.match(/^\/([A-Za-z0-9_-]{11})\/?$/)?.[1] || '';
            else if (['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'].includes(url.hostname)) {
                id = url.pathname === '/watch' ? url.searchParams.get('v') || '' : url.pathname.match(/^\/(?:embed|shorts|live)\/([A-Za-z0-9_-]{11})\/?$/)?.[1] || '';
            }
            return /^[A-Za-z0-9_-]{11}$/.test(id) ? id : '';
        } catch { return ''; }
    }
    function clear() {
        if (current instanceof HTMLVideoElement) {
            current.pause();
            current.removeAttribute('src');
            current.load();
        }
        current?.remove();
        current = null;
    }
    function empty(text, allowPick = false) {
        clear();
        message.textContent = text;
        pick.hidden = !allowPick;
        placeholder.hidden = false;
    }
    function show(element, src) {
        clear();
        placeholder.hidden = true;
        current = element;
        if (src) {
            element.addEventListener('error', () => {
                if (current === element) empty('Preview unavailable. Your selection is retained.', ['image', 'video'].includes(kind.value));
            }, { once: true });
        }
        preview.append(element);
        if (src) element.src = src;
    }
    function render() {
        const mode = kind.value;
        const id = assetId.value;
        const key = JSON.stringify([mode, id, youtube.value, color.value]);
        assetName.title = assetName.textContent || '';
        if (current instanceof HTMLImageElement && mode === 'image') current.alt = alt.value || 'Featured image preview';
        if (key === signature) return;
        signature = key;
        if (mode === 'none') return empty('No featured media selected', true);
        if (mode === 'image' || mode === 'video') {
            if (!/^[1-9][0-9]*$/.test(id)) return empty(mode === 'image' ? 'Choose or upload an image' : 'Choose or upload a video', true);
            const template = panel.dataset.mediaUrlTemplate || '';
            const url = new URL(template.replace('__MEDIA__', id), location.origin);
            if (!template.includes('__MEDIA__') || url.origin !== location.origin) return empty('Preview unavailable.', true);
            const element = document.createElement(mode === 'image' ? 'img' : 'video');
            if (mode === 'image') element.alt = alt.value || 'Featured image preview';
            else {
                element.controls = true;
                element.preload = 'metadata';
                element.playsInline = true;
                element.setAttribute('aria-label', 'Featured video preview');
            }
            return show(element, url.href);
        }
        if (mode === 'youtube') {
            const id = youtubeId(youtube.value);
            if (!id) return empty('Enter a valid YouTube link to preview');
            const image = document.createElement('img');
            image.alt = 'YouTube video thumbnail preview';
            image.referrerPolicy = 'no-referrer';
            return show(image, 'https://i.ytimg.com/vi/' + id + '/hqdefault.jpg');
        }
        if (mode === 'color' && /^#[a-fA-F0-9]{6}$/.test(color.value)) {
            const swatch = document.createElement('div');
            swatch.dataset.assessmentMediaSwatch = '';
            swatch.style.backgroundColor = color.value;
            swatch.setAttribute('role', 'img');
            swatch.setAttribute('aria-label', 'Cover color ' + color.value);
            return show(swatch);
        }
        empty('Choose a featured media type');
    }
    pick.addEventListener('click', () => {
        if (kind.value === 'none') {
            kind.value = 'image';
            kind.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (['image', 'video'].includes(kind.value)) library.click();
    });
    panel.addEventListener('change', () => requestAnimationFrame(render));
    panel.addEventListener('input', () => requestAnimationFrame(render));
    render();
})();
