const printPolicy = document.querySelector<HTMLButtonElement>('[data-print-policy]');
printPolicy?.addEventListener('click', () => window.print());

const policyLink = document.querySelector<HTMLButtonElement>('[data-policy-link]');
const policyLinkStatus = document.querySelector<HTMLElement>('[data-policy-link-status]');
if (policyLink && policyLinkStatus) {
    policyLink.addEventListener('click', async () => {
        const url = policyLink.dataset.policyLink!;
        try {
            await navigator.clipboard.writeText(url);
            policyLinkStatus.textContent = 'Policy link copied.';
        } catch {
            policyLinkStatus.textContent = url;
        }
    });
}
