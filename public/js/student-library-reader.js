const reader = document.querySelector('[data-reader]');
const by = (name) => reader.querySelector(`[data-${name}]`);
const status = by('reader-status');
const tools = by('reader-tools');
const stage = by('reader-stage');
const source = reader.dataset.source;
const format = reader.dataset.format;
const expires = Date.parse(reader.dataset.accessUntil);
const zoomValue = by('zoom');
const zoomOut = by('zoom-out');
const zoomIn = by('zoom-in');

let zoom = 100;
let page = 1;
let pdf;
let task;
let rendering;
let renderVersion = 0;
let expired = false;

function showMessage(message) {
    status.textContent = message;
    status.hidden = !message;
}

function releaseReader() {
    expired = true;
    ++renderVersion;
    rendering?.cancel();
    task?.destroy().catch(() => {});
    stage.replaceChildren();
    stage.hidden = true;
    tools.hidden = true;
    showMessage('Your loan has expired. Return to the item to borrow it again.');
}

function checkDeadline() {
    if (Number.isFinite(expires) && Date.now() >= expires && !expired) {
        releaseReader();
    }
    return expired;
}

function syncControls() {
    zoomValue.value = `${zoom}%`;
    zoomOut.disabled = zoom <= 50;
    zoomIn.disabled = zoom >= 250;

    if (pdf) {
        by('page-prev').disabled = page <= 1;
        by('page-next').disabled = page >= pdf.numPages;
        by('page').value = page;
    }
}

function displayError(error) {
    if (expired || error?.name === 'RenderingCancelledException') return;
    stage.hidden = true;
    showMessage('The file could not be opened. Check your access and reload the page.');
}

async function drawPage() {
    if (checkDeadline()) return;
    const version = ++renderVersion;
    rendering?.cancel();
    try {
        const paper = await pdf.getPage(page);
        if (version !== renderVersion) return;
        const fitWidth = Math.min(stage.clientWidth - 24, 920);
        const base = paper.getViewport({ scale: 1 });
        const scale = Math.max(1, fitWidth) / base.width * zoom / 100;
        const viewport = paper.getViewport({ scale });
        const resolution = Math.min(window.devicePixelRatio || 1, 2, Math.sqrt(8000000 / (viewport.width * viewport.height)));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.floor(viewport.width * resolution));
        canvas.height = Math.max(1, Math.floor(viewport.height * resolution));
        canvas.style.width = `${viewport.width}px`;
        canvas.style.height = `${viewport.height}px`;
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', `Page ${page} of ${pdf.numPages}`);
        const pending = paper.render({ canvas, viewport, transform: [resolution, 0, 0, resolution, 0, 0] });
        rendering = pending;
        await pending.promise;
        if (version !== renderVersion) return;

        const target = by('pdf-page');
        target.querySelector('canvas').replaceWith(canvas);
        showMessage('');
        const text = by('pdf-text');
        text.textContent = '';
        const content = await paper.getTextContent();
        if (version === renderVersion) {
            text.textContent = content.items.filter((part) => typeof part.str === 'string')
                .map((part) => part.str + (part.hasEOL ? '\n' : ' ')).join('').trim();
        }
    } catch (error) {
        if (version === renderVersion) displayError(error);
    }
}

function updateZoom(next) {
    if (checkDeadline()) return;
    zoom = Math.max(50, Math.min(250, next));
    syncControls();
    if (format === 'PDF') {
        drawPage();
    } else {
        by('reader-text').style.fontSize = `${zoom / 100}rem`;
    }
}

function updatePage(next) {
    if (checkDeadline() || !Number.isFinite(next)) return;
    page = Math.max(1, Math.min(pdf.numPages, Math.trunc(next)));
    syncControls();
    stage.scrollTo(0, 0);
    drawPage();
}

async function openReader() {
    if (checkDeadline()) return;
    try {
        if (format === 'PDF') {
            const root = reader.dataset.pdfRoot;
            const pdfjs = await import(`${root}pdf.js`);
            if (checkDeadline()) return;
            pdfjs.GlobalWorkerOptions.workerSrc = `${root}pdf.worker.js`;
            task = pdfjs.getDocument({
                url: source, cMapUrl: `${root}cmaps/`, cMapPacked: true,
                standardFontDataUrl: `${root}standard_fonts/`, wasmUrl: `${root}wasm/`,
                iccUrl: `${root}iccs/`, enableXfa: false,
            });
            pdf = await task.promise;
            by('page').max = pdf.numPages;
            by('page-count').textContent = `/ ${pdf.numPages}`;
            by('page-prev').addEventListener('click', () => updatePage(page - 1));
            by('page-next').addEventListener('click', () => updatePage(page + 1));
            by('page').addEventListener('change', (event) => updatePage(Number(event.target.value)));
        } else {
            const response = await fetch(source, { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok || !response.headers.get('content-type')?.startsWith('text/plain')) {
                throw new Error('Text file unavailable');
            }
            const text = await response.text();
            if (!checkDeadline()) by('reader-text').textContent = text;
        }
        if (checkDeadline()) return;
        stage.hidden = false;
        tools.hidden = false;
        syncControls();
        if (pdf) {
            drawPage();
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(drawPage, 125);
            });
        } else {
            showMessage('');
        }
    } catch (error) {
        displayError(error);
    }
}

zoomOut.addEventListener('click', () => updateZoom(zoom - 25));
zoomIn.addEventListener('click', () => updateZoom(zoom + 25));
by('zoom-reset').addEventListener('click', () => updateZoom(100));
if (Number.isFinite(expires)) setInterval(checkDeadline, 10000);
openReader();
