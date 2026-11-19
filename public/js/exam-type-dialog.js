(() => {
    const dialog = document.querySelector('[data-exam-type-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    dialog.removeAttribute('open');
    dialog.showModal();
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        window.location.assign(dialog.querySelector('[data-exam-type-close]').href);
    });
})();
