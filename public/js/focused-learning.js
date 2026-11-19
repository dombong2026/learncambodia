document.querySelectorAll('[data-message-scroll]').forEach((container) => {
    const refreshUrl = container.dataset.refreshUrl;
    if (!refreshUrl) return;

    const scrollToLatest = () => { container.scrollTop = container.scrollHeight; };
    scrollToLatest();

    const refresh = async () => {
        if (document.hidden || !container.isConnected || container.contains(document.activeElement)) return;

        try {
            const response = await fetch(refreshUrl, { cache: 'no-store', credentials: 'same-origin' });
            if ([401, 403, 404].includes(response.status) || response.redirected) {
                window.clearInterval(interval);
                window.location.reload();
                return;
            }
            if (!response.ok) return;

            const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
            const incoming = documentFragment.querySelector('[data-message-history]');
            const current = container.querySelector('[data-message-history]');
            if (!incoming || !current || incoming.dataset.latest === current.dataset.latest) return;

            const nearLatest = container.scrollHeight - container.scrollTop - container.clientHeight < 40;
            current.replaceWith(incoming);
            if (nearLatest) scrollToLatest();
        } catch {
            // The existing conversation and typed draft stay usable while disconnected.
        }
    };

    const interval = window.setInterval(refresh, 8000);
    window.addEventListener('pagehide', (event) => {
        if (!event.persisted) window.clearInterval(interval);
    });
});
