const queue = document.querySelector('[data-review-queue]');
const dialog = queue?.querySelector('[data-review-dialog]');

if (dialog?.showModal) {
    const choices = [...queue.querySelectorAll('[data-review-choice]')];
    const selectAll = queue.querySelector('[data-review-select-all]');
    const confirm = queue.querySelector('[data-review-confirm]');
    const count = queue.querySelector('[data-review-count]');
    const publishList = dialog.querySelector('[data-review-publish-list]');
    const publish = dialog.querySelector('[data-review-publish]');
    const form = dialog.querySelector('form');

    const selection = () => choices.filter(choice => choice.checked);

    function updateSelection() {
        const selectedCount = selection().length;
        selectAll.checked = selectedCount === choices.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < choices.length;
        count.textContent = selectedCount;
        confirm.disabled = selectedCount === 0;
        confirm.setAttribute('aria-label', `Approve and publish ${selectedCount} selected courses`);
    }

    selectAll.addEventListener('change', () => {
        choices.forEach(choice => { choice.checked = selectAll.checked; });
        updateSelection();
    });

    choices.forEach(choice => choice.addEventListener('change', updateSelection));
    confirm.addEventListener('click', () => {
        const selected = selection();
        if (selected.length === 0) return;

        publishList.replaceChildren(...selected.map(choice => {
            const item = document.createElement('li');
            item.textContent = choice.closest('[data-review-course]').querySelector('[data-review-title]').textContent.trim();
            return item;
        }));
        publish.textContent = `Publish ${selected.length} ${selected.length === 1 ? 'course' : 'courses'}`;
        dialog.showModal();
    });

    dialog.querySelector('[data-review-cancel]').addEventListener('click', () => dialog.close());
    form.addEventListener('submit', event => {
        if (selection().length === 0) {
            event.preventDefault();
            dialog.close();
            updateSelection();
            return;
        }

        publish.disabled = true;
        publish.textContent = 'Publishing…';
    });

    window.addEventListener('pageshow', () => {
        publish.disabled = false;
        if (dialog.open) dialog.close();
        updateSelection();
    });

    updateSelection();
    queue.querySelectorAll('[data-review-selection]').forEach(control => { control.hidden = false; });
}
