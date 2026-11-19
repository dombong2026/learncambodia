(() => {
    const page = document.querySelector('[data-certificate-verification]');
    if (!page || typeof window.jsQR !== 'function') return;

    const messages = JSON.parse(page.dataset.messages);
    const form = page.querySelector('[data-cv-form]');
    const input = page.querySelector('[data-cv-code]');
    const fileInput = page.querySelector('[data-cv-file]');
    const status = page.querySelector('[data-cv-status]');
    const video = page.querySelector('[data-cv-video]');
    const start = page.querySelector('[data-cv-start]');
    const stop = page.querySelector('[data-cv-stop]');
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
    start.addEventListener('click', startCamera);
    stop.addEventListener('click', stopCamera);
    window.addEventListener('pagehide', stopCamera);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stopCamera();
    });
    page.querySelector('[data-cv-qr-actions]').hidden = false;
})();
