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


// A local-only preference. No external account/profile synchronization or tracking.
const saver = document.querySelector<HTMLButtonElement>('[data-saver-toggle]');
const saverBody = document.querySelector<HTMLElement>('[data-workspace-body]');
if (saver && saverBody) {
    const storageKey = 'learncambodia.workspace.data-saver.v1';
    const onLabel = document.documentElement.lang === 'km' ? 'សន្សំទិន្នន័យ៖ បើក' : 'Data Saver on';
    const offLabel = document.documentElement.lang === 'km' ? 'សន្សំទិន្នន័យ៖ បិទ' : 'Data Saver off';
    const priorVideoSettings = new WeakMap<HTMLVideoElement, { autoplay: boolean, preload: HTMLMediaElement['preload'] }>();
    let enabled = false;
    try { enabled = window.localStorage.getItem(storageKey) === 'on'; } catch { /* private browser mode */ }
    const applySaver = (value: boolean): void => {
        enabled = value;
        saverBody.dataset.saver = enabled ? 'on' : 'off';
        saver.setAttribute('aria-pressed', String(enabled));
        saver.setAttribute('aria-label', enabled ? onLabel : offLabel);
        saver.title = enabled ? onLabel : offLabel;
        const span = saver.querySelector<HTMLElement>('[data-saver-label]');
        if (span) span.textContent = enabled ? onLabel : offLabel;
        document.querySelectorAll<HTMLVideoElement>('video').forEach(video => {
            if (enabled) {
                if (!priorVideoSettings.has(video)) priorVideoSettings.set(video, {
                    autoplay: video.autoplay, preload: video.preload,
                });
                video.pause();
                video.autoplay = false;
                video.preload = 'none';
            } else {
                const previous = priorVideoSettings.get(video);
                if (previous) {
                    video.autoplay = previous.autoplay;
                    video.preload = previous.preload;
                    priorVideoSettings.delete(video);
                }
            }
        });
        try { window.localStorage.setItem(storageKey, enabled ? 'on' : 'off'); } catch { /* local setting only */ }
    };
    applySaver(enabled);
    saver.addEventListener('click', () => applySaver(!enabled));
}
