(() => {
    'use strict';
    const root = document.querySelector('[data-playlist-builder]');
    if (!root) return;
    let selected = JSON.parse(root.dataset.current);
    let page = 1, last = 1, serial = 0;
    const query = root.querySelector('[data-playlist-query]');
    const results = root.querySelector('[data-playlist-results]');
    const order = root.querySelector('[data-playlist-order]');
    const message = root.querySelector('[data-playlist-message]');
    const save = root.querySelector('[data-playlist-save]');
    const previous = root.querySelector('[data-playlist-prev]');
    const following = root.querySelector('[data-playlist-next]');
    const button = (text, action) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'wsm-button wsm-outline';
        b.textContent = text; b.addEventListener('click', action); return b;
    };
    const row = (name, buttons) => {
        const el = document.createElement('div');
        el.className = 'wsm-history-row';
        const title = document.createElement('span'); title.textContent = name;
        title.style.overflowWrap = 'anywhere';
        const controls = document.createElement('div'); controls.className = 'wsm-actions';
        controls.append(...buttons); el.append(title, controls); return el;
    };
    const render = () => {
        order.replaceChildren();
        selected.forEach((clip, index) => {
            const move = offset => {
                const target = index + offset;
                if (target < 0 || target >= selected.length) return;
                [selected[index], selected[target]] = [selected[target], selected[index]];
                render();
            };
            const up = button('↑', () => move(-1)); up.disabled = index === 0; up.setAttribute('aria-label', 'Move up: ' + clip.name);
            const down = button('↓', () => move(1)); down.disabled = index === selected.length - 1; down.setAttribute('aria-label', 'Move down: ' + clip.name);
            const item = row((index + 1) + '. ' + clip.name, [up, down, button('Remove', () => { selected.splice(index, 1); render(); })]);
            const input = document.createElement('input'); input.type = 'hidden'; input.name = 'playlist_ids[]'; input.value = String(clip.id);
            item.append(input); order.append(item);
        });
        save.disabled = selected.length < 2 || selected.length > 20;
        if (!selected.length) order.textContent = 'No videos selected.';
        message.textContent = selected.length + ' / 20 videos selected.';
    };
    const search = async target => {
        const id = ++serial;
        const url = new URL(root.dataset.libraryUrl, location.origin);
        url.searchParams.set('kind', 'video'); url.searchParams.set('q', query.value); url.searchParams.set('page', String(target));
        try {
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            if (!response.ok) throw Error('Request failed');
            const data = await response.json();
            if (serial !== id) return;
            page = data.page; last = data.last_page;
            results.replaceChildren();
            for (const clip of data.items) {
                const add = button('Add', () => {
                    if (selected.length >= 20 || selected.some(item => item.id === clip.id)) return;
                    selected.push({id: clip.id, name: clip.name}); render();
                });
                results.append(row(clip.name, [add]));
            }
            if (!data.items.length) results.textContent = 'No videos found.';
            previous.disabled = page <= 1; following.disabled = page >= last;
        } catch {
            if (serial === id) results.textContent = 'Unable to load videos. Try again.';
        }
    };
    root.querySelector('[data-playlist-preset]').addEventListener('click', () => {
        selected = JSON.parse(root.dataset.preset); render();
    });
    root.querySelector('[data-playlist-search]').addEventListener('click', () => void search(1));
    query.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); void search(1); } });
    previous.addEventListener('click', () => void search(page - 1));
    following.addEventListener('click', () => void search(page + 1));
    render();
})();