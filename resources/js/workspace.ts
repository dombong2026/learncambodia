import './rich-editor';
import './editorial-media';
import './media-library';
import './featured-video';
import './course-builder';
import './examination';

const shell = document.querySelector<HTMLElement>('[data-workspace]');
const sidebar = document.querySelector<HTMLElement>('[data-sidebar]');
const overlay = document.querySelector<HTMLElement>('[data-sidebar-overlay]');
const trigger = document.querySelector<HTMLButtonElement>('[data-sidebar-toggle]');
const closeButton = document.querySelector<HTMLButtonElement>('[data-sidebar-close]');
const page = document.querySelector<HTMLElement>('[data-workspace-page]');
const mobileNav = document.querySelector<HTMLElement>('[data-workspace-mobile-nav]');

const desktop = window.matchMedia('(min-width: 64rem)');
const sidebarPreference = 'learncambodia.workspace.sidebar';
let desktopOpen = document.documentElement.dataset.desktopSidebar !== 'closed';
let mobileOpen = false;

function renderSidebar(): void {
    if (!shell || !sidebar || !overlay || !trigger) return;
    const open = desktop.matches ? desktopOpen : mobileOpen;
    document.documentElement.dataset.desktopSidebar = desktopOpen ? 'open' : 'closed';
    shell.dataset.mobileSidebar = mobileOpen && !desktop.matches ? 'open' : 'closed';
    sidebar.inert = !open;
    const mobileDrawer = !desktop.matches && mobileOpen;
    if (page) page.inert = mobileDrawer;
    if (mobileNav) mobileNav.inert = mobileDrawer;
    overlay.hidden = desktop.matches || !mobileOpen;
    trigger.setAttribute('aria-expanded', String(open));
}

function setSidebar(open: boolean): void {
    if (desktop.matches) {
        desktopOpen = open;
        try { localStorage.setItem(sidebarPreference, open ? 'open' : 'closed'); } catch { /* local setting */ }
    } else {
        mobileOpen = open;
    }
    renderSidebar();
    if (open && !desktop.matches) closeButton?.focus();
    if (!open) trigger?.focus();
}

if (shell && sidebar) {
    renderSidebar();
    desktop.addEventListener('change', () => {
        mobileOpen = false;
        renderSidebar();
    });
    trigger?.addEventListener('click', () => setSidebar(!(desktop.matches ? desktopOpen : mobileOpen)));
    closeButton?.addEventListener('click', () => setSidebar(false));
    overlay?.addEventListener('click', () => setSidebar(false));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !desktop.matches && mobileOpen) {
            setSidebar(false);
        }
    });

    sidebar.querySelectorAll<HTMLElement>('[data-nav-group]').forEach(group => {
        if (group.querySelector('[aria-current="page"]')) group.setAttribute('open', '');
    });

    sidebar.querySelectorAll<HTMLAnchorElement>('[data-workspace-link]').forEach(link => {
        link.addEventListener('click', () => { if (!desktop.matches) setSidebar(false); });
    });

    // Desktop and mobile search share one filtering state; entering text inside the
    // mobile sidebar must never steal input focus by reopening the navigation.
    const searches = Array.from(document.querySelectorAll<HTMLInputElement>('[data-workspace-search]'));
    const noResults = document.querySelector<HTMLElement>('[data-workspace-no-results]');
    const filterNavigation = (value: string, source: HTMLInputElement): void => {
        searches.forEach(input => { if (input !== source) input.value = value; });
        const query = value.trim().toLocaleLowerCase();
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
        if (query && source.id !== 'workspace-search-mobile' && (desktop.matches ? !desktopOpen : !mobileOpen)) {
            setSidebar(true);
            const search = desktop.matches ? source : document.getElementById('workspace-search-mobile');
            search?.focus({ preventScroll: true });
        }
    };
    searches.forEach(input => input.addEventListener('input', () => filterNavigation(input.value, input)));

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

const avatar = document.querySelector<HTMLInputElement>('[data-avatar-input]');
const avatarPreview = document.querySelector<HTMLImageElement>('[data-avatar-preview]');
if (avatar && avatarPreview) {
    const previous = avatarPreview.getAttribute('src');
    let previewUrl: string | null = null;
    avatar.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        const file = avatar.files?.[0];
        previewUrl = file ? URL.createObjectURL(file) : null;
        const source = previewUrl ?? previous;
        if (source) avatarPreview.src = source;
        else avatarPreview.removeAttribute('src');
        avatarPreview.hidden = !source;
        const remove = document.querySelector<HTMLInputElement>('[name="remove_avatar"]');
        if (file && remove) remove.checked = false;
    });
}

const notifications = document.querySelector<HTMLElement>('[data-workspace-notifications]');
if (notifications) {
    let refreshInFlight = false;
    const bell = notifications.querySelector<HTMLButtonElement>('[data-dropdown-trigger]')!;
    const preview = notifications.querySelector<HTMLElement>('[data-notification-preview]')!;
    const readAll = notifications.querySelector<HTMLFormElement>('[data-notification-readall]')!;
    const refresh = async (): Promise<void> => {
        if (document.hidden || refreshInFlight) return;
        refreshInFlight = true;
        try {
            const url = new URL(notifications.dataset.unreadUrl!, window.location.href);
            if (bell.getAttribute('aria-expanded') === 'true' && !preview.contains(document.activeElement)) {
                url.searchParams.set('preview', '1');
            }
            const result = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!result.ok || !result.headers.get('Content-Type')?.includes('application/json')) return;
            const { unread, preview: html } = await result.json();
            if (typeof html === 'string' && !preview.contains(document.activeElement)) preview.innerHTML = html;
            readAll.hidden = unread === 0;
            bell.setAttribute('aria-label', unread ? `Notifications, ${unread} unread` : 'Notifications');
            const count = notifications.querySelector<HTMLElement>('[data-notification-count]');
            if (count) {
                count.textContent = unread > 99 ? '99+' : String(unread);
                count.hidden = unread === 0;
            }
        } catch { /* The next refresh retries. */ }
        finally { refreshInFlight = false; }
    };
    bell.addEventListener('click', () => { if (bell.getAttribute('aria-expanded') === 'true') void refresh(); });
    window.setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', refresh);
}
