const builder = document.querySelector<HTMLFormElement>('[data-exam-builder]');
if (builder) {
    const list = builder.querySelector<HTMLElement>('[data-exam-questions]')!;
    const template = builder.querySelector<HTMLTemplateElement>('[data-exam-template]')!;
    const add = builder.querySelector<HTMLButtonElement>('[data-exam-add]')!;
    const questions = () => Array.from(list.querySelectorAll<HTMLElement>('[data-exam-question]'));

    function renumber(): void {
        const rows = questions();
        rows.forEach((row, index) => {
            row.querySelector('[data-exam-number]')!.textContent = `Question ${index + 1}`;
            row.querySelectorAll<HTMLInputElement>('[name]').forEach(input => {
                input.name = input.name.replace(/^questions\[\d+\]/, `questions[${index}]`);
            });
            const format = row.querySelector<HTMLSelectElement>('[data-exam-format]')!.value;
            row.querySelectorAll<HTMLElement>('[data-exam-option]').forEach(option => { option.hidden = format !== 'choice'; });
            row.querySelector('[data-exam-answer-tip]')!.textContent = format === 'choice'
                ? 'Enter a, b, c or d.' : format === 'boolean' ? 'Enter true or false.' : 'Enter the expected answer. Case and extra spaces are ignored.';
            row.querySelector<HTMLButtonElement>('[data-exam-up]')!.disabled = index === 0;
            row.querySelector<HTMLButtonElement>('[data-exam-down]')!.disabled = index === rows.length - 1;
            row.querySelector<HTMLButtonElement>('[data-exam-remove]')!.disabled = rows.length === 1;
        });
        add.disabled = rows.length >= 50;
    }

    add.addEventListener('click', () => {
        if (questions().length >= 50) return;
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
    renumber();
}

const attempt = document.querySelector<HTMLFormElement>('[data-exam-attempt]');
const timer = document.querySelector<HTMLOutputElement>('[data-exam-countdown]');
if (attempt && timer) {
    const end = Number(timer.dataset.expires) * 1000;
    const progress = attempt.querySelector<HTMLElement>('[data-exam-progress]');
    const total = attempt.querySelectorAll('fieldset').length;
    attempt.addEventListener('input', () => {
        const answers = Array.from(attempt.querySelectorAll<HTMLInputElement>('[data-exam-answer]'));
        const answered = answers.filter(input => input.type === 'radio' ? input.checked : input.value.trim() !== '').length;
        if (progress) progress.textContent = `${answered} answered of ${total}`;
    });

    const tick = (): void => {
        const remaining = Math.max(0, Math.ceil((end - Date.now()) / 1000));
        timer.value = `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
        if (remaining === 0) {
            attempt.querySelector<HTMLButtonElement>('button[type="submit"], button:not([type])')!.disabled = true;
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
