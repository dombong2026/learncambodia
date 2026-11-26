(() => {
    'use strict';
    const init = () => {
        const shell = document.querySelector('[data-workspace]');
        const sidebar = shell?.querySelector('[data-sidebar]');
        const header = shell?.querySelector('[data-sidebar-toggle]');
        const edge = sidebar?.querySelector('[data-glass-sidebar-toggle]');
        const wordmark = sidebar?.querySelector('[data-glass-wordmark]');
        const mark = sidebar?.querySelector('[data-glass-brand-icon]');
        if (!shell || !sidebar || !header || !edge || !wordmark || !mark) return;
        const desktop = matchMedia('(min-width: 64rem)');
        const root = document.documentElement;
        const collapsed = () => desktop.matches && root.dataset.desktopSidebar === 'closed';
        // The existing workspace controller owns preferences, mobile drawer, search and focus.
        // Only change the presentation of its desktop closed state into an accessible rail.
        const sync = () => {
            const compact = collapsed();
            if (desktop.matches && sidebar.inert) sidebar.inert = false;
            edge.hidden = !desktop.matches;
            edge.setAttribute('aria-expanded', String(!compact));
            const labels = root.lang === 'km' ? ['ពង្រីកម៉ឺនុយ','បង្រួមម៉ឺនុយ'] : root.lang === 'zh' ? ['展开导航','收起导航'] : ['Expand navigation','Collapse navigation'];
            edge.title = labels[compact ? 0 : 1];
            edge.setAttribute('aria-label', edge.title);
            wordmark.hidden = compact;
            mark.hidden = !compact;
            sidebar.querySelectorAll('[data-nav-group]>summary').forEach(summary => {
                const label = summary.textContent.trim();
                if (compact) summary.title = label;
                else summary.removeAttribute('title');
            });
            sidebar.querySelectorAll('[data-glass-footer-label]').forEach(label => {
                const link = label.closest('a');
                if (compact) link.title = label.textContent.trim();
                else link.removeAttribute('title');
            });
        };
        edge.addEventListener('click', () => {
            if (!desktop.matches) return;
            header.click();
            sync();
            edge.focus({preventScroll:true});
        });
        sidebar.addEventListener('click', event => {
            const summary = event.target.closest('[data-nav-group]>summary');
            if (!summary || !collapsed()) return;
            event.preventDefault();
            header.click();
            summary.parentElement.open = true;
            sync();
            summary.focus({preventScroll:true});
        });
        new MutationObserver(sync).observe(root,{attributes:true,attributeFilter:['data-desktop-sidebar']});
        new MutationObserver(sync).observe(sidebar,{attributes:true,attributeFilter:['inert']});
        desktop.addEventListener('change',sync);
        shell.setAttribute('data-glass-sidebar','');
        sync();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',init,{once:true});
    else init();
})();
