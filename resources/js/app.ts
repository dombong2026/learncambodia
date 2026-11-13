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
