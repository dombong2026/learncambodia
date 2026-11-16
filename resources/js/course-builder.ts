const builder = document.querySelector<HTMLElement>('[data-course-builder]');

if (builder) {
    const editable = builder.querySelector<HTMLFormElement>('form[data-builder-edit]');
    const notice = builder.querySelector<HTMLElement>('[data-builder-unsaved]')!;
    const values = (form: HTMLFormElement) => {
        const fields: [string, FormDataEntryValue][] = [];
        new FormData(form).forEach((value, name) => fields.push([name, value]));
        return JSON.stringify(fields);
    };
    const initial = editable ? values(editable) : '';
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
