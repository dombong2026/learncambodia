// Compact, dependency-free rich editor. Only the server-side allowlist decides
// what is persisted/rendered; the client also sanitizes HTML before DOM insertion.
const allowed = new Set([
    'p','div','br','strong','b','em','i','u','s','strike','h1','h2','h3','h4','h5','h6',
    'blockquote','pre','code','ul','ol','li','hr','a','img','figure','figcaption',
    'table','thead','tbody','tr','td','th','span','sup','sub','video','audio','source',
]);
const discard = new Set(['script','style','iframe','object','embed','svg','math','form','input','button','link','meta','base']);
const mediaPattern = /^\/editor-media\/[1-9]\d*$/;
const linkPattern = /^(?:https?:\/\/|mailto:|tel:|\/(?![\/\\]))[^\x00-\x1f\x7f]*$/i;

function safeCss(input: string, tag: string, node: Element): string {
    const properties: Record<string, string> = {};
    const items = input.toLowerCase().split(';');
    const align = node.getAttribute('align');
    if (align) items.unshift('text-align:' + align.toLowerCase());
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
        if (key === 'margin-left' && /^(40|80|120|160)px$/.test(value)) {
            properties['margin-inline-start'] = value;
        } else if (key === 'color' || key === 'background-color') {
            const rgb = /^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/.exec(value);
            if (rgb && rgb.slice(1).every(v => Number(v) <= 255)) {
                value = '#' + rgb.slice(1).map(v => Number(v).toString(16).padStart(2, '0')).join('');
            }
            if (/^#[a-f0-9]{3}([a-f0-9]{3})?$/i.test(value)) properties[key] = value;
        } else if (key === 'text-align' && ['left','center','right','justify'].includes(value)) {
            properties[key] = value;
        } else if (key === 'font-size' && /^(12|14|16|18|20|24|28|32|36)px$/.test(value)) {
            properties[key] = value;
        } else if (key === 'margin-inline-start' && /^(40|80|120|160)px$/.test(value)) {
            properties[key] = value;
        } else if (key === 'font-family' && ['arial','georgia','monospace'].includes(value)) {
            properties[key] = value;
        }
    }
    return Object.entries(properties).map(([key,value]) => key + ':' + value).join(';');
}

