import { test, expect } from '@playwright/test';

const example = `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body><section id="homepage-campaign-management" class="bg-white dark:bg-slate-900">
    <div><div><span>HOMEPAGE CAMPAIGNS</span><h2>Quiz &amp; Exam manager</h2>
        <p>Manage individual campaigns shown in the homepage hero.</p></div>
        <a data-hcm-create href="#">Create a new campaign</a></div>
    <div class="overflow-x-auto"><table><thead><tr><th>Campaign</th><th>Type</th><th>Status</th></tr></thead>
        <tbody><tr><td><a href="#">English Grammar</a></td><td>Quiz</td>
            <td><span data-campaign-status="draft">Draft</span></td></tr></tbody></table></div>
    <p>Showing 1–1 of 1 campaigns</p>
    <form><label for="go">Go to page</label><input id="go" type="number" value="1"><button type="submit">Go</button></form>
    <details data-campaign-menu><summary>Actions</summary><div data-campaign-popover>
        <a href="#">Edit</a><button type="button">Publish</button></div></details>
</section></body></html>`;

function luminance(color) {
    const channels = (color.match(/[\d.]+/g) || []).slice(0, 3).map(Number);
    if (channels.length !== 3) throw new Error('Unrecognized CSS color: ' + color);
    const values = channels.map(c => {
        const v = c / 255;
        return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    });
    return values[0] * 0.2126 + values[1] * 0.7152 + values[2] * 0.0722;
}
function contrast(a, b) {
    const first = luminance(a), second = luminance(b);
    return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}
for (const mode of ['light', 'dark']) {
    test(`Campaign Manager respects ${mode} mode with readable cells and actions`, async ({ page }) => {
        await page.route('**/up', route => route.fulfill({
            status: 200, contentType: 'text/html', body: 'ok',
        }));
        await page.goto('/up');
        await page.setContent(example);
        await page.addStyleTag({ path: 'public/css/homepage-campaigns-theme.css' });
        if (mode === 'dark') await page.evaluate(() => document.documentElement.classList.add('dark'));

        const computed = await page.evaluate(() => {
            const get = selector => {
                const el = document.querySelector(selector);
                const style = getComputedStyle(el);
                return { color: style.color, bg: style.backgroundColor, border: style.borderColor };
            };
            return {
                section: get('#homepage-campaign-management'),
                heading: get('#homepage-campaign-management h2'),
                row: get('#homepage-campaign-management tbody tr'),
                cell: get('#homepage-campaign-management tbody td'),
                header: get('#homepage-campaign-management th'),
                thead: get('#homepage-campaign-management thead'),
                link: get('#homepage-campaign-management [data-hcm-create]'),
                label: get('#homepage-campaign-management form label'),
                control: get('#homepage-campaign-management form input'),
                draft: get('#homepage-campaign-management [data-campaign-status]'),
                popover: get('#homepage-campaign-management [data-campaign-popover]'),
            };
        });
        if (mode === 'dark') {
            expect(computed.section.bg).toBe('rgb(18, 42, 68)');
            expect(computed.row.bg).toBe('rgb(20, 46, 76)');
        } else {
            expect(computed.section.bg).toBe('rgb(255, 255, 255)');
            expect(computed.row.bg).toBe('rgb(255, 255, 255)');
        }
        expect(contrast(computed.heading.color, computed.section.bg)).toBeGreaterThan(4.5);
        expect(contrast(computed.cell.color, computed.row.bg)).toBeGreaterThan(4.5);
        expect(contrast(computed.header.color, computed.thead.bg)).toBeGreaterThan(4.5);
        expect(contrast(computed.link.color, computed.link.bg)).toBeGreaterThan(4.5);
        expect(contrast(computed.label.color, computed.section.bg)).toBeGreaterThan(4.5);
        expect(contrast(computed.control.color, computed.control.bg)).toBeGreaterThan(4.5);
        expect(contrast(computed.draft.color, computed.draft.bg)).toBeGreaterThan(4.5);
        expect(contrast(computed.popover.color, computed.popover.bg)).toBeGreaterThan(4.5);
    });
}
