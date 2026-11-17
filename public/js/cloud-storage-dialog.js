(() => {
    const dialog = document.querySelector('[data-cloud-storage-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    dialog.removeAttribute('open');
    dialog.showModal();
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        window.location.assign(dialog.querySelector('[data-cloud-storage-close]').href);
    });
})();
