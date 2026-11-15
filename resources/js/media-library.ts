const library = document.querySelector<HTMLElement>('[data-library]');

if (library) {
    const content = library;
    const grid = content.querySelector<HTMLElement>('[data-library-columns]');
    const search = content.querySelector<HTMLFormElement>('form[role="search"]')!;
    const preview = content.querySelector<HTMLDialogElement>('#media-preview')!;
    const picture = preview.querySelector<HTMLImageElement>('[data-preview-image]')!;
    const player = preview.querySelector<HTMLElement>('[data-compact-video]')!;
    const video = player.querySelector('video')!;
    const previewError = preview.querySelector<HTMLElement>('[data-preview-error]')!;
    let trigger: HTMLElement | undefined;
    let resizeTimer: ReturnType<typeof setTimeout>;

    function fitGrid(): void {
        const field = search.elements.namedItem('columns') as HTMLInputElement;
        const previous = Number(field.value);
        const width = content.clientWidth;
        const columns = width >= 1050 ? 4 : width >= 760 ? 3 : width >= 460 ? 2 : 1;
        if (columns === previous) return;

        const url = new URL(location.href);
        const page = Math.max(1, Number(url.searchParams.get('page')) || 1);
        const newPage = Math.floor((page - 1) * previous / columns) + 1;
        if (!grid || (page === 1 && grid.childElementCount <= 4 * columns && !content.querySelector('.library-pagination'))) {
            field.value = String(columns);
            if (grid) grid.dataset.libraryColumns = String(columns);
            url.searchParams.set('columns', String(columns));
            history.replaceState(history.state, '', url);
            return;
        }

        url.searchParams.set('columns', String(columns));
        url.searchParams.set('page', String(newPage));
        location.replace(url);
    }

    new ResizeObserver(() => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(fitGrid, 180);
    }).observe(content);
    fitGrid();

    function resetPreview(): void {
        player.hidden = true;
        delete player.dataset.controlsActive;
        video.pause();
        video.playbackRate = 1;
        video.removeAttribute('src');
        video.load();
        picture.removeAttribute('src');
        picture.alt = '';
        previewError.hidden = true;
    }

    const showPreviewError = () => {
        if (preview.open) previewError.hidden = false;
    };
    video.addEventListener('error', showPreviewError);
    picture.addEventListener('error', showPreviewError);

    function openDialog(id: string, opener: HTMLElement): void {
        const dialog = content.querySelector<HTMLDialogElement>('#' + id);
        if (!dialog) return;
        const card = opener.closest<HTMLElement>('[data-library-item]');
        if (id !== 'media-upload' && !card) return;
        const menu = opener.closest<HTMLDetailsElement>('[data-library-menu]');
        trigger = menu?.querySelector<HTMLElement>('summary') ?? opener;
        if (menu) menu.open = false;

        if (card) {
            const { title, src, kind, format, size, update, delete: remove } = card.dataset;
            const label = dialog.querySelector<HTMLElement>('[data-library-title]');
            if (label) label.textContent = title ?? '';
            if (id === 'media-preview') {
                dialog.querySelector<HTMLElement>('[data-library-meta]')!.textContent = format + ' · ' + size;
                picture.hidden = kind === 'video';
                player.hidden = kind !== 'video';
                if (kind === 'video') {
                    video.setAttribute('aria-label', title ?? 'Video preview');
                    video.src = src!;
                } else {
                    picture.alt = title ?? '';
                    picture.src = src!;
                }
            } else {
                const form = dialog.querySelector<HTMLFormElement>('form')!;
                form.reset();
                form.action = (id === 'media-edit' ? update : remove)!;
                if (id === 'media-edit') {
                    (form.elements.namedItem('title') as HTMLInputElement).value = title ?? '';
                    (form.elements.namedItem('replacement') as HTMLInputElement).accept = kind === 'video'
                        ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/gif,image/webp';
                }
            }
        }

        dialog.showModal();
        if (id === 'media-preview' && !player.hidden) {
            player.dispatchEvent(new Event('previewopen'));
        }
    }

    document.addEventListener('click', event => {
        if (!(event.target instanceof Element)) return;
        const opener = event.target.closest<HTMLElement>('[data-library-open]');
        if (opener) openDialog(opener.dataset.libraryOpen!, opener);

        const close = event.target.closest('[data-library-close]');
        if (close) close.closest('dialog')?.close();
        if (event.target instanceof HTMLDialogElement) event.target.close();

        content.querySelectorAll<HTMLDetailsElement>('[data-library-menu][open]').forEach(menu => {
            if (!menu.contains(event.target as Element)) menu.open = false;
        });
    });

    content.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !(event.target instanceof Element)) return;
        const menu = event.target.closest<HTMLDetailsElement>('[data-library-menu][open]');
        if (menu) {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        }
    });

    content.querySelectorAll<HTMLDialogElement>('dialog').forEach(dialog => {
        dialog.addEventListener('close', () => {
            if (dialog === preview) resetPreview();
            trigger?.focus();
        });
    });

    content.querySelectorAll<HTMLFormElement>('[data-library-submit]').forEach(form => {
        form.addEventListener('submit', () => {
            const button = form.querySelector<HTMLButtonElement>('button[type="submit"]')!;
            button.disabled = true;
            button.textContent = 'Working…';
        });
    });
}
