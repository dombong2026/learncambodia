document.querySelectorAll('[data-openai-authoring]').forEach(panel => {
    const find = selector => panel.querySelector(selector);
    const form = panel.closest('form');
    const context = find('[data-ai-context]');
    const task = find('[data-ai-task]');
    const level = find('[data-ai-level]');
    const count = find('[data-ai-count]');
    const topic = find('[data-ai-topic]');
    const status = find('[data-ai-status]');
    const preview = find('[data-ai-preview]');
    const resultText = find('[data-ai-result]');
    const apply = find('[data-ai-apply]');
    const generate = find('[data-ai-generate]');
    const audioTools = find('[data-ai-audio-tools]');
    const targetQuestion = find('[data-ai-listening-row]');
    let draft;
    let audioUrl;
    let listeningRows = [];

    const rows = () => Array.from(form.querySelectorAll('[data-exam-question]'));
    const setValue = (field, value) => {
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
    };
    const resetDraft = () => {
        draft = null;
        preview.hidden = true;
        if (audioUrl) URL.revokeObjectURL(audioUrl);
        audioUrl = null;
    };
    const updateTask = () => {
        const audio = task.value === 'audio';
        find('[data-ai-topic-label]').textContent = audio ? 'Exact passage to read aloud' : 'Topic and focus';
        find('[data-ai-level-field]').hidden = audio;
        if (count) find('[data-ai-count-field]').hidden = task.value !== 'questions';
        generate.textContent = audio ? 'Generate recording' : 'Generate draft';
        resetDraft();
    };
    task.addEventListener('change', updateTask);
    const updateContext = () => {
        const textEnabled = context.selectedOptions[0]?.dataset.text === '1';
        for (const option of task.options) {
            option.disabled = option.value !== 'audio' && !textEnabled;
            option.hidden = option.disabled;
        }
        if (!task.value || task.selectedOptions[0].disabled) task.value = 'audio';
        updateTask();
    };
    context.addEventListener('change', updateContext);
    updateContext();

    const refreshListeningRows = () => {
        if (!targetQuestion) return;
        const selected = listeningRows[Number(targetQuestion.value)];
        listeningRows = rows().filter(row => row.querySelector('[data-exam-format]')?.value === 'listening');
        targetQuestion.disabled = listeningRows.length === 0;
        targetQuestion.replaceChildren(...listeningRows.map((row, index) =>
            new Option(row.querySelector('[data-exam-number]').textContent + ' · ' +
                row.querySelector('textarea').value.slice(0, 65), String(index))));
        if (listeningRows.includes(selected)) targetQuestion.value = String(listeningRows.indexOf(selected));
        apply.disabled = listeningRows.length === 0;
        if (!listeningRows.length) targetQuestion.add(new Option('Add or select a Listening question first', ''));
    };
    form.addEventListener('change', event => {
        if (draft?.task === 'audio' && event.target.matches('[data-exam-format]')) refreshListeningRows();
    });
    targetQuestion?.addEventListener('pointerdown', refreshListeningRows);

    generate.addEventListener('click', async () => {
        const audio = task.value === 'audio';
        const input = topic.value.trim() || (audio ? '' : form.elements.namedItem('title')?.value.trim());
        if (!input) {
            status.textContent = audio ? 'Enter the exact listening passage.' : 'Enter a topic or a title in the editor.';
            topic.focus();
            return;
        }
        if (task.value === 'questions' && !count.checkValidity()) {
            count.reportValidity();
            return;
        }
        resetDraft();
        const request = { context: context.value, task: task.value, topic: input, level: level.value };
        if (task.value === 'questions') request.count = Number(count.value);
        generate.disabled = task.disabled = context.disabled = true;
        status.textContent = audio ? 'Generating recording…' : 'Generating draft…';
        try {
            const response = await fetch(panel.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.elements.namedItem('_token').value,
                },
                body: JSON.stringify(request),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors ?? {})[0]?.[0] || data.message || 'Unable to generate a draft.');
            draft = { ...data, task: request.task };
            if (draft.questions) {
                resultText.textContent = draft.questions.map((question, index) =>
                    [`${index + 1}. ${question.prompt}`, ...['a','b','c','d'].map(letter => letter.toUpperCase() + '. ' + question[letter]),
                        'Answer ' + question.correct.toUpperCase() + ' · ' + question.feedback].join('\n')).join('\n\n');
            } else {
                resultText.textContent = draft.text || draft.transcript || 'Listen to the recording before adding it.';
            }
            if (audioTools) audioTools.hidden = !audio;
            apply.disabled = false;
            if (audio) {
                const bytes = Uint8Array.from(atob(draft.wav), char => char.charCodeAt(0));
                draft.file = new File([bytes], 'openai-listening.wav', { type: 'audio/wav' });
                audioUrl = URL.createObjectURL(draft.file);
                find('[data-ai-player]').src = audioUrl;
                find('[data-ai-download]').href = audioUrl;
                refreshListeningRows();
            }
            apply.textContent = audio ? 'Use recording on this question'
                : draft.questions ? 'Add questions to draft'
                    : draft.task === 'summary' ? 'Use as summary' : 'Append to editor';
            preview.hidden = false;
            status.textContent = 'Review the result below.';
        } catch (error) {
            status.textContent = error.message || 'Unable to generate a draft.';
        } finally {
            generate.disabled = task.disabled = context.disabled = false;
        }
    });

    apply.addEventListener('click', () => {
        if (!draft) return;
        if (draft.questions) {
            const questions = rows();
            const existing = questions.length === 1 ? questions[0] : null;
            const field = (row, key) => row.querySelector(`[name$="[${key}]"]`);
            const blankRow = existing && field(existing, 'format').value === 'choice'
                && ['prompt', 'a', 'b', 'c', 'd', 'correct', 'feedback', 'passage', 'media_id'].every(key => !field(existing, key)?.value.trim());
            if (questions.length + draft.questions.length - Number(Boolean(blankRow)) > Number(form.dataset.questionLimit)) {
                status.textContent = 'There is not enough space for these questions. Remove questions or generate a smaller set.';
                return;
            }
            for (const [index, question] of draft.questions.entries()) {
                const previous = rows().length;
                if (!(index === 0 && blankRow)) form.querySelector('[data-exam-add]').click();
                const current = rows();
                const reused = index === 0 && blankRow;
                if (!reused && current.length === previous) {
                    status.textContent = 'The editor could not add another question.';
                    return;
                }
                const row = reused ? existing : current.at(-1);
                for (const [key, value] of Object.entries(question)) {
                    const input = field(row, key);
                    if (input) setValue(input, value);
                }
            }
        } else if (draft.task === 'audio') {
            const row = listeningRows[Number(targetQuestion.value)];
            const input = row?.isConnected ? row.querySelector('[data-exam-audio]') : null;
            if (!input || row.querySelector('[data-exam-format]').value !== 'listening') {
                refreshListeningRows();
                status.textContent = 'Choose an available listening question.';
                return;
            }
            const files = new DataTransfer();
            files.items.add(draft.file);
            input.files = files.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            status.textContent = 'Check the upload status on the listening question before saving.';
            return;
        } else if (draft.task === 'summary') {
            const summary = form.elements.namedItem('summary');
            if (summary.value.trim() && !window.confirm('Replace the current course summary with this draft?')) return;
            setValue(summary, draft.text);
        } else {
            const plain = form.querySelector(draft.task === 'lesson' ? '#lesson-content-plain' : '#course-description-plain');
            const editor = plain.closest('[data-rich-editor]');
            const visual = editor.querySelector('[data-editor-visual]');
            if (visual && !plain.hidden) {
                const text = plain.value.trim();
                setValue(plain, text ? text + '\n\n' + draft.text : draft.text);
            } else {
                editor.querySelector('[data-editor-mode="text"]').click();
                const fragments = draft.text.split(/\n\s*\n/).map(text => {
                    const p = document.createElement('p');
                    p.textContent = text;
                    return p;
                });
                visual.append(...fragments);
                visual.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
        apply.disabled = true;
        status.textContent = 'Draft added. Review the editor and use its Save button when ready.';
    });
});
