type PopoverToggle = Event & { newState: 'open' | 'closed' };

document.querySelectorAll<HTMLElement>('.learning-manager-menu').forEach(menu => {
    const trigger = document.querySelector<HTMLButtonElement>(`[popovertarget="${menu.id}"]`);
    menu.addEventListener('beforetoggle', event => {
        if ((event as PopoverToggle).newState !== 'open' || !trigger) return;
        const rect = trigger.getBoundingClientRect();
        const margin = 8;
        const viewport = document.documentElement.clientWidth;
        const width = Math.min(208, viewport - 2 * margin);
        const below = window.innerHeight - rect.bottom;
        const above = rect.top;

        menu.style.width = `${width}px`;
        menu.style.left = `${Math.min(viewport - width - margin, Math.max(margin, rect.right - width))}px`;
        const useAbove = below < 240 && above > below;
        menu.style.top = useAbove ? 'auto' : `${rect.bottom + margin}px`;
        menu.style.bottom = useAbove ? `${window.innerHeight - rect.top + margin}px` : 'auto';
        menu.style.maxHeight = `${Math.max(44, (useAbove ? above : below) - 2 * margin)}px`;
    });
});

function closeLearningMenu(): void {
    document.querySelector<HTMLElement>('.learning-manager-menu:popover-open')?.hidePopover();
}

window.addEventListener('resize', closeLearningMenu);
document.addEventListener('scroll', event => {
    if (!(event.target instanceof HTMLElement) || !event.target.closest('.learning-manager-menu')) {
        closeLearningMenu();
    }
}, { capture: true, passive: true });

document.addEventListener('submit', event => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.learningDelete && !window.confirm(form.dataset.learningDelete)) {
        event.preventDefault();
    }
});
