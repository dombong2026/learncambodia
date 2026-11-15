// Compact, dependency-free rich editor. Only the server-side allowlist decides
// what is persisted/rendered; the client also sanitizes HTML before DOM insertion.
const allowed = new Set([
    'p','div','br','strong','b','em','i','u','s','strike','h1','h2','h3','h4','h5','h6',
    'blockquote','pre','code','ul','ol','li','hr','a','img','figure','figcaption',
    'table','thead','tbody','tr','td','th','span','sup','sub','video','audio','source',
]);
const discard = new Set(['script','style','iframe','object','embed','svg','math','form','input','button','link','meta','base']);
const mediaPattern = /^\/editor-media\/[1-9]\d*$/;

function safeCss(input: string, tag: string, node: Element): string {
    const properties: Record<string, string> = {};
    const items = input.toLowerCase().split(';');
    if (tag === 'font') {
        items.push('color:' + (node.getAttribute('color') ?? ''));
        const sizes = ['','12','14','16','18','24','32','36'];
        const size = Number(node.getAttribute('size'));
        if (size >= 1 && size <= 7) items.push('font-size:' + sizes[size] + 'px');
        const face = (node.getAttribute('face') ?? '').toLowerCase();
        if (['arial','georgia','monospace'].includes(face)) items.push('font-family:' + face);
    }
    for (const item of items) {
        const separator = item.indexOf(':');
        if (separator < 0) continue;
        const key = item.slice(0, separator).trim();
        let value = item.slice(separator + 1).trim();
        if (key === 'color' || key === 'background-color') {
            const rgb = /^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/.exec(value);
            if (rgb && rgb.slice(1).every(v => Number(v) <= 255)) {
                value = '#' + rgb.slice(1).map(v => Number(v).toString(16).padStart(2, '0')).join('');
            }
            if (/^#[a-f0-9]{3}([a-f0-9]{3})?$/i.test(value)) properties[key] = value;
        } else if (key === 'text-align' && ['left','center','right','justify'].includes(value)) {
            properties[key] = value;
        } else if (key === 'font-size' && /^(12|14|16|18|20|24|28|32|36)px$/.test(value)) {
            properties[key] = value;
        } else if (key === 'font-family' && ['arial','georgia','monospace'].includes(value)) {
            properties[key] = value;
        }
    }
    return Object.entries(properties).map(([key,value]) => key + ':' + value).join(';');
}

function safeMarkup(markup: string): string {
    const doc = new DOMParser().parseFromString(markup, 'text/html');
    const out = document.createElement('div');

    const copy = (node: Node): Node | null => {
        if (node.nodeType === Node.TEXT_NODE) return document.createTextNode(node.textContent ?? '');
        if (!(node instanceof Element)) return null;
        const tag = node.tagName.toLowerCase();
        if (discard.has(tag)) return null;
        const el = document.createElement(allowed.has(tag) ? tag : 'span');
        const style = safeCss(node.getAttribute('style') ?? '', tag, node);
        if (style) el.setAttribute('style', style);
        if (tag === 'a') {
            const href = node.getAttribute('href') ?? '';
            if (/^(https?:\/\/|mailto:|tel:|\/(?!\/))/i.test(href)) {
                el.setAttribute('href', href);
                el.setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
        if (['img','audio','video','source'].includes(tag)) {
            const src = node.getAttribute('src') ?? '';
            if (src && !mediaPattern.test(src) && ['img','source'].includes(tag)) return null;
            if (mediaPattern.test(src)) el.setAttribute('src', src);
            if (tag === 'img') {
                el.setAttribute('alt', (node.getAttribute('alt') ?? '').slice(0, 200));
                el.setAttribute('loading', 'lazy');
            }
            if (tag === 'audio' || tag === 'video') {
                el.setAttribute('controls', '');
                el.setAttribute('preload', 'none');
            }
        }
        if (tag === 'td' || tag === 'th') {
            for (const attr of ['colspan','rowspan']) {
                const value = node.getAttribute(attr) ?? '';
                if (/^[1-8]$/.test(value)) el.setAttribute(attr, value);
            }
        }
        if (tag === 'img' || tag === 'br' || tag === 'hr' || tag === 'source') return el;
        for (const child of Array.from(node.childNodes)) {
            const next = copy(child);
            if (next) el.append(next);
        }
        return el;
    };
    for (const node of Array.from(doc.body.childNodes)) {
        const next = copy(node);
        if (next) out.append(next);
    }
    return out.innerHTML;
}

function escapeMarkup(value: string): string {
    const span = document.createElement('span');
    span.textContent = value;
    return span.innerHTML;
}

function initialMarkup(value: string): string {
    return value.split(/\r?\n\r?\n/).map(part =>
        '<p>' + escapeMarkup(part).replace(/\r?\n/g, '<br>') + '</p>'
    ).join('');
}

function initializeEditor(root: HTMLElement): void {
    const form = root.closest('form');
    const visual = root.querySelector<HTMLElement>('[data-editor-visual]');
    const source = root.querySelector<HTMLTextAreaElement>('[data-editor-source]');
    const plain = root.querySelector<HTMLTextAreaElement>('[data-editor-plain]');
    const html = root.querySelector<HTMLTextAreaElement>('[data-editor-html]');
    const enhanced = root.querySelector<HTMLElement>('[data-editor-enhanced]');
    const notice = root.querySelector<HTMLElement>('[data-editor-notice]');
    const count = root.querySelector<HTMLElement>('[data-editor-count]');
    if (!form || !visual || !source || !plain || !html || !enhanced) return;

    const locked = Boolean(root.closest('fieldset')?.disabled);
    let mode: 'text' | 'html' = 'text';
    let selection: Range | null = null;
    visual.innerHTML = safeMarkup(html.value || initialMarkup(plain.value));
    enhanced.hidden = false;
    plain.hidden = true;
    plain.required = false;
    visual.contentEditable = locked ? 'false' : 'true';

    const inform = (message: string): void => { if (notice) notice.textContent = message; };
    const getText = (): string => (visual.innerText || visual.textContent || '').trim();

    const updateSidebar = (words: number): void => {
        const summary = form.querySelector<HTMLElement>('[data-editor-words-summary]');
        if (summary) summary.textContent = String(words) + ' words in this document';
        const title = form.querySelector<HTMLInputElement>('[data-document-title]');
        const seoTitle = form.querySelector<HTMLInputElement>('[data-seo-title]');
        const seoDescription = form.querySelector<HTMLTextAreaElement>('[data-seo-description]');
        const docSummary = form.querySelector<HTMLTextAreaElement>('[data-editor-summary]');
        const titlePreview = form.querySelector<HTMLElement>('[data-seo-preview-title]');
        const descriptionPreview = form.querySelector<HTMLElement>('[data-seo-preview-description]');
        if (titlePreview) titlePreview.textContent = seoTitle?.value.trim() || title?.value.trim() || 'Document title';
        if (descriptionPreview) descriptionPreview.textContent =
            seoDescription?.value.trim() || docSummary?.value.trim() || 'Add a summary for your search preview.';
    };

    const sync = (): void => {
        const cleaned = safeMarkup(mode === 'html' ? source.value : visual.innerHTML);
        html.value = cleaned;
        if (mode === 'html') {
            // Keep raw input out of the live DOM until it has passed client sanitization.
            visual.innerHTML = cleaned;
        }
        plain.value = getText();
        const words = plain.value ? plain.value.split(/\s+/u).length : 0;
        if (count) count.textContent = String(words) + ' words · ' + String(plain.value.length) + ' characters';
        updateSidebar(words);
    };

    const setMode = (next: 'text' | 'html'): void => {
        if (mode === next) return;
        sync();
        if (next === 'html') {
            source.value = html.value;
            visual.hidden = true;
            source.hidden = false;
            source.focus();
        } else {
            visual.innerHTML = safeMarkup(source.value);
            source.hidden = true;
            visual.hidden = false;
            if (!locked) visual.focus();
        }
        mode = next;
        root.querySelectorAll<HTMLButtonElement>('[data-editor-mode]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.editorMode === next));
        });
        inform(next === 'html' ? 'Editing HTML source. Unsafe tags and attributes are removed on save.' : 'Visual text editing.');
    };

    const remember = (): void => {
        const current = window.getSelection();
        if (current?.rangeCount && visual.contains(current.anchorNode)) selection = current.getRangeAt(0).cloneRange();
    };
    const focusVisual = (): void => {
        if (mode === 'html') setMode('text');
        visual.focus();
        if (selection && visual.contains(selection.startContainer)) {
            const current = window.getSelection();
            current?.removeAllRanges();
            current?.addRange(selection);
        }
    };
    const command = (name: string, value?: string): void => {
        if (locked) return;
        focusVisual();
        document.execCommand(name, false, value);
        remember();
        sync();
    };

    visual.addEventListener('keyup', remember);
    visual.addEventListener('mouseup', remember);
    visual.addEventListener('input', sync);
    visual.addEventListener('paste', (event) => {
        if (!event.clipboardData || locked) return;
        event.preventDefault();
        const rich = event.clipboardData.getData('text/html');
        const text = event.clipboardData.getData('text/plain');
        focusVisual();
        if (rich) document.execCommand('insertHTML', false, safeMarkup(rich));
        else document.execCommand('insertText', false, text);
        sync();
    });
    source.addEventListener('input', sync);
    root.querySelectorAll<HTMLButtonElement>('[data-editor-mode]').forEach(button => {
        button.addEventListener('click', () => setMode(button.dataset.editorMode === 'html' ? 'html' : 'text'));
    });
    root.querySelectorAll<HTMLButtonElement>('[data-editor-command]').forEach(button => {
        button.addEventListener('mousedown', remember);
        button.addEventListener('click', () => command(button.dataset.editorCommand ?? ''));
    });
    root.querySelectorAll<HTMLInputElement>('[data-editor-color]').forEach(picker => {
        picker.addEventListener('input', () => {
            if (!/^#[0-9a-f]{6}$/i.test(picker.value)) return;
            command(picker.dataset.editorColor === 'hiliteColor' ? 'hiliteColor' : 'foreColor', picker.value);
        });
    });
    root.querySelector<HTMLSelectElement>('[data-editor-size]')?.addEventListener('change', event => {
        const target = event.currentTarget;
        if (!(target instanceof HTMLSelectElement)) return;
        command('fontSize', target.value);
    });
    root.querySelector<HTMLSelectElement>('[data-editor-font]')?.addEventListener('change', event => {
        const target = event.currentTarget;
        if (!(target instanceof HTMLSelectElement)) return;
        command('fontName', target.value);
    });
    const block = root.querySelector<HTMLSelectElement>('[data-editor-block]');
    block?.addEventListener('change', () => { command('formatBlock', block.value); block.value = 'p'; });
    root.querySelector<HTMLButtonElement>('[data-editor-link]')?.addEventListener('click', () => {
        if (locked) return;
        const value = window.prompt('Link URL (https://, mailto: or same-site /path)', 'https://');
        if (value === null) return;
        if (!/^(https?:\/\/|mailto:|tel:|\/(?!\/))/i.test(value)) {
            inform('Please enter a safe URL beginning with https:// or /');
            return;
        }
        command('createLink', value);
    });
    root.querySelector<HTMLButtonElement>('[data-editor-table]')?.addEventListener('click', () => {
        if (locked) return;
        command('insertHTML', '<table><tbody><tr><td>Cell 1</td><td>Cell 2</td></tr><tr><td>Cell 3</td><td>Cell 4</td></tr></tbody></table><p></p>');
    });

    const upload = root.querySelector<HTMLInputElement>('[data-editor-upload]');
    upload?.addEventListener('change', async () => {
        const file = upload.files?.[0];
        if (!file || locked) return;
        if (file.size > 12 * 1024 * 1024) {
            inform('File exceeds the 12 MB upload limit.'); upload.value = ''; return;
        }
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        if (!csrf) { inform('Missing secure upload token. Refresh and try again.'); return; }
        const body = new FormData();
        body.append('file', file);
        body.append('context', root.dataset.editorContext === 'ticket' ? 'ticket' : 'editorial');
        inform('Uploading ' + file.name + '…');
        try {
            const response = await fetch(root.dataset.editorUploadUrl ?? '/editor-media', {
                method: 'POST', body, credentials: 'same-origin', headers: {
                    'X-CSRF-TOKEN': csrf, 'Accept': 'application/json',
                },
            });
            if (!response.ok) { inform('Upload failed (' + String(response.status) + '). Check file size and format.'); return; }
            const result: unknown = await response.json();
            if (!result || typeof result !== 'object' || !('url' in result) ||
                typeof result.url !== 'string' || !mediaPattern.test(result.url)) {
                inform('Upload response was invalid.'); return;
            }
            const href = result.url;
            const name = escapeMarkup(file.name.slice(0, 120));
            let markup = '';
            if (file.type.startsWith('image/')) markup = '<figure><img src="' + href + '" alt="' + name + '"><figcaption>' + name + '</figcaption></figure><p></p>';
            else if (file.type.startsWith('audio/')) markup = '<figure><audio src="' + href + '" controls preload="none"></audio><figcaption>' + name + '</figcaption></figure><p></p>';
            else markup = '<figure><video src="' + href + '" controls preload="none"></video><figcaption>' + name + '</figcaption></figure><p></p>';
            command('insertHTML', markup);
            inform('Media uploaded privately. Save your document to attach it.');
        } catch {
            inform('Media could not be uploaded. Check your connection.');
        } finally { upload.value = ''; }
    });

    for (const field of form.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('[data-document-title],[data-seo-title],[data-seo-description],[data-editor-summary]')) {
        field.addEventListener('input', () => updateSidebar(plain.value ? plain.value.split(/\s+/u).length : 0));
    }
    form.addEventListener('submit', event => {
        sync();
        if (!plain.value.trim()) {
            event.preventDefault();
            inform('Add some text or a caption before saving.');
            if (mode === 'html') source.focus(); else visual.focus();
        }
        if (plain.value.length > Number(plain.maxLength || '25000')) {
            event.preventDefault();
            inform('Content is longer than the allowed character limit.');
        }
    });
    if (locked) root.querySelectorAll<HTMLInputElement | HTMLButtonElement | HTMLSelectElement>('[data-editor-enhanced] button,[data-editor-enhanced] input,[data-editor-enhanced] select]').forEach(control => { control.disabled = true; });
    sync();
}
document.querySelectorAll<HTMLElement>('[data-rich-editor]').forEach(initializeEditor);
