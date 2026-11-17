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
