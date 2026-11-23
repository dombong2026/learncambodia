/* Learn Cambodia homepage Course Player — no third-party dependencies. */
(function () {
    'use strict';
    function init() {
        const root = document.querySelector('[data-home-course-hero]');
        if (!root) return;
        const list = root.querySelector('[data-home-course-list]');
        const screens = Array.from(root.querySelectorAll('[data-home-course-screen]'));
        const outlines = Array.from(root.querySelectorAll('[data-home-course-outline]'));
        const courses = Array.from(root.querySelectorAll('[data-home-course-choice]'));
        const lessonLinks = Array.from(root.querySelectorAll('[data-home-lesson-choice]'));
        const stop = () => {
            root.querySelectorAll('video, audio').forEach(media => { media.pause(); });
            root.querySelectorAll('iframe').forEach(frame => {
                const url = frame.getAttribute('src');
                if (url && !frame.dataset.source) frame.dataset.source = url;
                frame.removeAttribute('src');
            });
        };
        const show = (id, outline) => {
            stop();
            screens.forEach(screen => {
                screen.hidden = screen.dataset.homeCourseScreen !== id;
                const media = screen.querySelector('[data-home-course-media]');
                if (media) media.hidden = false;
                screen.querySelectorAll('[data-home-lesson-preview]').forEach(p => { p.hidden = true; });
            });
            outlines.forEach(section => { section.hidden = !outline || section.dataset.homeCourseOutline !== id; });
            if (list) list.hidden = outline;
            courses.forEach(link => {
                if (link.dataset.homeCourseChoice === id) link.setAttribute('aria-current', 'true');
                else link.removeAttribute('aria-current');
            });
            const screen = screens.find(s => s.dataset.homeCourseScreen === id);
            if (screen) screen.querySelectorAll('iframe[data-source]').forEach(frame => {
                frame.setAttribute('src', frame.dataset.source);
            });
        };
        courses.forEach(link => link.addEventListener('click', event => {
            const id = link.dataset.homeCourseChoice;
            if (!screens.some(screen => screen.dataset.homeCourseScreen === id)) return;
            event.preventDefault();
            show(id, true);
        }));
        root.querySelectorAll('[data-home-course-back]').forEach(button => button.addEventListener('click', () => {
            const current = screens.find(screen => !screen.hidden);
            if (!current) return;
            show(current.dataset.homeCourseScreen, false);
            const active = courses.find(link => link.dataset.homeCourseChoice === current.dataset.homeCourseScreen);
            if (active) active.focus({ preventScroll: true });
        }));
        lessonLinks.forEach(link => link.addEventListener('click', event => {
            const id = link.dataset.homeLessonChoice;
            const preview = Array.from(root.querySelectorAll('[data-home-lesson-preview]'))
                .find(el => el.dataset.homeLessonPreview === id);
            if (!preview) return;
            event.preventDefault();
            stop();
            const screen = preview.closest('[data-home-course-screen]');
            if (!screen || screen.hidden) return;
            const media = screen.querySelector('[data-home-course-media]');
            if (media) media.hidden = true;
            screen.querySelectorAll('[data-home-lesson-preview]').forEach(el => { el.hidden = el !== preview; });
            lessonLinks.forEach(el => {
                if (el === link) el.setAttribute('aria-current', 'true');
                else el.removeAttribute('aria-current');
            });
            preview.scrollTop = 0;
        }));
        /* Prevent hidden YouTube embeds from loading / playing at initial render. */
        const first = screens.find(screen => !screen.hidden);
        if (first) show(first.dataset.homeCourseScreen, false);
        window.addEventListener('pagehide', stop);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
