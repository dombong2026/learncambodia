const heading = document.querySelector<HTMLElement>('[data-detail-heading]');
const title = heading?.querySelector<HTMLHeadingElement>('h1');
const text = title?.querySelector<HTMLSpanElement>('span');
const toggle = heading?.querySelector<HTMLButtonElement>('[data-detail-title-toggle]');

if (heading && title && text && toggle) {
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const pauseIcon = toggle.querySelector('[data-detail-pause]');
    const playIcon = toggle.querySelector('[data-detail-play]');
    let paused = false;

    const refresh = (): void => {
        // Measure against the space available when the control is hidden.
        const overflows = text.offsetWidth > heading.clientWidth;
        toggle.hidden = !overflows || motion.matches;
        title.tabIndex = overflows && motion.matches ? 0 : -1;
        title.toggleAttribute('data-overflow', overflows);
        if (!overflows || motion.matches) return;

        const distance = text.offsetWidth - title.clientWidth;
        title.style.setProperty('--detail-scroll-distance', `-${distance}px`);
        title.style.setProperty('--detail-scroll-duration', `${Math.max(6, distance / 32 + 2)}s`);
    };

    toggle.addEventListener('click', () => {
        paused = !paused;
        heading.toggleAttribute('data-title-paused', paused);
        const label = (paused ? toggle.dataset.playLabel : toggle.dataset.pauseLabel) ?? '';
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        pauseIcon?.toggleAttribute('hidden', paused);
        playIcon?.toggleAttribute('hidden', !paused);
    });
    motion.addEventListener('change', refresh);
    const observer = new ResizeObserver(refresh);
    observer.observe(heading);
    observer.observe(text);
    refresh();
}
