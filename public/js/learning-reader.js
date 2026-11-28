(() => {
    const sizes = [80, 90, 100, 110, 120, 130, 140, 150, 160];
    const storageKey = 'learncambodia.reading-scale';
    const textSelectors = 'h1,h2,h3,h4,h5,h6,p,span,small,strong,em,li,dt,dd,th,td,blockquote,label,legend,a,summary,button,output,textarea,select,input[type="text"],input[type="search"],time,[role="heading"],.fl-reading-text';

    document.querySelectorAll('[data-learning-reader]').forEach((reader) => {
        const toolbar = reader.querySelector('[data-reader-toolbar]');
        if (!toolbar) return;
        const decrease = toolbar.querySelector('[data-reader-decrease]');
        const increase = toolbar.querySelector('[data-reader-increase]');
        const reset = toolbar.querySelector('[data-reader-reset]');
        let scale = 100;
        try {
            const saved = Number(localStorage.getItem(storageKey));
            if (sizes.includes(saved)) scale = saved;
        } catch {
            // The controls still work when browser storage is unavailable.
        }

        function setZoom(nextScale) {
            scale = nextScale;
            reader.removeAttribute('data-reader-scaled');
            if (scale !== 100) {
                const targets = [...reader.querySelectorAll(textSelectors)]
                    .filter((node) => !node.closest('[data-reader-toolbar],svg,math,[aria-hidden="true"]'));
                const base = targets.map((node) => {
                    const style = getComputedStyle(node);
                    return [node, parseFloat(style.fontSize), parseFloat(style.lineHeight)];
                });
                for (const [node, fontSize, lineHeight] of base) {
                    if (!fontSize) continue;
                    node.setAttribute('data-reader-text', '');
                    node.style.setProperty('--lc-reading-font-size', `${fontSize * scale / 100}px`);
                    node.style.setProperty('--lc-reading-line-height', lineHeight ? `${lineHeight * scale / 100}px` : 'normal');
                }
                reader.setAttribute('data-reader-scaled', '');
            }
            reset.textContent = `${scale}%`;
            decrease.disabled = scale === sizes[0];
            increase.disabled = scale === sizes[sizes.length - 1];
        }

        const change = (value) => {
            setZoom(value);
            try { localStorage.setItem(storageKey, String(value)); } catch { /* Storage is optional. */ }
        };
        decrease.addEventListener('click', () => change(sizes[sizes.indexOf(scale) - 1]));
        increase.addEventListener('click', () => change(sizes[sizes.indexOf(scale) + 1]));
        reset.addEventListener('click', () => change(100));
        let resize;
        window.addEventListener('resize', () => {
            if (scale === 100) return;
            clearTimeout(resize);
            resize = setTimeout(() => setZoom(scale), 120);
        });
        toolbar.hidden = false;
        setZoom(scale);

        const attempt = reader.querySelector('[data-exam-attempt]');
        const labels = attempt?.querySelector('[data-exam-feedback-labels], [data-khmer-feedback-labels]');
        if (!labels) return;

        // Keep the shared exam script's answer locking and requests intact.
        const observer = new MutationObserver((records) => {
            for (const record of records) {
                const status = record.target.nodeType === Node.TEXT_NODE ? record.target.parentElement : record.target;
                if (!status?.matches('[data-exam-feedback]')) continue;
                let value = status.textContent;
                if (value.startsWith('Correct.')) value = labels.dataset.correct + value.slice('Correct.'.length);
                else if (value.startsWith('Incorrect.')) value = labels.dataset.incorrect + value.slice('Incorrect.'.length);
                else if (value === 'Choose or enter an answer first.') value = labels.dataset.choose;
                else if (value === 'Checking answer…') value = labels.dataset.checking;
                else if (value === 'Unable to check the answer. Try again.') value = labels.dataset.error;
                if (value.endsWith(' Answer locked.')) value = value.slice(0, -' Answer locked.'.length) + ' ' + labels.dataset.locked;
                if (value !== status.textContent) status.textContent = value;
            }
        });
        observer.observe(attempt, {childList: true, characterData: true, subtree: true});
    });
})();
