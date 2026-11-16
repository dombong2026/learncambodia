export function initLessonQuizEditor(editor: HTMLElement): void {
    const questions = editor.querySelector<HTMLElement>('[data-quiz-questions]')!;
    const add = editor.querySelector<HTMLButtonElement>('[data-quiz-add]')!;
    const template = editor.querySelector<HTMLTemplateElement>('[data-quiz-template]')!;
    const limit = Number(add.dataset.limit);

    function updateFields(): void {
        Array.from(questions.children).forEach((question, index) => {
            question.querySelector('[data-quiz-question-label]')!.textContent = `Question ${index + 1}`;
            question.querySelectorAll<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>('[name]')
                .forEach(field => { field.name = field.name.replace(/^quiz_questions\[\d+\]/, `quiz_questions[${index}]`); });
        });
        add.disabled = questions.children.length >= limit;
    }

    function changed(): void {
        updateFields();
        editor.dispatchEvent(new Event('change', { bubbles: true }));
    }

    add.hidden = false;
    updateFields();
    add.addEventListener('click', () => {
        if (questions.children.length >= limit) return;

        questions.append(template.content.cloneNode(true));
        changed();
        questions.lastElementChild?.querySelector('textarea')?.focus();
    });

    questions.addEventListener('click', event => {
        const remove = (event.target as Element).closest<HTMLButtonElement>('[data-quiz-remove]');
        const question = remove?.closest<HTMLElement>('[data-quiz-question]');
        if (!question) return;

        const neighbor = question.nextElementSibling ?? question.previousElementSibling;
        question.remove();
        changed();
        (neighbor?.querySelector('textarea') ?? add).focus();
    });

    editor.addEventListener('invalid', () => editor.setAttribute('open', ''), true);
}
