// Public homepage promotional player. Published courses only: lesson content
// appears only when a lesson has explicitly been marked as a public preview.
// No enrollment or assessment actions are performed by this component.
function initHomeCourseHero(): void {
    const hero = document.querySelector<HTMLElement>('[data-home-course-hero]');
    if (!hero) return;

    const list = hero.querySelector<HTMLElement>('[data-home-course-list]');
    const screens = Array.from(hero.querySelectorAll<HTMLElement>('[data-home-course-screen]'));
    const outlines = Array.from(hero.querySelectorAll<HTMLElement>('[data-home-course-outline]'));
    const choices = Array.from(hero.querySelectorAll<HTMLAnchorElement>('[data-home-course-choice]'));

    const stopPlayback = (): void => {
        hero.querySelectorAll<HTMLMediaElement>('video, audio').forEach(media => {
            media.pause();
        });
        // Remove embedded videos' browsing contexts when switching to another
        // course, preventing playback from continuing in an invisible iframe.
        hero.querySelectorAll<HTMLIFrameElement>('iframe').forEach(frame => {
            const source = frame.getAttribute('src');
            if (source && !frame.dataset.homeSource) frame.dataset.homeSource = source;
            frame.removeAttribute('src');
        });
    };

    const showCourse = (courseId: string, outline: boolean): void => {
        stopPlayback();
        screens.forEach(screen => {
            screen.hidden = screen.dataset.homeCourseScreen !== courseId;
            screen.querySelector<HTMLElement>('[data-home-course-media]')?.removeAttribute('hidden');
            screen.querySelectorAll<HTMLElement>('[data-home-lesson-preview]').forEach(preview => { preview.hidden = true; });
        });
        outlines.forEach(section => { section.hidden = !outline || section.dataset.homeCourseOutline !== courseId; });
        if (list) list.hidden = outline;
        choices.forEach(choice => {
            if (choice.dataset.homeCourseChoice === courseId) choice.setAttribute('aria-current', 'true');
            else choice.removeAttribute('aria-current');
        });
        const active = screens.find(screen => screen.dataset.homeCourseScreen === courseId);
        active?.querySelectorAll<HTMLIFrameElement>('iframe[data-home-source]').forEach(frame => {
            if (frame.dataset.homeSource) frame.setAttribute('src', frame.dataset.homeSource);
        });
    };

    choices.forEach(choice => {
        choice.addEventListener('click', event => {
            const id = choice.dataset.homeCourseChoice;
            if (!id || !screens.some(screen => screen.dataset.homeCourseScreen === id)) return;
            event.preventDefault();
            showCourse(id, true);
        });
    });

    hero.querySelectorAll<HTMLButtonElement>('[data-home-course-back]').forEach(button => {
        button.addEventListener('click', () => {
            const current = screens.find(screen => !screen.hidden);
            if (current?.dataset.homeCourseScreen) showCourse(current.dataset.homeCourseScreen, false);
            list?.querySelector<HTMLElement>('[aria-current="true"]')?.focus({ preventScroll: true });
        });
    });

    hero.querySelectorAll<HTMLAnchorElement>('[data-home-lesson-choice]').forEach(choice => {
        choice.addEventListener('click', event => {
            const id = choice.dataset.homeLessonChoice;
            const preview = id ? Array.from(hero.querySelectorAll<HTMLElement>('[data-home-lesson-preview]'))
                .find(item => item.dataset.homeLessonPreview === id) : null;
            if (!preview) return; // No public preview: follow the real course link.
            event.preventDefault();
            stopPlayback();
            const screen = preview.closest<HTMLElement>('[data-home-course-screen]');
            screen?.querySelector<HTMLElement>('[data-home-course-media]')?.setAttribute('hidden', '');
            screen?.querySelectorAll<HTMLElement>('[data-home-lesson-preview]').forEach(item => { item.hidden = item !== preview; });
            preview.scrollTop = 0;
        });
    });
}

initHomeCourseHero();
