const updateKey = 'learncambodia.category-navigation.changed';
const marker = document.querySelector<HTMLTemplateElement>('[data-category-navigation]');

if (document.body.hasAttribute('data-category-navigation-changed')) {
    try {
        localStorage.setItem(updateKey, String(Date.now()));
    } catch {
        // Other tabs still refresh directly from the server.
    }
}

if (marker?.dataset.categoryNavigation && marker.parentElement) {
    const endpoint = marker.dataset.categoryNavigation;
    const tray = marker.parentElement;
    let pending: AbortController | null = null;

    const refresh = async (immediate = false): Promise<void> => {
        if (!immediate && (document.hidden || pending)) return;

        pending?.abort();
        const request = new AbortController();
        pending = request;
        try {
            const response = await fetch(endpoint, {
                cache: 'no-store',
                credentials: 'omit',
                signal: request.signal,
            });
            if (!response.ok || response.headers.get('X-Category-Navigation') !== '1') return;

            const fragment = document.createElement('template');
            fragment.innerHTML = await response.text();
            if (request.signal.aborted) return;

            const incoming = Array.from(fragment.content.querySelectorAll<HTMLAnchorElement>('a[data-category-short]'));
            const current = new Map(Array.from(tray.querySelectorAll<HTMLAnchorElement>('a[data-category-short]'))
                .map(link => [link.dataset.categoryShort, link]));
            const focused = document.activeElement;
            const activeCodes = new Set(incoming.map(link => link.dataset.categoryShort));
            for (const [code, link] of current) {
                if (!activeCodes.has(code)) link.remove();
            }

            let next: Element = marker;
            for (const link of incoming.reverse()) {
                const url = new URL(link.href, window.location.href);
                url.searchParams.set('lang', document.documentElement.lang);
                const href = url.pathname + url.search + url.hash;
                const existing = current.get(link.dataset.categoryShort);
                const category = existing ?? link;
                const label = link.getAttribute('aria-label') ?? '';
                if (category.getAttribute('href') !== href) category.setAttribute('href', href);
                if (category.title !== link.title) category.title = link.title;
                if (category.getAttribute('aria-label') !== label) category.setAttribute('aria-label', label);
                if (category.nextElementSibling !== next) tray.insertBefore(category, next);
                next = category;
            }

            if (focused?.hasAttribute('data-category-short') && !focused.isConnected) {
                tray.querySelector<HTMLAnchorElement>('[data-category-directory]')?.focus({ preventScroll: true });
            }
        } catch {
            // Keep the rendered links during a temporary network failure.
        } finally {
            if (pending === request) pending = null;
        }
    };

    window.addEventListener('pageshow', () => void refresh());
    document.addEventListener('visibilitychange', () => void refresh());
    window.addEventListener('storage', event => {
        if (event.key === updateKey) void refresh(true);
    });
    window.setInterval(() => void refresh(), 5000);
}