function safeMarkup(markup: string): string {
    const parsed = document.createElement('template');
    parsed.innerHTML = markup;
    const out = document.createElement('div');

    const copy = (node: Node): Node | null => {
        if (node.nodeType === Node.TEXT_NODE) return document.createTextNode(node.textContent ?? '');
        if (node.nodeType !== Node.ELEMENT_NODE) return null;
        const element = node as Element;
        const tag = element.tagName.toLowerCase();
        if (discard.has(tag)) return null;
        const el = document.createElement(allowed.has(tag) ? tag : 'span');
        const style = safeCss(element.getAttribute('style') ?? '', tag, element);
        if (style) el.setAttribute('style', style);
        if (tag === 'a') {
            const href = element.getAttribute('href') ?? '';
            if (linkPattern.test(href)) {
                el.setAttribute('href', href);
                el.setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
        if (['img','audio','video','source'].includes(tag)) {
            const src = element.getAttribute('src') ?? '';
            if (src && !mediaPattern.test(src) && ['img','source'].includes(tag)) return null;
            if (mediaPattern.test(src)) el.setAttribute('src', src);
            if (tag === 'img') {
                el.setAttribute('alt', (element.getAttribute('alt') ?? '').slice(0, 200));
                el.setAttribute('loading', 'lazy');
            }
            if (tag === 'audio' || tag === 'video') {
                el.setAttribute('controls', '');
                el.setAttribute('preload', 'none');
            }
        }
        if (tag === 'td' || tag === 'th') {
            for (const attr of ['colspan','rowspan']) {
                const value = element.getAttribute(attr) ?? '';
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
    for (const node of Array.from(parsed.content.childNodes)) {
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
    const course = root.dataset.editorContext === 'course';
    const required = plain.required;
    const startingText = plain.value;
    const startingHtml = html.value;
    // Keep the legacy fallback untouched until the description itself changes.
    const initial = safeMarkup(startingHtml || initialMarkup(startingText));
    const formatting = root.querySelector<HTMLElement>('[data-editor-formatting]');
    let mode: 'text' | 'html' = 'text';
    let selection: Range | null = null;
    visual.innerHTML = initial;
    html.disabled = false;
    enhanced.hidden = false;
    plain.hidden = true;
    plain.required = false;
    visual.contentEditable = locked ? 'false' : 'true';

    const inform = (message: string): void => { if (notice) notice.textContent = message; };
    const getText = (markup: string): string => {
        const content = document.createElement('template');
        content.innerHTML = markup.replace(/<(?:br|hr)\b[^>]*>|<\/(?:p|div|h[1-6]|blockquote|pre|li|tr|figure|figcaption)>/gi, '\n')
            .replace(/<\/(?:td|th)>/gi, ' ');
        return (content.content.textContent ?? '').trim();
    };

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
        const text = getText(cleaned);
        const meaningful = text || /<(?:img|audio|video|hr)\b/.test(cleaned);
        const unchanged = course && cleaned === initial;
        html.value = unchanged ? startingHtml : (course && !meaningful ? '' : cleaned);
        if (mode === 'html') {
            // Keep raw input out of the live DOM until it has passed client sanitization.
            if (visual.innerHTML !== cleaned) visual.innerHTML = cleaned;
        }
        plain.value = unchanged ? startingText : text;
        const words = plain.value ? plain.value.split(/\s+/u).length : 0;
        if (count) count.textContent = String(words) + ' words · ' + String(plain.value.length) + ' characters';
        updateSidebar(words);
    };

    const setMode = (next: 'text' | 'html'): void => {
        if (mode === next) return;
        sync();
        if (next === 'html') {
            source.value = safeMarkup(visual.innerHTML);
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
        selectedMedia = null;
        if (course && formatting) formatting.hidden = next === 'html';
        selection = null;
        updateSelectionTools();
        root.querySelectorAll<HTMLButtonElement>('[data-editor-mode]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.editorMode === next));
        });
        inform(next === 'html' ? 'Editing HTML source. Unsafe tags and attributes are removed on save.' : 'Visual text editing.');
    };

    const caption = root.querySelector<HTMLButtonElement>('[data-editor-media-caption]');
    const alt = root.querySelector<HTMLButtonElement>('[data-editor-media-alt]');
    const removeMedia = root.querySelector<HTMLButtonElement>('[data-editor-media-remove]');
    const tableTools = root.querySelector<HTMLElement>('[data-editor-table-tools]');
    let selectedMedia: HTMLElement | null = null;
    const selectedElement = (selector: string): Element | null => {
        const node = selection?.startContainer;
        return (node instanceof Element ? node : node?.parentElement)?.closest(selector) ?? null;
    };
    const updateSelectionTools = (): void => {
        if (caption) caption.hidden = !selectedMedia?.matches('figure');
        if (alt) alt.hidden = !(selectedMedia?.matches('img') || selectedMedia?.querySelector('img'));
        if (removeMedia) removeMedia.hidden = !selectedMedia;
        if (tableTools) tableTools.hidden = !selectedElement('td, th');
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
        if (course) plain.dispatchEvent(new Event('input', { bubbles: true }));
    };

    visual.addEventListener('keyup', () => {
        selectedMedia = null;
        remember();
        updateSelectionTools();
    });
    visual.addEventListener('mouseup', event => {
        const target = event.target as Element;
        selectedMedia = target.closest<HTMLElement>('figure, img, audio, video');
        remember();
        updateSelectionTools();
    });
    visual.addEventListener('input', () => {
        remember();
        if (selectedMedia && !visual.contains(selectedMedia)) selectedMedia = null;
        updateSelectionTools();
        sync();
    });
    const selectElement = (element: Element): void => {
        selection = document.createRange();
        selection.selectNode(element);
    };
    const removeElement = (element: Element | null): void => {
        if (!element || !visual.contains(element)) return;
        selectElement(element);
        selectedMedia = null;
        command('delete');
        updateSelectionTools();
    };
    caption?.addEventListener('click', () => {
        if (!selectedMedia || !visual.contains(selectedMedia)) return;
        const label = window.prompt('Media caption', selectedMedia.querySelector('figcaption')?.textContent ?? '');
        if (label === null) return;
        const replacement = selectedMedia.cloneNode(true) as HTMLElement;
        replacement.querySelector('figcaption')?.remove();
        if (label.trim()) {
            const newCaption = document.createElement('figcaption');
            newCaption.textContent = label.trim();
            replacement.append(newCaption);
        }
        selectElement(selectedMedia);
        selectedMedia = null;
        command('insertHTML', replacement.outerHTML);
        updateSelectionTools();
    });
    alt?.addEventListener('click', () => {
        if (!selectedMedia || !visual.contains(selectedMedia)) return;
        const replacement = selectedMedia.cloneNode(true) as HTMLElement;
        const image = replacement.matches('img') ? replacement : replacement.querySelector('img');
        if (!image) return;
        const description = window.prompt('Image description', image.getAttribute('alt') ?? '');
        if (description === null) return;
        image.setAttribute('alt', description.trim().slice(0, 200));
        selectElement(selectedMedia);
        selectedMedia = null;
        command('insertHTML', replacement.outerHTML);
        updateSelectionTools();
    });
    removeMedia?.addEventListener('click', () => removeElement(selectedMedia));
    tableTools?.addEventListener('click', event => {
        const action = (event.target as Element).closest<HTMLButtonElement>('[data-table-action]')?.dataset.tableAction;
        const cell = selectedElement('td, th') as HTMLTableCellElement | null;
        const row = cell?.parentElement as HTMLTableRowElement | null;
        const table = row?.closest('table');
        if (!action || !table || !row || !cell) return;
        if (action === 'remove-table') {
            removeElement(table);
            return;
        }
        const replacement = table.cloneNode(true) as HTMLTableElement;
        const currentRow = replacement.rows[row.rowIndex];
        const column = cell.cellIndex;
        if (Array.from(replacement.rows).some(item =>
            Array.from(item.cells).some(entry => entry.colSpan !== 1 || entry.rowSpan !== 1)
        )) {
            inform('Use HTML mode to change tables with merged cells.');
            return;
        }
        if ((action === 'remove-row' && replacement.rows.length === 1)
            || (action === 'remove-column' && currentRow.cells.length === 1)) {
            removeElement(table);
            return;
        }
        if (action === 'add-row') {
            const newRow = (currentRow.parentElement as HTMLTableSectionElement).insertRow(currentRow.sectionRowIndex + 1);
            for (const entry of Array.from(currentRow.cells)) {
                const newCell = document.createElement(entry.tagName);
                newCell.textContent = 'Cell';
                newRow.append(newCell);
            }
        } else if (action === 'remove-row') {
            currentRow.remove();
        } else {
            for (const item of Array.from(replacement.rows)) {
                const entry = item.cells[column];
                if (!entry) continue;
                if (action === 'add-column') {
                    const newCell = document.createElement(entry.tagName);
                    newCell.textContent = 'Cell';
                    entry.after(newCell);
                } else {
                    entry.remove();
                }
            }
        }
        selectElement(table);
        command('insertHTML', replacement.outerHTML);
        updateSelectionTools();
    });
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
    if (course) visual.addEventListener('drop', event => event.preventDefault());
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
        const existingLink = selectedElement('a');
        const value = window.prompt('Link URL (https://, mailto: or same-site /path)', existingLink?.getAttribute('href') ?? 'https://')?.trim();
        if (value === undefined) return;
        if (!linkPattern.test(value)) {
            inform('Please enter a safe URL beginning with https:// or /');
            return;
        }
        if (existingLink) {
            const replacement = existingLink.cloneNode(true) as HTMLAnchorElement;
            replacement.setAttribute('href', value);
            selectElement(existingLink);
            command('insertHTML', replacement.outerHTML);
        } else if (selection?.toString().trim()) {
            command('createLink', value);
        } else {
            const label = window.prompt('Link text', value);
            if (!label) return;
            const link = document.createElement('a');
            link.setAttribute('href', value);
            link.textContent = label;
            command('insertHTML', link.outerHTML);
        }
    });
    root.querySelector<HTMLButtonElement>('[data-editor-table]')?.addEventListener('click', () => {
        if (locked) return;
        const rowInput = window.prompt('Number of table rows (1–8)', '2');
        if (rowInput === null) return;
        const colInput = window.prompt('Number of table columns (1–8)', '2');
        if (colInput === null) return;
        const rows = Number(rowInput), columns = Number(colInput);
        if (!Number.isInteger(rows) || !Number.isInteger(columns) || rows < 1 || columns < 1 || rows > 8 || columns > 8) {
            inform('Table must contain 1–8 rows and 1–8 columns.');
            return;
        }
        let table = '<table><tbody>';
        for (let row = 0; row < rows; row++) {
            table += '<tr>';
            for (let col = 0; col < columns; col++) table += '<td>Cell</td>';
            table += '</tr>';
        }
        command('insertHTML', table + '</tbody></table><p></p>');
    });

    const previewDialog = root.querySelector<HTMLDialogElement>('[data-editor-preview-dialog]');
    const previewContent = root.querySelector<HTMLElement>('[data-editor-preview-content]');
    root.querySelector<HTMLButtonElement>('[data-editor-preview]')?.addEventListener('click', () => {
        sync();
        if (!previewDialog || !previewContent) return;
        previewContent.innerHTML = safeMarkup(html.value);
        previewDialog.showModal();
    });
    root.querySelector<HTMLButtonElement>('[data-editor-preview-close]')?.addEventListener('click', () => {
        previewDialog?.close();
    });
    const expand = root.querySelector<HTMLButtonElement>('[data-editor-expand]');
    expand?.addEventListener('click', () => {
        const expanded = root.classList.toggle('rich-editor-expanded');
        expand.setAttribute('aria-pressed', String(expanded));
        expand.textContent = expanded ? 'Collapse' : 'Expand';
        inform(expanded ? 'Expanded editing view. Press Escape to close.' : 'Compact editing view.');
        if (mode === 'html') source.focus(); else visual.focus();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('rich-editor-expanded')) {
            root.classList.remove('rich-editor-expanded');
            if (expand) { expand.textContent = 'Expand'; expand.setAttribute('aria-pressed', 'false'); expand.focus(); }
        }
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
        body.append('context', root.dataset.editorContext ?? 'editorial');
        const insertion = selection?.cloneRange();
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
            if (insertion && visual.contains(insertion.startContainer)) selection = insertion;
            command('insertHTML', markup);
            inform('Media uploaded privately. Save your document to attach it.');
        } catch {
            inform('Media could not be uploaded. Check your connection.');
        } finally { upload.value = ''; }
    });

    for (const field of Array.from(form.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('[data-document-title],[data-seo-title],[data-seo-description],[data-editor-summary]'))) {
        field.addEventListener('input', () => updateSidebar(plain.value ? plain.value.split(/\s+/u).length : 0));
    }
    form.addEventListener('submit', event => {
        sync();
        if (required && !plain.value.trim()) {
            event.preventDefault();
            inform('Add some text or a caption before saving.');
            if (mode === 'html') source.focus(); else visual.focus();
        }
        if (plain.value.length > plain.maxLength || (course && html.value.length > 120000)) {
            event.preventDefault();
            inform('Content is longer than the allowed character limit.');
            if (mode === 'html') source.focus(); else visual.focus();
        }
    });
    if (locked) root.querySelectorAll<HTMLInputElement | HTMLButtonElement | HTMLSelectElement>('[data-editor-enhanced] button,[data-editor-enhanced] input,[data-editor-enhanced] select]').forEach(control => { control.disabled = true; });
    sync();
}
document.querySelectorAll<HTMLElement>('[data-rich-editor]').forEach(initializeEditor);
