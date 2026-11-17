document.querySelectorAll<HTMLElement>('[data-detail-share]').forEach((panel) => {
    const button = panel.querySelector<HTMLButtonElement>('[data-share-url]');
    const status = panel.querySelector<HTMLElement>('[data-share-status]');
    const copy = panel.querySelector<HTMLInputElement>('[data-share-copy]');
    if (!button || !status || !copy) return;

    button.addEventListener('click', async () => {
        const url = button.dataset.shareUrl;
        if (!url) return;
        status.textContent = '';
        copy.hidden = true;

        if (typeof navigator.share === 'function') {
            try {
                await navigator.share({ title: button.dataset.shareTitle, url });
                return;
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') return;
            }
        }

        try {
            await navigator.clipboard.writeText(url);
            status.textContent = button.dataset.shareCopied ?? '';
        } catch {
            status.textContent = button.dataset.shareFallback ?? '';
            copy.hidden = false;
            copy.focus();
            copy.select();
        }
    });
});
