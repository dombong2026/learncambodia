(() => {
    const dialog = document.querySelector('[data-social-auth-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    const close = dialog.querySelector('[data-social-auth-close]');
    dialog.removeAttribute('open');
    dialog.showModal();

    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        window.location.assign(close.href);
    });
})();
