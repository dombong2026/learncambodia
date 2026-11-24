type PopoverToggle = Event & { newState: 'open' | 'closed' };

function positionLearningMenu(menu: HTMLElement, trigger: HTMLButtonElement): void {
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
}

const learningMenuTriggers = new Map<HTMLElement, HTMLButtonElement>();
document.querySelectorAll<HTMLElement>('.learning-manager-menu').forEach(menu => {
    const trigger = document.querySelector<HTMLButtonElement>(`[popovertarget="${menu.id}"]`);
    if (!trigger) return;
    learningMenuTriggers.set(menu, trigger);
    menu.addEventListener('beforetoggle', event => {
        if ((event as PopoverToggle).newState === 'open') positionLearningMenu(menu, trigger);
    });
});

// Browser focus and scroll-into-view can dispatch a late scroll event just
// after native popover activation, especially in narrow horizontal tables.
// Reposition the open menu instead of immediately dismissing it.
let learningMenuFrame = 0;
function scheduleLearningMenuPosition(): void {
    if (learningMenuFrame) return;
    learningMenuFrame = window.requestAnimationFrame(() => {
        learningMenuFrame = 0;
        const menu = document.querySelector<HTMLElement>('.learning-manager-menu:popover-open');
        if (!menu) return;
        const trigger = learningMenuTriggers.get(menu);
        if (!trigger || !trigger.isConnected) {
            menu.hidePopover();
            return;
        }
        const rect = trigger.getBoundingClientRect();
        if (rect.bottom <= 0 || rect.top >= window.innerHeight ||
            rect.right <= 0 || rect.left >= document.documentElement.clientWidth) {
            menu.hidePopover();
            return;
        }
        positionLearningMenu(menu, trigger);
    });
}

window.addEventListener('resize', scheduleLearningMenuPosition);
document.addEventListener('scroll', event => {
    if (!(event.target instanceof HTMLElement) || !event.target.closest('.learning-manager-menu')) {
        scheduleLearningMenuPosition();
    }
}, { capture: true, passive: true });

document.addEventListener('submit', event => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.learningDelete && !window.confirm(form.dataset.learningDelete)) {
        event.preventDefault();
    }
});
