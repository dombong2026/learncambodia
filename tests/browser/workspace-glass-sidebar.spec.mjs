import { expect, test } from '@playwright/test';

test('workspace glass preserves real navigation in expanded, rail and mobile modes', async ({page}) => {
  await page.goto('/login?lang=en');
  await page.locator('#login_email').fill('editor-browser-qa@example.test');
  await page.locator('#login_password').fill('Only-A-CI-Browser-Fixture-2026!');
  await page.locator('button[type="submit"]').first().click();
  await expect(page).toHaveURL(/\/dashboard(?:\?|$)/);
  const shell=page.locator('[data-workspace]');
  await expect(shell).toHaveAttribute('data-glass-sidebar','');
  const sidebar=page.locator('[data-sidebar]');
  const header=page.locator('[data-sidebar-toggle]');
  const edge=page.locator('[data-glass-sidebar-toggle]');
  const group=sidebar.locator('[data-nav-group]>summary').first();
  const desktop=await page.evaluate(()=>matchMedia('(min-width: 64rem)').matches);
  if (desktop) {
    await expect(sidebar).toBeVisible();
    await expect(edge).toBeVisible();
    await expect(shell).toHaveCSS('padding-inline-start','266px');
    const originalLinks=await sidebar.locator('[data-workspace-link]').evaluateAll(items=>items.map(x=>x.getAttribute('href')));
    await edge.click();
    await expect(edge).toHaveAttribute('aria-expanded','false');
    await expect.poll(async()=>Math.round((await sidebar.boundingBox()).width)).toBe(72);
    await expect(group).toBeVisible();
    expect(await sidebar.evaluate(el=>el.inert)).toBe(false);
    await page.reload();
    await expect(edge).toHaveAttribute('aria-expanded','false');
    await group.click();
    await expect(edge).toHaveAttribute('aria-expanded','true');
    await expect(shell).toHaveCSS('padding-inline-start','266px');
    await expect.poll(async()=>Math.round((await sidebar.boundingBox()).width)).toBe(254);
    expect(await sidebar.locator('[data-workspace-link]').evaluateAll(items=>items.map(x=>x.getAttribute('href')))).toEqual(originalLinks);
  } else {
    await expect(sidebar).toBeHidden();
    await header.click();
    await expect(sidebar).toBeVisible();
    await expect(edge).toBeHidden();
    await expect(group).toBeVisible();
    await page.locator('[data-sidebar-close]').click();
    await expect(sidebar).toBeHidden();
    await expect(header).toBeFocused();
  }
  await page.evaluate(()=>document.documentElement.classList.remove('dark'));
  const light=await sidebar.evaluate(el=>getComputedStyle(el).color);
  await page.evaluate(()=>document.documentElement.classList.add('dark'));
  const dark=await sidebar.evaluate(el=>getComputedStyle(el).color);
  expect(light).not.toBe(dark);
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
});
