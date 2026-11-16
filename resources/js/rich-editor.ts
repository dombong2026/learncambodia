// Compact, dependency-free rich editor. Only the server-side allowlist decides
// what is persisted/rendered; the client also sanitizes HTML before DOM insertion.
import { enhanceLessonMedia, removeLessonVideoFileCaptions } from './lesson-media-player';

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

function safeMarkup(markup: string, lesson = false): string {
    const parsed = document.createElement('template');
    parsed.innerHTML = markup;
    if (lesson) removeLessonVideoFileCaptions(parsed.content);
    const out = document.createElement('div');

    const copy = (node: Node): Node | null => {
        if (node.nodeType === Node.TEXT_NODE) return document.createTextNode(node.textContent ?? '');
        if (node.nodeType !== Node.ELEMENT_NODE) return null;
        const element = node as Element;
        const tag = element.tagName.toLowerCase();
        if (discard.has(tag)) return null;
        if (tag === 'lesson-media-player') {
            const fragment = document.createDocumentFragment();
            // The player UI lives in shadow DOM. Serialize only the actual media.
            for (const media of Array.from(element.children)) {
                if (!media.matches('audio, video')) continue;
                const next = copy(media);
                if (next) fragment.append(next);
            }
            return fragment;
        }
        const el = document.createElement(allowed.has(tag) ? tag : 'span');
        const style = safeCss(element.getAttribute('style') ?? '', tag, element);
        if (style) el.setAttribute('style', style);
        const youtube = element.getAttribute('data-youtube') ?? '';
        if (lesson && tag === 'figure' && /^[A-Za-z0-9_-]{11}$/.test(youtube)) el.dataset.youtube = youtube;
        const language = element.getAttribute('data-language') ?? '';
        if (lesson && tag === 'code' && /^[a-z][a-z0-9+#-]{0,23}$/.test(language)) el.dataset.language = language;
        if (tag === 'a') {
            const href = element.getAttribute('href') ?? '';
            if (linkPattern.test(href)) {
                el.setAttribute('href', href);
                const kind = element.getAttribute('data-resource');
                if (lesson && (kind === 'button' || (kind === 'file' && mediaPattern.test(href)))) el.dataset.resource = kind;
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
    const lesson = root.dataset.editorContext === 'lesson';
    const course = lesson || root.dataset.editorContext === 'course';
    const clean = (markup: string): string => safeMarkup(markup, lesson);
    const htmlLimit = lesson ? 200000 : 120000;
    let uploading = false;
    const required = plain.required;
    const startingText = plain.value;
    const startingHtml = html.value;
    // Keep the legacy fallback untouched until the description itself changes.
    const initial = clean(startingHtml || initialMarkup(startingText));
    const formatting = root.querySelector<HTMLElement>('[data-editor-formatting]');
    let mode: 'text' | 'html' = 'text';
    let selection: Range | null = null;
    visual.innerHTML = initial;
    if (lesson) enhanceLessonMedia(visual);
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
        const cleaned = clean(mode === 'html' ? source.value : visual.innerHTML);
        const text = getText(cleaned);
        const meaningful = text || /<(?:img|audio|video|hr)\b| data-youtube=/.test(cleaned);
        const unchanged = course && cleaned === initial;
        html.value = unchanged ? startingHtml : (course && !meaningful ? '' : cleaned);
        if (!lesson && mode === 'html' && visual.innerHTML !== cleaned) visual.innerHTML = cleaned;
        plain.value = unchanged ? startingText : text;
        const words = plain.value ? plain.value.split(/\s+/u).length : 0;
        if (count) count.textContent = String(words) + ' words · ' + String(plain.value.length) + ' characters';
        updateSidebar(words);
    };

    const setMode = (next: 'text' | 'html'): void => {
        if (mode === next) return;
        sync();
        if (next === 'html') {
            source.value = clean(visual.innerHTML);
            if (lesson) visual.querySelectorAll<HTMLMediaElement>('video, audio').forEach(media => media.pause());
            visual.hidden = true;
            source.hidden = false;
            source.focus();
        } else {
            visual.innerHTML = clean(source.value);
            if (lesson) enhanceLessonMedia(visual);
            source.hidden = true;
            visual.hidden = false;
            if (!locked) visual.focus();
        }
        mode = next;
        selectedMedia = null;
        clickedBlock = null;
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
    const editBlock = root.querySelector<HTMLButtonElement>('[data-lesson-edit-block]');
    const removeBlock = root.querySelector<HTMLButtonElement>('[data-lesson-remove-block]');
    let clickedBlock: Element | null = null;
    const resourceSelector = '[data-youtube], pre, a[data-resource]';
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
        const block = clickedBlock ?? selectedElement(resourceSelector);
        if (editBlock) editBlock.hidden = !block;
        if (removeBlock) removeBlock.hidden = !block;
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
        clickedBlock = null;
        remember();
        updateSelectionTools();
    });
    visual.addEventListener(lesson ? 'click' : 'mouseup', event => {
        const target = event.target as Element;
        clickedBlock = target.closest(resourceSelector);
        selectedMedia = target.closest<HTMLElement>('figure:not([data-youtube]), img, audio, video');
        remember();
        updateSelectionTools();
    });
    visual.addEventListener('input', () => {
        if (lesson) enhanceLessonMedia(visual);
        remember();
        if (selectedMedia && !visual.contains(selectedMedia)) selectedMedia = null;
        if (clickedBlock && !visual.contains(clickedBlock)) clickedBlock = null;
        updateSelectionTools();
        sync();
    });
    const selectElement = (element: Element): void => {
        selection = document.createRange();
        selection.selectNode(element);
    };
    const insertLessonElement = (node: HTMLElement, replacing: Element | null = null): void => {
        if (replacing && visual.contains(replacing)) {
            replacing.replaceWith(node);
        } else {
            focusVisual();
            const range = window.getSelection()?.rangeCount ? window.getSelection()?.getRangeAt(0) : null;
            if (range && visual.contains(range.startContainer) && node.matches('a')) {
                range.deleteContents();
                range.insertNode(node);
            } else {
                const anchor = range?.startContainer;
                let block = anchor instanceof Element ? anchor : anchor?.parentElement;
                while (block && block !== visual && block.parentElement !== visual) block = block.parentElement;
                if (block && block !== visual) block.after(node);
                else visual.append(node);
            }
        }
        if (node.matches('figure, pre') && !node.nextSibling) {
            const line = document.createElement('p');
            line.append(document.createElement('br'));
            node.after(line);
        }
        focusVisual();
        const cursor = document.createRange();
        cursor.setStartAfter(node);
        cursor.collapse(true);
        const active = window.getSelection();
        active?.removeAllRanges();
        active?.addRange(cursor);
        selection = cursor;
        visual.dispatchEvent(new Event('input', { bubbles: true }));
    };
    const removeElement = (element: Element | null): void => {
        if (!element || !visual.contains(element)) return;
        selectedMedia = null;
        clickedBlock = null;
        if (lesson) {
            element.remove();
            selection = null;
            visual.focus();
            visual.dispatchEvent(new Event('input', { bubbles: true }));
        } else {
            selectElement(element);
            command('delete');
        }
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
        const original = selectedMedia;
        selectedMedia = null;
        if (lesson) insertLessonElement(replacement, original);
        else {
            selectElement(original);
            command('insertHTML', replacement.outerHTML);
        }
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
        const original = selectedMedia;
        selectedMedia = null;
        if (lesson) insertLessonElement(replacement, original);
        else {
            selectElement(original);
            command('insertHTML', replacement.outerHTML);
        }
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
        if (rich) document.execCommand('insertHTML', false, clean(rich));
        else document.execCommand('insertText', false, text);
        sync();
    });
    if (course) visual.addEventListener('drop', event => event.preventDefault());
    if (lesson) visual.addEventListener('click', event => {
        if ((event.target as Element).closest('a')) event.preventDefault();
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

    if (lesson) {
        const dialog = root.querySelector<HTMLDialogElement>('[data-lesson-insert-dialog]')!;
        const heading = dialog.querySelector<HTMLElement>('[data-lesson-insert-title]')!;
        const label = dialog.querySelector<HTMLInputElement>('[data-lesson-label-field] input')!;
        const url = dialog.querySelector<HTMLInputElement>('[data-lesson-url-field] input')!;
        const language = dialog.querySelector<HTMLSelectElement>('[data-lesson-language-field] select')!;
        const code = dialog.querySelector<HTMLTextAreaElement>('[data-lesson-code-field] textarea')!;
        const error = dialog.querySelector<HTMLElement>('[data-lesson-dialog-notice]')!;
        const apply = dialog.querySelector<HTMLButtonElement>('[data-lesson-insert-apply]')!;
        type Kind = 'youtube' | 'code' | 'button' | 'file';
        let kind: Kind = 'youtube';
        let editing: Element | null = null;

        const open = (next: Kind, element: Element | null = null) => {
            kind = next;
            editing = element;
            const title = { youtube: 'YouTube video', code: 'code block', button: 'resource button', file: 'attachment' }[kind];
            heading.textContent = (element ? 'Edit ' : 'Insert ') + title;
            apply.textContent = element ? 'Apply changes' : 'Insert';
            label.value = (kind === 'youtube' ? element?.querySelector('figcaption')?.textContent : element?.textContent) ?? '';
            url.value = (kind === 'youtube' ? element?.getAttribute('data-youtube') : element?.getAttribute('href')) ?? '';
            const snippet = element?.querySelector('code');
            language.value = snippet?.dataset.language ?? 'plaintext';
            if (!language.value) language.value = 'plaintext';
            code.value = snippet?.textContent ?? element?.textContent ?? '';
            label.parentElement!.hidden = kind === 'code';
            url.parentElement!.hidden = kind === 'code' || kind === 'file';
            language.parentElement!.hidden = kind !== 'code';
            code.parentElement!.hidden = kind !== 'code';
            error.hidden = true;
            dialog.showModal();
            if (kind === 'youtube') url.focus();
            else if (kind === 'code') code.focus();
            else label.focus();
        };

        root.querySelectorAll<HTMLButtonElement>('[data-lesson-insert]').forEach(button => {
            button.addEventListener('click', () => open(button.dataset.lessonInsert as Kind));
        });
        editBlock?.addEventListener('click', () => {
            const block = clickedBlock ?? selectedElement(resourceSelector);
            if (!block) return;
            const type = block.matches('pre') ? 'code' : block.matches('[data-youtube]') ? 'youtube' : block.getAttribute('data-resource');
            if (type === 'code' || type === 'youtube' || type === 'file' || type === 'button') open(type, block);
        });
        removeBlock?.addEventListener('click', () => removeElement(clickedBlock ?? selectedElement(resourceSelector)));
        dialog.querySelector('[data-lesson-insert-cancel]')?.addEventListener('click', () => dialog.close());
        apply.addEventListener('click', () => {
            let node: HTMLElement;
            const title = label.value.trim();
            let message = '';
            if (kind === 'youtube') {
                const id = youtubeId(url.value.trim());
                if (!id) message = 'Enter a YouTube video URL or 11-character ID.';
                node = document.createElement('figure');
                node.dataset.youtube = id ?? '';
                const caption = document.createElement('figcaption');
                caption.textContent = title || 'YouTube video';
                node.append(caption);
            } else if (kind === 'code') {
                if (!code.value.trim()) message = 'Enter the code to display.';
                node = document.createElement('pre');
                const snippet = document.createElement('code');
                snippet.dataset.language = language.value;
                snippet.textContent = code.value;
                node.append(snippet);
            } else {
                const href = url.value.trim();
                if (!title || !linkPattern.test(href)) message = 'Enter a label and a valid resource URL.';
                const link = document.createElement('a');
                link.dataset.resource = kind;
                link.setAttribute('href', href);
                link.textContent = title;
                node = link;
            }
            if (message) {
                error.textContent = message;
                error.hidden = false;
                return;
            }
            dialog.close();
            clickedBlock = null;
            insertLessonElement(node, editing);
            updateSelectionTools();
        });
    }

    const previewDialog = root.querySelector<HTMLDialogElement>('[data-editor-preview-dialog]');
    const previewContent = root.querySelector<HTMLElement>('[data-editor-preview-content]');
    root.querySelector<HTMLButtonElement>('[data-editor-preview]')?.addEventListener('click', () => {
        sync();
        if (!previewDialog || !previewContent) return;
        previewContent.innerHTML = clean(html.value);
        if (lesson) {
            visual.querySelectorAll<HTMLMediaElement>('video, audio').forEach(media => media.pause());
            enhanceLessonMedia(previewContent);
            previewContent.querySelectorAll<HTMLElement>('figure[data-youtube]').forEach(figure => {
                const frame = document.createElement('iframe');
                frame.src = 'https://www.youtube-nocookie.com/embed/' + figure.dataset.youtube;
                frame.title = 'YouTube lesson video';
                frame.allowFullscreen = true;
                frame.allow = 'encrypted-media; picture-in-picture; fullscreen';
                frame.referrerPolicy = 'strict-origin-when-cross-origin';
                figure.prepend(frame);
            });
        }
        previewDialog.showModal();
    });
    if (lesson) previewDialog?.addEventListener('close', () => previewContent?.replaceChildren());
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
        if (!file || locked || uploading) return;
        const limit = lesson ? 50 : 12;
        if (file.size > limit * 1024 * 1024) {
            inform('File exceeds the ' + limit + ' MB upload limit.'); upload.value = ''; return;
        }
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        if (!csrf) { inform('Missing secure upload token. Refresh and try again.'); return; }
        const body = new FormData();
        body.append('file', file);
        body.append('context', root.dataset.editorContext ?? 'editorial');
        const insertion = selection?.cloneRange();
        uploading = true;
        upload.disabled = true;
        inform('Uploading ' + file.name + '…');
        try {
            const response = await fetch(root.dataset.editorUploadUrl ?? '/editor-media', {
                method: 'POST', body, credentials: 'same-origin', headers: {
                    'X-CSRF-TOKEN': csrf, 'Accept': 'application/json',
                },
            });
            if (!response.ok) {
                const detail = await response.json().catch(() => null) as { message?: string } | null;
                inform(detail?.message ?? 'Upload failed (' + String(response.status) + '). Check file size and format.');
                return;
            }
            const result: unknown = await response.json();
            if (!result || typeof result !== 'object' || !('url' in result) ||
                typeof result.url !== 'string' || !mediaPattern.test(result.url) || !('mime' in result) || typeof result.mime !== 'string') {
                inform('Upload response was invalid.'); return;
            }
            const href = result.url;
            const name = escapeMarkup(file.name.slice(0, 120));
            let markup = '';
            if (result.mime.startsWith('image/')) markup = '<figure><img src="' + href + '" alt="' + name + '"><figcaption>' + name + '</figcaption></figure><p></p>';
            else if (result.mime.startsWith('audio/')) markup = '<figure><audio src="' + href + '" controls preload="none"></audio><figcaption>' + name + '</figcaption></figure><p></p>';
            else if (result.mime.startsWith('video/')) markup = '<figure><video src="' + href + '" controls preload="none"></video>' +
                (lesson ? '' : '<figcaption>' + name + '</figcaption>') + '</figure><p></p>';
            else if (lesson) markup = '<p><a data-resource="file" href="' + href + '">' + name + '</a></p>';
            if (insertion && visual.contains(insertion.startContainer)) selection = insertion;
            if (lesson) {
                const parsed = document.createElement('template');
                parsed.innerHTML = markup;
                const element = parsed.content.firstElementChild as HTMLElement | null;
                if (element) insertLessonElement(element);
            } else {
                command('insertHTML', markup);
            }
            inform('Media uploaded privately. Save your document to attach it.');
        } catch {
            inform('Media could not be uploaded. Check your connection.');
        } finally {
            upload.value = '';
            upload.disabled = false;
            uploading = false;
        }
    });

    for (const field of Array.from(form.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('[data-document-title],[data-seo-title],[data-seo-description],[data-editor-summary]'))) {
        field.addEventListener('input', () => updateSidebar(plain.value ? plain.value.split(/\s+/u).length : 0));
    }
    form.addEventListener('submit', event => {
        sync();
        if (uploading) {
            event.preventDefault();
            inform('Wait for the upload to finish before saving.');
            return;
        }
        if (required && !plain.value.trim() && !(lesson && /<(?:img|audio|video)\b| data-youtube=/.test(html.value))) {
            event.preventDefault();
            inform('Add some text or a caption before saving.');
            if (mode === 'html') source.focus(); else visual.focus();
        }
        if (plain.value.length > plain.maxLength || (course && html.value.length > htmlLimit)) {
            event.preventDefault();
            inform('Content is longer than the allowed character limit.');
            if (mode === 'html') source.focus(); else visual.focus();
        }
    });
    if (locked) root.querySelectorAll<HTMLInputElement | HTMLButtonElement | HTMLSelectElement>('[data-editor-enhanced] button,[data-editor-enhanced] input,[data-editor-enhanced] select]').forEach(control => { control.disabled = true; });
    sync();
}
function youtubeId(value: string): string | null {
    const pattern = /^[A-Za-z0-9_-]{11}$/;
    if (pattern.test(value)) return value;
    let url: URL;
    try { url = new URL(value); } catch { return null; }
    if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password || url.port) return null;
    const segments = url.pathname.split('/').filter(Boolean);
    let id: string | null = null;
    if (url.hostname === 'youtu.be' && segments.length === 1) id = segments[0];
    if (['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'].includes(url.hostname)) {
        if (url.pathname === '/watch') id = url.searchParams.get('v');
        else if (segments.length === 2 && ['embed', 'shorts', 'live'].includes(segments[0])) id = segments[1];
    }
    return id && pattern.test(id) ? id : null;
}

document.querySelectorAll<HTMLElement>('[data-rich-editor]').forEach(initializeEditor);
