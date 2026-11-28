const mode = document.querySelector('[name="viewer_mode"]');
const courseSettings = document.querySelector('[data-exam-course-settings]');
const pricingSettings = document.querySelector('[data-exam-pricing-settings]');

if (mode && courseSettings && pricingSettings) {
    const update = () => {
        const specialized = mode.value === 'specialized';
        courseSettings.hidden = pricingSettings.hidden = specialized;
        courseSettings.disabled = pricingSettings.disabled = specialized;
    };
    mode.addEventListener('change', update);
    update();
}
