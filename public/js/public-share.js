document.querySelectorAll('[data-public-share]').forEach((share) => {
    const trigger = share.querySelector('summary');
    const url = share.querySelector('[data-public-share-url]');
    const copy = share.querySelector('[data-public-share-copy]');
    const native = share.querySelector('[data-public-share-native]');
    const status = share.querySelector('[data-public-share-status]');

    copy.hidden = false;
    url.hidden = true;
    native.hidden = typeof navigator.share !== 'function';

    function selectLink() {
        status.textContent = status.dataset.fallback;
        url.hidden = false;
        url.focus();
        url.select();
    }

    copy.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(url.value);
            url.hidden = true;
            status.textContent = status.dataset.copied;
        } catch {
            selectLink();
        }
    });

    native.addEventListener('click', async () => {
        try {
            await navigator.share({ title: url.dataset.title, url: url.value });
        } catch (error) {
            if (error.name !== 'AbortError') selectLink();
        }
    });

    share.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !share.open) return;
        share.open = false;
        trigger.focus();
    });
    document.addEventListener('click', (event) => {
        if (share.open && !share.contains(event.target)) share.open = false;
    });
});
