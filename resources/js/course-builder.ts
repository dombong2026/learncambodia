import { initCurriculumOrder } from './curriculum-order';
import { initLessonQuizEditor } from './lesson-quiz-editor';

const builder = document.querySelector<HTMLElement>('[data-course-builder]');

if (builder) {
    const editable = builder.querySelector<HTMLFormElement>('form[data-builder-edit]');
    const notice = builder.querySelector<HTMLElement>('[data-builder-unsaved]')!;
    const values = (form: HTMLFormElement) => {
        const fields: [string, FormDataEntryValue][] = [];
        new FormData(form).forEach((value, name) => fields.push([name, value]));
        return JSON.stringify(fields);
    };
    const quiz = editable?.querySelector<HTMLElement>('[data-lesson-quiz-editor]');
    if (quiz) initLessonQuizEditor(quiz);
    let initial = editable ? values(editable) : '';
    const reorderPending = initCurriculumOrder(builder, (list, order) => {
        if (!editable || editable.dataset.orderEditor !== list.dataset.orderList
            || editable.dataset.orderParent !== list.dataset.orderParent) return;

        const item = Number(editable.dataset.orderItem);
        const index = order.indexOf(item);
        if (index < 0 && item) return;
        const position = String(index < 0 ? order.length + 1 : index + 1);
        const field = editable.querySelector<HTMLInputElement>('input[name="position"]')!;
        const fields = JSON.parse(initial) as [string, FormDataEntryValue][];
        const saved = fields.find(([name]) => name === 'position');
        if (saved) {
            if (field.value === saved[1]) field.value = position;
            saved[1] = position;
            initial = JSON.stringify(fields);
            updateNotice();
        }
    });
    const isDirty = () => editable !== null && values(editable) !== initial;
    let proceeding = false;

    function updateNotice(): void {
        notice.hidden = !isDirty();
    }

    builder.addEventListener('input', updateNotice);
    builder.addEventListener('change', updateNotice);

    const confirmDiscard = () => !isDirty() || window.confirm('Discard unsaved changes? Choose Cancel to return and save.');

    document.addEventListener('click', event => {
        const link = (event.target as Element | null)?.closest<HTMLAnchorElement>('a[href]');
        if (!link || link.target === '_blank' || link.hasAttribute('download') || event.defaultPrevented ||
            event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const destination = new URL(link.href, location.href);
        if (destination.href !== location.href && !confirmDiscard()) event.preventDefault();
        else if (destination.origin !== location.origin || destination.pathname !== location.pathname || destination.search !== location.search) proceeding = true;
    });

    builder.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        if (reorderPending()) {
            event.preventDefault();
            return;
        }
        const form = event.target as HTMLFormElement;
        const warning = form.dataset.builderConfirm;
        if ((form !== editable && !confirmDiscard()) || (warning && !window.confirm(warning))) {
            event.preventDefault();
            return;
        }
        proceeding = true;
    });

    window.addEventListener('beforeunload', event => {
        if (!proceeding && isDirty()) event.preventDefault();
    });
    builder.dataset.builderReady = 'true';
    window.addEventListener('pageshow', () => {
        proceeding = false;
        updateNotice();
    });
}
