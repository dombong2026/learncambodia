const builder = document.querySelector<HTMLFormElement>('[data-exam-builder]');
if (builder) {
    const list = builder.querySelector<HTMLElement>('[data-exam-questions]')!;
    const template = builder.querySelector<HTMLTemplateElement>('[data-exam-template]')!;
    const add = builder.querySelector<HTMLButtonElement>('[data-exam-add]')!;
    const limit = Number(builder.dataset.questionLimit);
    const questions = () => Array.from(list.querySelectorAll<HTMLElement>('[data-exam-question]'));

    function renumber(): void {
        const rows = questions();
        rows.forEach((row, index) => {
            row.querySelector('[data-exam-number]')!.textContent = `Question ${index + 1}`;
            row.querySelectorAll<HTMLInputElement>('[name]').forEach(input => {
                input.name = input.name.replace(/^questions\[\d+\]/, `questions[${index}]`);
            });
            const format = row.querySelector<HTMLSelectElement>('[data-exam-format]')!.value;
            const choice = ['choice', 'reading', 'listening'].includes(format);
            const manual = ['writing', 'speaking', 'likert'].includes(format);
            row.querySelectorAll<HTMLElement>('[data-exam-option]').forEach(option => { option.hidden = !choice && format !== 'order'; });
            row.querySelector<HTMLElement>('[data-exam-key]')!.hidden = manual;
            row.querySelector<HTMLInputElement>('[data-exam-correct]')!.required = !manual;
            row.querySelector<HTMLElement>('[data-exam-reading]')!.hidden = format !== 'reading';
            row.querySelector<HTMLElement>('[data-exam-listening]')!.hidden = format !== 'listening';
            row.querySelector('[data-exam-answer-tip]')!.textContent = choice
                ? 'Enter a, b, c or d.'
                : format === 'boolean' ? 'Enter true for Yes or false for No.'
                : format === 'order' ? 'Enter item letters in the correct order, for example b,a,c.'
                : 'Separate accepted answers with |. Capitalization and extra spaces are ignored.';
            row.querySelector<HTMLButtonElement>('[data-exam-up]')!.disabled = index === 0;
            row.querySelector<HTMLButtonElement>('[data-exam-down]')!.disabled = index === rows.length - 1;
            row.querySelector<HTMLButtonElement>('[data-exam-remove]')!.disabled = rows.length === 1;
        });
        add.disabled = rows.length >= limit;
    }

    add.addEventListener('click', () => {
        if (questions().length >= limit) return;
        list.append(template.content.cloneNode(true));
        renumber();
        list.lastElementChild?.querySelector('textarea')?.focus();
    });
    list.addEventListener('change', event => {
        if ((event.target as Element).matches('[data-exam-format]')) renumber();
    });
    list.addEventListener('click', event => {
        const control = (event.target as Element).closest<HTMLButtonElement>('button');
        const row = control?.closest<HTMLElement>('[data-exam-question]');
        if (!control || !row || control.disabled) return;
        if (control.hasAttribute('data-exam-up')) row.previousElementSibling?.before(row);
        if (control.hasAttribute('data-exam-down')) row.nextElementSibling?.after(row);
        if (control.hasAttribute('data-exam-remove')) row.remove();
        renumber();
        if (!control.disabled && control.isConnected) control.focus();
        else if (row.isConnected) row.querySelector('textarea')?.focus();
        else add.focus();
    });
    const course = builder.querySelector<HTMLSelectElement>('[name="course_id"]');
    const pricing = builder.querySelector<HTMLElement>('[data-standalone-pricing]');
    course?.addEventListener('change', () => {
        if (pricing) pricing.hidden = Boolean(course.value);
    });

    let uploading = 0;
    const save = builder.querySelector<HTMLButtonElement>('button[type="submit"]')!;
    list.addEventListener('change', async event => {
        const upload = event.target as HTMLInputElement;
        if (!upload.matches('[data-exam-audio]') || !upload.files?.[0]) return;

        const row = upload.closest<HTMLElement>('[data-exam-question]')!;
        const status = row.querySelector<HTMLElement>('[data-exam-upload-status]')!;
        const data = new FormData();
        data.set('file', upload.files[0]);
        data.set('context', 'exam');
        data.set('_token', builder.querySelector<HTMLInputElement>('[name="_token"]')!.value);
        uploading++;
        save.disabled = upload.disabled = true;
        status.textContent = 'Uploading audio…';
        try {
            const response = await fetch(builder.dataset.mediaUpload!, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
            const media = await response.json();
            if (!response.ok || !String(media.mime).startsWith('audio/')) throw new Error();
            row.querySelector<HTMLInputElement>('[data-exam-media-id]')!.value = String(media.id);
            const audio = row.querySelector<HTMLAudioElement>('audio')!;
            audio.src = media.url;
            audio.hidden = false;
            status.textContent = 'Audio ready.';
        } catch {
            status.textContent = 'Audio upload failed. Choose an audio file and try again.';
        } finally {
            upload.value = '';
            upload.disabled = false;
            save.disabled = --uploading > 0;
        }
    });
    builder.addEventListener('submit', () => {
        const payload = document.createElement('input');
        payload.type = 'hidden';
        payload.name = 'questions_payload';
        payload.value = JSON.stringify(questions().map(row => {
            const fields = row.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('[name^="questions["]');
            return Object.fromEntries(Array.from(fields, field => {
                const key = field.name.split('][')[1].slice(0, -1);
                field.disabled = true;
                return [key, field.value];
            }));
        }));
        builder.append(payload);
    });
    window.addEventListener('pageshow', () => {
        if (!builder.querySelector('[name="questions_payload"]')) return;
        builder.querySelector('[name="questions_payload"]')?.remove();
        builder.querySelectorAll<HTMLInputElement>('[name^="questions["]').forEach(field => { field.disabled = false; });
    });
    renumber();
}

const attempt = document.querySelector<HTMLFormElement>('[data-exam-attempt]');
const timer = document.querySelector<HTMLOutputElement>('[data-exam-countdown]');
if (attempt && timer) {
    const end = Number(timer.dataset.expires) * 1000;
    const progress = attempt.querySelector<HTMLElement>('[data-exam-progress]');
    const meter = attempt.querySelector<HTMLProgressElement>('[data-exam-meter]');
    const questions = Array.from(attempt.querySelectorAll<HTMLElement>('[data-attempt-question]'));
    const submit = attempt.querySelector<HTMLButtonElement>('button[type="submit"]')!;
    const selected = (row: HTMLElement) => row.querySelector<HTMLInputElement>('[data-exam-answer]:checked')
        ?? row.querySelector<HTMLInputElement | HTMLTextAreaElement>('[data-exam-answer]:not([type="radio"])');
    const updateProgress = (): void => {
        const answered = questions.filter(row => Boolean(selected(row)?.value.trim())).length;
        if (progress) progress.textContent = `${answered} answered of ${questions.length}`;
        if (meter) meter.value = answered;
    };
    attempt.addEventListener('input', updateProgress);
    attempt.addEventListener('change', updateProgress);
    updateProgress();

    let checking = 0;
    attempt.addEventListener('submit', event => {
        if (checking || Date.now() >= end) event.preventDefault();
    });

    questions.forEach(row => {
        const check = row.querySelector<HTMLButtonElement>('[data-exam-check]');
        check?.addEventListener('click', async () => {
            const feedback = row.querySelector<HTMLElement>('[data-exam-feedback]')!;
            const answer = selected(row)?.value.trim();
            if (!answer) {
                feedback.textContent = 'Choose or enter an answer first.';
                return;
            }

            checking++;
            submit.disabled = check.disabled = true;
            feedback.textContent = 'Checking answer…';
            try {
                const response = await fetch(attempt.dataset.feedbackUrl!, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': attempt.querySelector<HTMLInputElement>('[name="_token"]')!.value },
                    body: JSON.stringify({ question: Number(row.dataset.attemptQuestion), answer }),
                });
                if (!response.ok) throw new Error();
                const result = await response.json();
                row.setAttribute('data-answer-locked', '');
                row.querySelectorAll<HTMLInputElement | HTMLButtonElement>('input, button').forEach(control => {
                    if (control instanceof HTMLInputElement && control.type === 'radio') control.checked = control.value === result.answer;
                    else if (control.hasAttribute('data-exam-answer')) control.value = result.answer;
                    control.disabled = true;
                });
                const order = row.querySelector('[data-order-list]');
                if (order) String(result.answer).split(',').forEach(key => {
                    const item = Array.from(order.children).find(node => (node as HTMLElement).dataset.orderKey === key);
                    if (item) order.append(item);
                });
                row.querySelectorAll<HTMLElement>('[draggable]').forEach(item => { item.draggable = false; });
                feedback.textContent = `${result.correct ? 'Correct.' : 'Incorrect.'} ${result.feedback ?? ''} Answer locked.`;
            } catch {
                check.disabled = false;
                feedback.textContent = 'Unable to check the answer. Try again.';
            } finally {
                submit.disabled = --checking > 0 || Date.now() >= end;
                updateProgress();
            }
        });

        const order = row.querySelector<HTMLOListElement>('[data-order-list]');
        if (order && !row.hasAttribute('data-answer-locked')) {
            const answer = row.querySelector<HTMLInputElement>('[data-exam-answer]')!;
            const status = row.querySelector<HTMLOutputElement>('[data-order-status]')!;
            let dragged: HTMLElement | null = null;
            const captureOrder = (): void => {
                answer.value = Array.from(order.children, item => (item as HTMLElement).dataset.orderKey!).join(',');
                status.value = 'Order saved.';
                updateProgress();
            };
            row.querySelector('[data-use-order]')?.addEventListener('click', captureOrder);
            order.addEventListener('click', event => {
                const move = (event.target as Element).closest<HTMLButtonElement>('[data-move]');
                if (!move || !move.parentElement) return;
                const item = move.parentElement;
                const previous = move.dataset.move === '-1';
                const sibling = previous ? item.previousElementSibling : item.nextElementSibling;
                if (!sibling) return;
                if (previous) sibling.before(item);
                else sibling.after(item);
                captureOrder();
                move.focus();
            });
            order.addEventListener('dragstart', event => {
                dragged = (event.target as Element).closest<HTMLElement>('[data-order-key]');
                event.dataTransfer?.setData('text/plain', dragged?.dataset.orderKey ?? '');
            });
            order.addEventListener('dragend', () => { dragged = null; });
            order.addEventListener('dragover', event => { if (dragged) event.preventDefault(); });
            order.addEventListener('drop', event => {
                const target = (event.target as Element).closest<HTMLElement>('[data-order-key]');
                if (!dragged || !target || target === dragged) return;
                event.preventDefault();
                const bounds = target.getBoundingClientRect();
                if (event.clientY < bounds.top + bounds.height / 2) target.before(dragged);
                else target.after(dragged);
                captureOrder();
            });
        }

        const record = row.querySelector<HTMLElement>('[data-recording-controls]');
        if (!record || !navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined' || typeof DataTransfer === 'undefined') return;
        const start = record.querySelector<HTMLButtonElement>('[data-record-start]')!;
        const stop = record.querySelector<HTMLButtonElement>('[data-record-stop]')!;
        const status = record.querySelector<HTMLOutputElement>('[data-record-status]')!;
        const file = row.querySelector<HTMLInputElement>('[type="file"]')!;
        const preview = record.querySelector<HTMLAudioElement>('[data-record-preview]')!;
        let sample: MediaRecorder | null = null;
        record.hidden = false;

        start.addEventListener('click', async () => {
            start.disabled = true;
            let stream: MediaStream | undefined;
            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                const recorder = new MediaRecorder(stream);
                sample = recorder;
                const parts: Blob[] = [];
                recorder.ondataavailable = event => { if (event.data.size) parts.push(event.data); };
                recorder.onstop = () => {
                    stream?.getTracks().forEach(track => track.stop());
                    stop.hidden = true;
                    start.disabled = false;
                    const mime = recorder.mimeType.split(';')[0];
                    const recording = new Blob(parts, { type: mime });
                    if (!recording.size || recording.size > 10 * 1024 * 1024) {
                        status.value = 'The recording could not be attached. Try a shorter recording.';
                        return;
                    }
                    const transfer = new DataTransfer();
                    const extension = mime.endsWith('mp4') ? 'm4a' : mime.endsWith('ogg') ? 'ogg' : 'webm';
                    transfer.items.add(new File([recording], `response.${extension}`, { type: mime }));
                    file.files = transfer.files;
                    file.dispatchEvent(new Event('change', { bubbles: true }));
                    if (preview.src.startsWith('blob:')) URL.revokeObjectURL(preview.src);
                    preview.src = URL.createObjectURL(recording);
                    preview.hidden = false;
                    status.value = 'Recording attached.';
                };
                recorder.start();
                stop.hidden = false;
                status.value = 'Recording…';
            } catch {
                stream?.getTracks().forEach(track => track.stop());
                start.disabled = false;
                status.value = 'Microphone unavailable. You can still upload a recording.';
            }
        });
        stop.addEventListener('click', () => { if (sample?.state === 'recording') sample.stop(); });
        attempt.addEventListener('submit', event => {
            if (sample?.state !== 'recording') return;
            event.preventDefault();
            status.value = 'Stop the recording before submitting.';
        });
    });

    const tick = (): void => {
        const remaining = Math.max(0, Math.ceil((end - Date.now()) / 1000));
        timer.value = `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
        if (remaining === 0) {
            submit.disabled = true;
            timer.value = 'Time expired';
            return;
        }
        window.setTimeout(tick, 500);
    };
    tick();
}

const certificate = document.querySelector<HTMLFormElement>('[data-certificate-builder]');
const preview = document.querySelector<HTMLElement>('[data-certificate-preview]');
if (certificate && preview) {
    const update = (): void => {
        const fields = new FormData(certificate);
        preview.querySelector('[data-certificate-heading]')!.textContent = String(fields.get('heading') ?? '');
        preview.querySelector('[data-certificate-body]')!.textContent = String(fields.get('body') ?? '')
            .replaceAll('{learner}', 'Sample Learner').replaceAll('{course}', 'Sample Course');
        preview.querySelector('[data-certificate-issuer]')!.textContent = String(fields.get('issuer') ?? '');
        preview.style.setProperty('--certificate-accent', String(fields.get('accent')));
        preview.dataset.orientation = String(fields.get('orientation'));
    };
    certificate.addEventListener('input', update);
    certificate.addEventListener('change', update);
    update();
}
