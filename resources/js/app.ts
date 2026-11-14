function initMenus(): void {
    const menus = Array.from(document.querySelectorAll<HTMLElement>('[data-dropdown]'));
    const close = (menu: HTMLElement): void => {
        menu.querySelector<HTMLElement>('[data-dropdown-panel]')?.setAttribute('hidden', '');
        menu.querySelector<HTMLElement>('[data-dropdown-trigger]')?.setAttribute('aria-expanded', 'false');
    };

    for (const menu of menus) {
        const trigger = menu.querySelector<HTMLButtonElement>('[data-dropdown-trigger]');
        const panel = menu.querySelector<HTMLElement>('[data-dropdown-panel]');
        if (!trigger || !panel) continue;

        trigger.addEventListener('click', () => {
            const opening = panel.hasAttribute('hidden');
            menus.forEach(close);
            if (opening) {
                panel.removeAttribute('hidden');
                trigger.setAttribute('aria-expanded', 'true');
            }
        });
    }

    document.addEventListener('click', (event: MouseEvent) => {
        if (!(event.target instanceof Node)) return;
        for (const menu of menus) if (!menu.contains(event.target)) close(menu);
    });
    document.addEventListener('keydown', (event: KeyboardEvent) => {
        if (event.key === 'Escape') {
            menus.forEach(close);
            (document.activeElement as HTMLElement | null)?.blur();
        }
    });
}

function initCategoryTray(): void {
    const tray = document.querySelector<HTMLElement>('[data-category-tray]');
    if (!tray) return;
    document.querySelectorAll<HTMLButtonElement>('[data-tray-scroll]').forEach((button) => {
        button.addEventListener('click', () => {
            const direction = Number(button.dataset.trayScroll) || 1;
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            tray.scrollBy({ left: direction * Math.min(320, tray.clientWidth * 0.75), behavior: reducedMotion ? 'instant' : 'smooth' });
        });
    });
}

initMenus();
initCategoryTray();


function initPasswordVisibility(): void {
    document.querySelectorAll<HTMLButtonElement>('[data-password-toggle]').forEach((button) => {
        const id = button.dataset.passwordFor;
        const input = id ? document.getElementById(id) : null;
        if (!(input instanceof HTMLInputElement)) return;

        button.addEventListener('click', () => {
            const showing = input.type === 'password';
            input.type = showing ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(showing));
            button.setAttribute('aria-label',
                showing ? button.dataset.hideLabel ?? 'Hide password' :
                button.dataset.showLabel ?? 'Show password');
            input.focus({ preventScroll: true });
        });
    });
}

initPasswordVisibility();


// Keep language visible as a validated ?lang= code on the current URL and
// carry it through same-origin navigational links, without rewriting route
// parameters or breaking existing server-side Laravel route names.
// The middleware still remembers the language when JavaScript is disabled.
function initLocaleUrls(): void {
    const path = window.location.pathname;
    if (path === '/install' || path.startsWith('/install/') ||
        path.startsWith('/api/') || path === '/up') return;

    const supported = ['en', 'km', 'zh'];
    const documentLocale = document.documentElement.lang.toLowerCase().split('-')[0];
    const current = new URL(window.location.href);
    const explicit = current.searchParams.get('lang');
    const locale = explicit && supported.includes(explicit) ? explicit :
        (supported.includes(documentLocale) ? documentLocale : 'en');

    if (explicit !== locale) {
        current.searchParams.set('lang', locale);
        window.history.replaceState(window.history.state, '', current.pathname + current.search + current.hash);
    }

    for (const link of Array.from(document.querySelectorAll<HTMLAnchorElement>('a[href]'))) {
        if (link.hasAttribute('download')) continue;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || /^(mailto:|tel:|javascript:)/i.test(href)) continue;
        try {
            const destination = new URL(href, window.location.href);
            if (destination.origin !== window.location.origin ||
                destination.pathname === '/install' ||
                destination.pathname.startsWith('/install/')) continue;
            destination.searchParams.set('lang', locale);
            link.setAttribute('href', destination.pathname + destination.search + destination.hash);
        } catch { /* Ignore malformed external URLs; no unsafe navigation. */ }
    }

    for (const form of Array.from(document.querySelectorAll<HTMLFormElement>('form[method="GET"],form[method="get"]'))) {
        if (form.querySelector('[name="lang"]')) continue;
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'lang';
        hidden.value = locale;
        form.append(hidden);
    }
}
initLocaleUrls();


// Shared site-wide theme: guest menu, account menu, public/auth/workspace pages.
// Stored only in this browser. No account changes, cookies or server requests.
function initThemeToggle(): void {
    const storageKey = 'learncambodia.theme.v1';
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    const controls = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-theme-toggle]'));

    const savedTheme = (): 'light' | 'dark' | null => {
        try {
            const value = window.localStorage.getItem(storageKey);
            return value === 'light' || value === 'dark' ? value : null;
        } catch {
            return null; // Local storage may be unavailable in private browsers.
        }
    };

    const apply = (dark: boolean): void => {
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')
            ?.setAttribute('content', dark ? '#0b1d39' : '#f8fafc');
        for (const control of controls) control.setAttribute('aria-checked', String(dark));
    };

    const refresh = (): void => {
        const choice = savedTheme();
        apply(choice === 'dark' || (choice === null && system.matches));
    };

    refresh();

    for (const control of controls) {
        control.addEventListener('click', () => {
            const dark = !document.documentElement.classList.contains('dark');
            try { window.localStorage.setItem(storageKey, dark ? 'dark' : 'light'); }
            catch { /* Keep working for this page even without storage. */ }
            apply(dark);
        });
    }

    system.addEventListener('change', () => {
        if (savedTheme() === null) refresh();
    });

    window.addEventListener('storage', (event: StorageEvent) => {
        if (event.key === storageKey || event.key === null) refresh();
    });
}

initThemeToggle();
