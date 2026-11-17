import { expect, test } from '@playwright/test';

test('detail hero fits the viewport and only scrolls an overflowing title', async ({ page }) => {
  await page.goto('/articles/efl-keep-a-weekly-english-journal?lang=en');
  const hero = page.locator('.detail-hero');
  const title = hero.getByRole('heading', { level: 1 });
  const text = title.locator('span');
  const cover = hero.locator('.detail-cover img');
  const toggle = page.locator('[data-detail-title-toggle]');

  await expect(title).toHaveText('Keep a Weekly English Journal');
  await expect(title).toHaveCSS('white-space', 'nowrap');
  await expect(cover).toBeVisible();
  await expect(cover).toHaveCSS('object-fit', 'cover');
  await expect(cover).toHaveJSProperty('complete', true);
  expect(await cover.evaluate(img => img.naturalWidth)).toBeGreaterThan(0);
  await expect(hero.locator('nav')).toHaveCount(0);

  await page.emulateMedia({ reducedMotion: 'no-preference' });
  await page.setViewportSize({ width: 1600, height: 900 });
  await expect(toggle).toBeHidden();
  await expect(text).toHaveCSS('animation-name', 'none');

  await page.setViewportSize({ width: 360, height: 800 });
  await expect(toggle).toBeVisible();
  await expect(text).toHaveCSS('animation-name', 'detail-title-scroll');
  await toggle.click();
  await expect(toggle).toHaveAccessibleName('Resume title scrolling');
  await expect(text).toHaveCSS('animation-play-state', 'paused');

  await page.emulateMedia({ reducedMotion: 'reduce' });
  await expect(toggle).toBeHidden();
  await expect(text).toHaveCSS('animation-name', 'none');
  expect(await page.locator('html').evaluate(html => html.scrollWidth)).toBeLessThanOrEqual(362);
  await hero.getByRole('link', { name: 'Read and explore' }).click();
  await expect(page).toHaveURL(/#article-body$/);
});

test('detail Share copies the canonical link or exposes it for manual copying', async ({ page }) => {
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'share', { value: undefined });
    Object.defineProperty(navigator, 'clipboard', {
      value: {
        writeText: (text) => {
          if (document.documentElement.hasAttribute('data-test-deny-copy')) {
            return Promise.reject(new Error('Clipboard unavailable'));
          }
          document.documentElement.setAttribute('data-test-copied-url', text);
          return Promise.resolve();
        },
      },
    });
  });
  await page.goto('/articles/efl-keep-a-weekly-english-journal?lang=en');
  const hero = page.locator('.detail-hero');
  const share = hero.getByRole('button', { name: 'Share', exact: true });
  const url = await page.locator('link[rel="canonical"]').getAttribute('href');

  await expect(hero.locator('.line-clamp-2')).toHaveCount(0);
  await share.click();
  await expect(hero.getByRole('status')).toHaveText('Link copied.');
  await expect(page.locator('html')).toHaveAttribute('data-test-copied-url', url);

  await page.evaluate(() => document.documentElement.setAttribute('data-test-deny-copy', ''));
  await share.click();
  await expect(hero.getByRole('status')).toHaveText('Copy this link to share the page.');
  await expect(hero.getByLabel('Page link')).toHaveValue(url);
});
