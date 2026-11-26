import { expect, test } from '@playwright/test';

test('course manager search and row actions stay usable at dashboard widths', async ({ page }, info) => {
  await page.goto('/login?lang=en');
  await page.locator('#login_email').fill('editor-browser-qa@example.test');
  await page.locator('#login_password').fill('Only-A-CI-Browser-Fixture-2026!');
  await page.locator('button[type="submit"]').first().click();
  await expect(page).toHaveURL(/\/dashboard(?:\?|$)/);

  await page.goto('/dashboard/course-builder/create?lang=en');
  const title = 'Manager browser course ' + info.project.name;
  const builder = page.locator('[data-course-builder]');
  const category = await builder.getByLabel('Category', { exact: true }).locator('option').nth(1).getAttribute('value');
  const token = await page.locator('meta[name=csrf-token]').getAttribute('content');
  const created = await page.request.post('/dashboard/course-builder', {
    form: { _token: token, title, category_id: category, delivery_mode: 'online', summary: 'Practice managing a course.' },
  });
  await expect(created).toBeOK();

  await page.goto('/dashboard/courses?lang=en');
  const table = page.locator('.learning-manager');
  await table.locator('input[name=q]').fill(title);
  await table.getByRole('button', { name: 'Search', exact: true }).click();
  await expect(table.locator('tbody tr')).toHaveCount(1);
  const trigger = table.getByRole('button', { name: 'Actions for ' + title });
  await expect(trigger).toBeVisible();
  await trigger.scrollIntoViewIfNeeded();
  await trigger.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  await trigger.click();
  const menu = page.locator('.learning-manager-menu:popover-open');
  await expect(menu).toBeVisible();
  for (const label of ['Edit', 'Preview', 'Activate', 'Assign to user', 'Delete']) {
    await expect(menu.getByText(label, { exact: true })).toBeVisible();
  }

  const menuBox = await menu.boundingBox();
  const width = await page.evaluate(() => document.documentElement.clientWidth);
  expect(menuBox.x).toBeGreaterThanOrEqual(0);
  expect(menuBox.x + menuBox.width).toBeLessThanOrEqual(width + 1);

  await page.keyboard.press('Escape');
  await expect(menu).toHaveCount(0);
  await trigger.click();
  await menu.getByRole('link', { name: 'Preview', exact: true }).click();
  await expect(page).toHaveURL(/\/dashboard\/courses\/[^/]+\/preview/);
  await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
});
