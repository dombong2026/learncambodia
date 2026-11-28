(() => {
    const builder = document.querySelector('[data-cert-builder]');
    if (!builder) return;

    const form = builder.querySelector('form');
    const paper = builder.querySelector('.cert-paper');
    const artwork = paper.querySelector('[data-cert-image]');
    const savedArtwork = artwork.getAttribute('src');
    const upload = form.elements.namedItem('background');
    const removeArtwork = form.elements.namedItem('remove_background');
    const fileStatus = builder.querySelector('[data-cert-file-status]');
    const dropZone = builder.querySelector('[data-cert-drop]');
    const controls = Object.fromEntries(Array.from(builder.querySelectorAll('[data-cert-controls]'), group => [
        group.dataset.certControls,
        { group, inputs: Object.fromEntries(Array.from(group.querySelectorAll('input'), input => [input.dataset.certAxis, input])) },
    ]));
    const fields = Object.fromEntries(Array.from(paper.querySelectorAll('[data-cert-field]'), field => [field.dataset.certField, field]));
    let objectUrl = null;
    let dragging = null;

    function renderArtwork() {
        const url = objectUrl || (removeArtwork?.checked ? null : savedArtwork);
        artwork.hidden = !url;
        if (url) {
            if (artwork.getAttribute('src') !== url) artwork.src = url;
        } else {
            artwork.removeAttribute('src');
        }
    }

    function renderField(key) {
        for (const [axis, input] of Object.entries(controls[key].inputs)) {
            if (input.validity.valid) fields[key].style.setProperty('--cert-' + axis, input.value + (axis === 'size' ? '' : '%'));
        }
    }

    function renderText() {
        const value = name => form.elements.namedItem(name).value;
        const type = form.elements.namedItem('certificate_type_id');
        fields.type.textContent = type.value ? type.selectedOptions[0].textContent.trim() : 'Course completion';
        fields.heading.textContent = value('heading');
        const kind = type.selectedOptions[0]?.dataset.certificateKind;
        const placeholders = {
            '{learner}': fields.learner.textContent,
            '{course}': 'Sample Course',
            '{assessment}': kind === 'quiz' ? 'Sample Quiz' : 'Sample Exam',
        };
        fields.body.textContent = value('body').replace(/\{learner\}|\{course\}|\{assessment\}/g, key => placeholders[key]);
        form.elements.namedItem('courses[]').disabled = kind === 'exam' || kind === 'quiz';
        fields.issuer.textContent = value('issuer');
        paper.dataset.orientation = value('orientation');
        paper.dataset.font = value('design[font]');
        paper.dataset.frame = form.querySelector('input[type="checkbox"][name="design[frame]"]').checked ? 'on' : 'off';
        paper.style.setProperty('--cert-accent', value('accent'));
        paper.style.setProperty('--cert-ink', value('design[ink]'));
    }

    function selectField(key) {
        for (const [name, field] of Object.entries(fields)) {
            field.toggleAttribute('data-selected', name === key);
            controls[name].group.toggleAttribute('data-selected', name === key);
        }
    }

    function uploadChanged() {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        const file = upload.files[0];
        const supported = file && (/^image\/(png|jpeg|webp)$/.test(file.type) || (!file.type && /\.(png|jpe?g|webp)$/i.test(file.name)));
        if (file && (!supported || file.size > 8 * 1024 * 1024)) {
            fileStatus.textContent = 'Choose a PNG, JPG or WebP image up to 8 MB.';
            upload.value = '';
        } else if (file) {
            objectUrl = URL.createObjectURL(file);
            if (removeArtwork) removeArtwork.checked = false;
            fileStatus.textContent = file.name;
        } else {
            fileStatus.textContent = '';
        }
        renderArtwork();
    }

    upload.addEventListener('change', uploadChanged);
    artwork.addEventListener('error', () => {
        if (!objectUrl) return;
        upload.value = '';
        uploadChanged();
        fileStatus.textContent = 'This image could not be opened. Choose another image.';
    });
    removeArtwork?.addEventListener('change', () => {
        if (removeArtwork.checked && upload.files.length) {
            upload.value = '';
            uploadChanged();
        }
        renderArtwork();
    });
    dropZone.addEventListener('dragover', event => {
        if (!event.dataTransfer?.types.includes('Files')) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
        dropZone.setAttribute('data-dragging', '');
    });
    dropZone.addEventListener('dragleave', event => {
        if (!dropZone.contains(event.relatedTarget)) dropZone.removeAttribute('data-dragging');
    });
    dropZone.addEventListener('drop', event => {
        event.preventDefault();
        dropZone.removeAttribute('data-dragging');
        if (!event.dataTransfer?.files.length) return;
        if (event.dataTransfer.files.length !== 1) {
            fileStatus.textContent = 'Drop one artwork image at a time.';
            return;
        }
        upload.files = event.dataTransfer.files;
        uploadChanged();
    });

    form.addEventListener('input', renderText);
    for (const [key, { group }] of Object.entries(controls)) {
        group.addEventListener('focusin', () => selectField(key));
        group.addEventListener('input', () => renderField(key));
    }

    paper.addEventListener('pointerdown', event => {
        const field = event.target.closest('[data-cert-field]');
        if (!field || !event.isPrimary || event.button !== 0) return;
        const key = field.dataset.certField;
        const { x, y } = controls[key].inputs;
        if (!x.validity.valid || !y.validity.valid) return;
        const box = paper.getBoundingClientRect();
        dragging = { key, id: event.pointerId, x: event.clientX, y: event.clientY, left: x.valueAsNumber, top: y.valueAsNumber, box };
        selectField(key);
        paper.setPointerCapture(event.pointerId);
    });
    paper.addEventListener('pointermove', event => {
        if (dragging?.id !== event.pointerId) return;
        const { key, box } = dragging;
        const { x, y } = controls[key].inputs;
        const limit = (number, input) => Math.min(Number(input.max), Math.max(Number(input.min), number)).toFixed(1);
        x.value = limit(dragging.left + (event.clientX - dragging.x) * 100 / box.width, x);
        y.value = limit(dragging.top + (event.clientY - dragging.y) * 100 / box.height, y);
        renderField(key);
    });
    paper.addEventListener('lostpointercapture', () => { dragging = null; });

    builder.querySelector('[data-cert-print]').addEventListener('click', () => window.print());
    renderText();
    renderArtwork();
})();
