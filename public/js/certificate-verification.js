(() => {
    const page = document.querySelector('[data-certificate-verification]');
    if (!page) return;

    const messages = JSON.parse(page.dataset.messages);
    const status = page.querySelector('[data-cv-status]');
    const form = page.querySelector('[data-cv-form]');
    const copy = page.querySelector('[data-cv-copy]');
    if (copy && navigator.clipboard?.writeText) {
        copy.hidden = false;
        copy.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(copy.dataset.cvCopy);
                message('copied');
            } catch {
                message('copy_failed');
            }
        });
    }
    if (!form || typeof window.jsQR !== 'function') return;

    const input = page.querySelector('[data-cv-code]');
    const fileInput = page.querySelector('[data-cv-file]');
    const drop = page.querySelector('[data-cv-drop]');
    const video = page.querySelector('[data-cv-video]');
    const start = page.querySelector('[data-cv-start]');
    const stop = page.querySelector('[data-cv-stop]');
    const guide = page.querySelector('[data-cv-guide]');
    const cameraIcon = page.querySelector('[data-cv-camera-icon]');
    const tabList = page.querySelector('[data-cv-tabs]');
    const tabs = Array.from(tabList.querySelectorAll('[data-cv-tab]'));
    const panes = Array.from(page.querySelectorAll('[data-cv-pane]'));
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) return;

    const verification = new URL(form.action);
    const uuid = /^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i;
    let stream = null;
    let timer = null;
    let scanRequest = 0;
    let uploadRequest = 0;

    function message(key) {
        status.textContent = key ? messages[key] : '';
        status.dataset.state = ['invalid_file', 'no_qr', 'wrong_qr', 'unreadable', 'camera_failed', 'camera_unavailable',
            'one_image', 'copy_failed'].includes(key) ? 'error' : 'info';
    }

    function codeFrom(payload) {
        const text = payload.trim();
        if (uuid.test(text)) return text.toLowerCase();

        try {
            const url = new URL(text);
            if (url.origin !== verification.origin || url.pathname !== verification.pathname || url.username || url.password) return null;
            const code = url.searchParams.get('code');
            return code && uuid.test(code) ? code.toLowerCase() : null;
        } catch {
            return null;
        }
    }

    function decode(source, width, height, maxSize) {
        const scale = Math.min(1, maxSize / Math.max(width, height));
        canvas.width = Math.max(1, Math.round(width * scale));
        canvas.height = Math.max(1, Math.round(height * scale));
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(source, 0, 0, canvas.width, canvas.height);
        const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
        return window.jsQR(pixels.data, pixels.width, pixels.height)?.data ?? null;
    }

    function verify(payload) {
        const code = codeFrom(payload);
        if (!code) {
            message('wrong_qr');
            return;
        }
        stopCamera();
        message('checking');
        input.value = code;
        form.requestSubmit();
    }

    async function handleUpload(file) {
        const attempt = ++uploadRequest;
        stopCamera();
        if (!file) return;
        if (!/^image\/(png|jpeg|webp)$/.test(file.type) && (file.type || !/\.(png|jpe?g|webp)$/i.test(file.name))) {
            message('invalid_file');
            return;
        }
        if (file.size > 12 * 1024 * 1024) {
            message('invalid_file');
            return;
        }

        message('reading_image');
        const url = URL.createObjectURL(file);
        try {
            const image = new Image();
            image.src = url;
            await image.decode();
            if (attempt !== uploadRequest) return;
            const result = decode(image, image.naturalWidth, image.naturalHeight, 2400);
            if (result) verify(result);
            else message('no_qr');
        } catch {
            if (attempt === uploadRequest) message('unreadable');
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    function stopCamera() {
        scanRequest++;
        clearInterval(timer);
        stream?.getTracks().forEach(track => track.stop());
        stream = null;
        video.srcObject = null;
        video.hidden = true;
        guide.hidden = true;
        cameraIcon.hidden = false;
        stop.hidden = true;
        start.hidden = false;
        message(null);
    }

    async function startCamera() {
        if (!navigator.mediaDevices?.getUserMedia) {
            message('camera_unavailable');
            return;
        }

        uploadRequest++;
        const request = ++scanRequest;
        start.hidden = true;
        stop.hidden = false;
        message('requesting');

        try {
            const capture = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } },
                audio: false,
            });
            if (request !== scanRequest) {
                capture.getTracks().forEach(track => track.stop());
                return;
            }

            stream = capture;
            video.srcObject = capture;
            video.hidden = false;
            guide.hidden = false;
            cameraIcon.hidden = true;
            await video.play();
            if (request !== scanRequest) return;
            message('scanning');
            timer = setInterval(() => {
                if (!video.videoWidth) return;
                try {
                    const result = decode(video, video.videoWidth, video.videoHeight, 1000);
                    if (result) verify(result);
                } catch {
                    stopCamera();
                    message('camera_failed');
                }
            }, 220);
        } catch {
            if (request === scanRequest) {
                stopCamera();
                message('camera_failed');
            }
        }
    }

    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        fileInput.value = '';
        handleUpload(file);
    });
    drop.addEventListener('dragover', event => {
        if (!event.dataTransfer.types.includes('Files')) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
        drop.dataset.dragging = 'true';
    });
    drop.addEventListener('dragleave', event => {
        if (!drop.contains(event.relatedTarget)) delete drop.dataset.dragging;
    });
    drop.addEventListener('drop', event => {
        event.preventDefault();
        delete drop.dataset.dragging;
        const files = event.dataTransfer.files;
        if (files.length === 1) {
            handleUpload(files[0]);
        } else if (files.length > 1) {
            uploadRequest++;
            message('one_image');
        }
    });

    function selectTab(selected) {
        if (selected.getAttribute('aria-selected') === 'true') return;
        uploadRequest++;
        stopCamera();
        for (const tab of tabs) {
            const active = tab === selected;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
        }
        for (const pane of panes) pane.hidden = pane.dataset.cvPane !== selected.dataset.cvTab;
    }

    for (const pane of panes) {
        pane.setAttribute('role', 'tabpanel');
        pane.setAttribute('aria-labelledby', 'cv-tab-' + pane.dataset.cvPane);
    }
    tabList.addEventListener('click', event => {
        const tab = event.target.closest('[data-cv-tab]');
        if (tab) selectTab(tab);
    });
    tabList.addEventListener('keydown', event => {
        const index = tabs.indexOf(event.target);
        if (index === -1) return;
        const indices = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: tabs.length - 1 };
        if (!(event.key in indices)) return;
        event.preventDefault();
        const next = tabs[(indices[event.key] + tabs.length) % tabs.length];
        selectTab(next);
        next.focus();
    });
    tabList.hidden = false;

    start.addEventListener('click', () => {
        startCamera();
        if (!stop.hidden) stop.focus();
    });
    stop.addEventListener('click', () => {
        stopCamera();
        start.focus();
    });
    window.addEventListener('pagehide', stopCamera);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stopCamera();
    });
})();
