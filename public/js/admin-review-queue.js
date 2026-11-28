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

queue?.querySelectorAll('[data-course-review-trigger]').forEach(trigger => {
    const courseDialog = document.getElementById(trigger.getAttribute('aria-controls'));
    if (!courseDialog?.showModal) return;

    const decision = courseDialog.querySelector('[data-course-review-actions]');
    const rejection = courseDialog.querySelector('[data-course-review-rejection]');
    const rejectionActions = courseDialog.querySelector('[data-course-review-reject-actions]');
    const reject = courseDialog.querySelector('[data-course-review-reject]');

    function showRejection(show) {
        decision.hidden = show;
        rejection.hidden = !show;
        rejectionActions.hidden = !show;
    }

    trigger.addEventListener('click', event => {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        showRejection(false);
        courseDialog.showModal();
    });

    courseDialog.querySelector('[data-course-review-close]').addEventListener('click', () => courseDialog.close());
    reject.addEventListener('click', () => {
        showRejection(true);
        rejection.elements.note.focus();
    });
    courseDialog.querySelector('[data-course-review-back]').addEventListener('click', () => {
        showRejection(false);
        reject.focus();
    });
    courseDialog.addEventListener('close', () => trigger.focus());

    if (courseDialog.hasAttribute('data-retry-rejection')) {
        showRejection(true);
        courseDialog.showModal();
        rejection.elements.note.focus();
    }
});
