const shell = document.querySelector<HTMLElement>('[data-workspace]');
const sidebar = document.querySelector<HTMLElement>('[data-sidebar]');
const overlay = document.querySelector<HTMLElement>('[data-sidebar-overlay]');
const trigger = document.querySelector<HTMLButtonElement>('[data-sidebar-toggle]');
const closeButton = document.querySelector<HTMLButtonElement>('[data-sidebar-close]');

function setSidebar(open: boolean): void {
    if (!sidebar || !overlay || !trigger) return;
    sidebar.classList.toggle('translate-x-0', open);
    sidebar.classList.toggle('-translate-x-full', !open);
    overlay.hidden = !open;
    trigger.setAttribute('aria-expanded', String(open));
    if (open) sidebar.querySelector<HTMLAnchorElement>('a')?.focus();
}

if (shell && sidebar) {
    trigger?.addEventListener('click', () => setSidebar(true));
    closeButton?.addEventListener('click', () => setSidebar(false));
    overlay?.addEventListener('click', () => setSidebar(false));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && overlay && !overlay.hidden) {
            setSidebar(false);
            trigger?.focus();
        }
    });

    sidebar.querySelectorAll<HTMLElement>('[data-nav-group]').forEach(group => {
        if (group.querySelector('[aria-current="page"]')) group.setAttribute('open', '');
    });

    sidebar.querySelectorAll<HTMLAnchorElement>('[data-workspace-link]').forEach(link => {
        link.addEventListener('click', () => setSidebar(false));
    });

    const search = document.querySelector<HTMLInputElement>('[data-workspace-search]');
    const noResults = document.querySelector<HTMLElement>('[data-workspace-no-results]');
    search?.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase();
        let visible = 0;

        sidebar.querySelectorAll<HTMLElement>('[data-nav-group]').forEach(group => {
            const heading = group.querySelector<HTMLElement>('summary')?.textContent?.toLocaleLowerCase() ?? '';
            let groupHasResults = false;
            const groupMatches = query !== '' && heading.includes(query);
            group.querySelectorAll<HTMLAnchorElement>('[data-workspace-link]').forEach(link => {
                const matches = !query || groupMatches || (link.textContent ?? '').toLocaleLowerCase().includes(query);
                link.hidden = !matches;
                groupHasResults ||= matches;
                if (matches) visible++;
            });
            group.hidden = !groupHasResults;
            if (query && groupHasResults) group.setAttribute('open', '');
        });

        if (noResults) noResults.hidden = visible > 0;
        if (query && window.matchMedia('(max-width: 1023px)').matches) setSidebar(true);
    });

    document.querySelectorAll<HTMLElement>('[data-workspace-toast]').forEach(toast => {
        window.setTimeout(() => toast.remove(), 5000);
    });

    document.querySelectorAll<HTMLButtonElement>('[data-copy-secret]').forEach(button => {
        button.addEventListener('click', async () => {
            const targetId = button.dataset.copySecret;
            const value = targetId ? document.getElementById(targetId)?.textContent?.trim() : '';
            if (!value) return;
            try {
                await navigator.clipboard.writeText(value);
                button.textContent = 'Copied';
            } catch {
                button.textContent = 'Select the token and copy manually';
            }
        });
    });
}
