export function initCurriculumOrder(
    builder: HTMLElement,
    onReorder: (list: HTMLElement, order: number[]) => void,
): () => boolean {
    const status = builder.querySelector<HTMLElement>('[data-order-status]');
    const csrf = builder.querySelector<HTMLInputElement>('input[name="_token"]')?.value
        ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (!status || !csrf) return () => false;

    let saving = false;
    const lists = Array.from(builder.querySelectorAll<HTMLElement>('[data-order-list]'));

    function announce(message: string): void {
        status!.hidden = false;
        status!.textContent = message;
    }

    function rows(list: HTMLElement): HTMLElement[] {
        return Array.from(list.querySelectorAll<HTMLElement>(':scope > [data-order-id]'));
    }

    async function move(list: HTMLElement, row: HTMLElement, target: HTMLElement, focus?: HTMLButtonElement): Promise<void> {
        if (saving || row === target) return;
        const current = rows(list);
        const reordered = [...current];
        reordered.splice(current.indexOf(row), 1);
        reordered.splice(current.indexOf(target), 0, row);
        const ids = (listRows: HTMLElement[]) => listRows.map(item => Number(item.dataset.orderId));
        const order = ids(reordered);

        saving = true;
        list.setAttribute('aria-busy', 'true');
        announce('Saving order…');

        try {
            const response = await fetch(list.dataset.orderUrl!, {
                method: 'PATCH',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf! },
                body: JSON.stringify({ order, expected: ids(current) }),
            });

            if (!response.ok) {
                announce(response.status === 409
                    ? 'The curriculum changed. Save any open edits and refresh to load the latest order.'
                    : 'Could not save the order. Try again or reload the page.');
                return;
            }

            // Move only the dragged row, preserving other nodes and their focus state.
            target.insertAdjacentElement(current.indexOf(row) < current.indexOf(target) ? 'afterend' : 'beforebegin', row);
            reordered.forEach((item, index) => {
                const number = item.querySelector('[data-order-number]');
                if (number) number.textContent = String(index + 1);
            });
            onReorder(list, order);
            if (focus && document.activeElement === document.body) focus.focus({ preventScroll: true });
            announce(`${list.dataset.orderList === 'module' ? 'Module' : 'Lesson'} moved to position ${order.indexOf(Number(row.dataset.orderId)) + 1} of ${order.length}.`);
        } catch {
            announce('Could not confirm the new order. Refresh the page before trying again.');
        } finally {
            saving = false;
            list.removeAttribute('aria-busy');
        }
    }

    for (const list of lists) {
        for (const row of rows(list)) {
            const handle = row.querySelector<HTMLButtonElement>('[data-order-handle]')!;
            handle.hidden = false;
            let pointer: { id: number; y: number; target: HTMLElement | null } | null = null;

            function release(): HTMLElement | null {
                if (!pointer) return null;
                const { id, target } = pointer;
                pointer = null;
                if (handle.hasPointerCapture(id)) handle.releasePointerCapture(id);
                delete row.dataset.orderDragging;
                target?.removeAttribute('data-order-target');
                return target;
            }

            handle.addEventListener('pointerdown', event => {
                if (!event.isPrimary || event.button !== 0 || saving) return;
                pointer = { id: event.pointerId, y: event.clientY, target: null };
                handle.setPointerCapture(event.pointerId);
            });

            handle.addEventListener('pointermove', event => {
                if (pointer?.id !== event.pointerId) return;
                if (!row.hasAttribute('data-order-dragging') && Math.abs(pointer.y - event.clientY) < 6) return;
                row.dataset.orderDragging = '';
                pointer.target?.removeAttribute('data-order-target');
                const bounds = list.getBoundingClientRect();
                if (event.clientX < bounds.left - 20 || event.clientX > bounds.right + 20) {
                    pointer.target = null;
                    return;
                }

                const siblings = rows(list);
                const target = siblings.find(item => event.clientY < item.getBoundingClientRect().bottom) ?? siblings.at(-1);
                pointer.target = target && target !== row ? target : null;
                if (pointer.target) pointer.target.dataset.orderTarget = row.compareDocumentPosition(pointer.target) & Node.DOCUMENT_POSITION_FOLLOWING ? 'after' : 'before';
            });

            handle.addEventListener('pointerup', event => {
                if (pointer?.id !== event.pointerId) return;
                const target = release();
                if (target) void move(list, row, target);
            });
            handle.addEventListener('lostpointercapture', release);
            handle.addEventListener('pointercancel', release);
            handle.addEventListener('keydown', event => {
                if (event.key === 'Escape') release();
                if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;
                event.preventDefault();

                const siblings = rows(list);
                const target = siblings[siblings.indexOf(row) + (event.key === 'ArrowUp' ? -1 : 1)];
                if (target) void move(list, row, target, handle);
            });
        }
    }

    return () => saving;
}
