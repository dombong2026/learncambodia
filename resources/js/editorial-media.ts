async function uploadMedia(file: File, url: string): Promise<MediaItem> {
    if (file.size > 50 * 1024 * 1024) throw new Error('Files can be up to 50 MB.');
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (!csrf) throw new Error('Reload the page before uploading.');
    const data = new FormData();
    data.append('file', file);
    const response = await fetch(url, {
        method: 'POST',
        body: data,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
    });
    if (response.ok) return await response.json() as MediaItem;
    if (response.status === 413) throw new Error('The file exceeds the server’s upload limit.');
    const error = await response.json().catch(() => ({})) as { message?: string };
    throw new Error(error.message ?? 'Upload failed. Please try again.');
}

type MediaItem = { id: number; name: string; mime: string; url: string };
type MediaPage = { items: MediaItem[]; page: number; last_page: number };

const editorial = document.querySelector<HTMLFormElement>('#editorial-form');
const slugField = editorial?.querySelector<HTMLInputElement>('[data-editor-slug]');
const copySlug = editorial?.querySelector<HTMLButtonElement>('[data-copy-slug]');
const copyStatus = editorial?.querySelector<HTMLElement>('[data-copy-slug-status]');
if (slugField && copySlug && copyStatus) {
    copySlug.addEventListener('click', async () => {
        if (!slugField.value.trim()) {
            const title = editorial?.querySelector<HTMLInputElement>('[data-document-title]')?.value.trim() ?? '';
            if (!title) {
                slugField.focus();
                copyStatus.textContent = 'Enter a title or URL slug first.';
                return;
            }
            const url = new URL(copySlug.dataset.slugUrl!, window.location.origin);
            url.searchParams.set('title', title);
            let suggested = '';
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (response.ok) suggested = ((await response.json()) as { slug: string }).slug;
            } catch { /* Prefer the typed slug if the draft title cannot be resolved. */ }
            if (!slugField.value && !suggested) {
                slugField.focus();
                copyStatus.textContent = 'Enter a URL slug first.';
                return;
            }
            if (!slugField.value) slugField.value = suggested;
        }
        try {
            await navigator.clipboard.writeText(slugField.value);
            copyStatus.textContent = 'Slug copied.';
            copySlug.title = 'Copied';
        } catch {
            slugField.select();
            copyStatus.textContent = 'Slug selected. Press your copy shortcut.';
            copySlug.title = 'Slug selected';
        }
    });
    slugField.addEventListener('input', () => { copySlug.title = 'Click to copy'; });
}

const settings = document.querySelector<HTMLElement>('[data-featured-editor]');
const picker = document.querySelector<HTMLDialogElement>('[data-media-picker]');
if (settings && picker) {
    const kind = settings.querySelector<HTMLSelectElement>('[data-featured-kind]')!;
    const assetId = settings.querySelector<HTMLInputElement>('[data-featured-id]')!;
    const assetName = settings.querySelector<HTMLElement>('[data-featured-name]')!;
    const search = picker.querySelector<HTMLFormElement>('[data-media-search]')!;
    const status = picker.querySelector<HTMLElement>('[data-media-status]')!;
    const results = picker.querySelector<HTMLElement>('[data-media-results]')!;
    const previous = picker.querySelector<HTMLButtonElement>('[data-media-prev]')!;
    const next = picker.querySelector<HTMLButtonElement>('[data-media-next]')!;
    const upload = picker.querySelector<HTMLInputElement>('[data-media-upload]')!;
    let page = 1;
    let selectedKind = kind.value;
    let request: AbortController | undefined;

    function setFields(): void {
        settings!.querySelector<HTMLElement>('[data-featured-asset]')!.hidden = !['image', 'video'].includes(kind.value);
        settings!.querySelector<HTMLElement>('[data-featured-alt]')!.hidden = kind.value !== 'image';
        settings!.querySelector<HTMLElement>('[data-featured-youtube]')!.hidden = kind.value !== 'youtube';
        settings!.querySelector<HTMLElement>('[data-featured-color]')!.hidden = kind.value !== 'color';
    }

    function choose(item: MediaItem): void {
        assetId.value = String(item.id);
        assetName.textContent = item.name;
        assetId.dispatchEvent(new Event('change', { bubbles: true }));
        picker!.close();
    }

    async function load(targetPage: number): Promise<void> {
        request?.abort();
        const controller = new AbortController();
        request = controller;
        previous.disabled = next.disabled = true;
        results.replaceChildren();
        status.textContent = 'Loading files…';
        const url = new URL(picker!.dataset.indexUrl!, window.location.origin);
        url.searchParams.set('kind', kind.value);
        url.searchParams.set('page', String(targetPage));
        url.searchParams.set('q', String(new FormData(search).get('q') ?? ''));
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) throw new Error();
            const data = await response.json() as MediaPage;
            if (controller.signal.aborted) return;
            page = data.page;
            previous.disabled = page <= 1;
            next.disabled = page >= data.last_page;
            for (const item of data.items) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'min-w-0 rounded-xl border border-slate-200 p-3 text-left text-sm font-semibold hover:border-blue-600 dark:border-slate-700';
                if (kind.value === 'image') {
                    const image = document.createElement('img');
                    image.src = item.url;
                    image.alt = '';
                    image.loading = 'lazy';
                    image.className = 'mb-2 aspect-video w-full rounded-lg bg-slate-950 object-contain';
                    button.append(image);
                }
                const name = document.createElement('span');
                name.className = 'block break-words';
                name.textContent = item.name;
                button.append(name);
                button.addEventListener('click', () => choose(item));
                results.append(button);
            }
            status.textContent = data.items.length ? `Page ${page} of ${data.last_page}` : 'No matching files.';
        } catch {
            if (!controller.signal.aborted) status.textContent = 'Could not load files. Search to retry.';
        }
    }

    kind.addEventListener('change', () => {
        if (selectedKind !== kind.value) {
            assetId.value = '';
            assetName.textContent = 'Choose a file';
            selectedKind = kind.value;
        }
        setFields();
    });
    setFields();
    settings.querySelector('[data-open-media-library]')?.addEventListener('click', () => {
        upload.accept = kind.value === 'image' ? 'image/jpeg,image/png,image/gif,image/webp' : 'video/mp4,video/webm';
        picker.showModal();
        void load(1);
    });
    picker.querySelector('[data-close-media-library]')?.addEventListener('click', () => picker.close());
    picker.addEventListener('close', () => request?.abort());
    search.addEventListener('submit', event => {
        event.preventDefault();
        void load(1);
    });
    previous.addEventListener('click', () => void load(page - 1));
    next.addEventListener('click', () => void load(page + 1));
    upload.addEventListener('change', async () => {
        const file = upload.files?.[0];
        if (!file) return;
        request?.abort();
        upload.disabled = true;
        status.textContent = 'Uploading…';
        try {
            const uploaded = await uploadMedia(file, picker.dataset.uploadUrl!);
            if (uploaded.mime.startsWith(kind.value + '/')) choose(uploaded);
            else status.textContent = 'File added to the library. Choose its file type to use it here.';
        } catch (error) {
            status.textContent = error instanceof Error ? error.message : 'Upload failed.';
        } finally {
            upload.value = '';
            upload.disabled = false;
        }
    });
}
