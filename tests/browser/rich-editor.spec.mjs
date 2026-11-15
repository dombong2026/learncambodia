import { test, expect } from '@playwright/test';

test.describe('Authenticated rich editor against isolated Laravel fixture', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login?lang=en');
    await page.locator('#login_email').fill('editor-browser-qa@example.test');
    await page.locator('#login_password').fill('Only-A-CI-Browser-Fixture-2026!');
    await page.locator('button[type="submit"]').first().click();
    await expect(page).toHaveURL(/\/dashboard(?:\?|$)/);
  });

  test('six forms show the usable editor, mode switch and contextual sidebar', async ({ page }) => {
    for (const kind of ['pages', 'posts', 'policies', 'faqs', 'help-articles']) {
      await page.goto('/dashboard/editorial/' + kind + '/create?lang=en');
      const editor = page.locator('[data-rich-editor]');
      await expect(editor).toBeVisible();
      await expect(editor.locator('[data-editor-enhanced]')).toBeVisible();
      await expect(editor.locator('[data-editor-visual]')).toBeVisible();
      await expect(editor.locator('[data-editor-source]')).toBeHidden();
      await expect(editor.locator('[data-editor-mode="html"]')).toBeVisible();
      await expect(page.getByRole('complementary', { name: 'Document settings' })).toBeVisible();
      await expect(page.locator('[data-seo-preview-title]')).toBeVisible();
    }
    await page.goto('/support/tickets/create?lang=en');
    await expect(page.locator('[data-editor-visual]')).toBeVisible();
    await expect(page.getByRole('complementary', { name: 'Ticket settings' })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Support shortcuts' })).toBeVisible();
  });

  test('actual Text/HTML editing, safe preview, word count, fullscreen and save', async ({ page }, info) => {
    await page.goto('/dashboard/editorial/posts/create?lang=en');
    const editor = page.locator('[data-rich-editor]');
    await editor.locator('[data-editor-visual]').fill('First draft');
    await expect(editor.locator('[data-editor-count]')).toContainText('2 words');

    await editor.locator('[data-editor-mode="html"]').click();
    const source = editor.locator('[data-editor-source]');
    await expect(source).toBeVisible();
    await source.fill('<h2>Structured learning</h2><p>A <strong>rich</strong> article.</p><script>window.__unsafe=1</script>');
    await editor.locator('[data-editor-mode="text"]').click();
    await expect(editor.locator('[data-editor-visual] h2')).toContainText('Structured learning');
    await expect(editor.locator('[data-editor-visual] strong')).toContainText('rich');
    await expect(editor.locator('[data-editor-visual] script')).toHaveCount(0);

    await editor.locator('[data-editor-preview]').click();
    const dialog = editor.locator('[data-editor-preview-dialog]');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('h2')).toContainText('Structured learning');
    await editor.locator('[data-editor-preview-close]').click();
    await expect(dialog).toBeHidden();

    const expand = editor.locator('[data-editor-expand]');
    await expand.click();
    await expect(expand).toHaveAttribute('aria-pressed', 'true');
    await expect(editor).toHaveClass(/rich-editor-expanded/);
    await page.keyboard.press('Escape');
    await expect(editor).not.toHaveClass(/rich-editor-expanded/);

    const title = 'Rich Browser Draft ' + info.project.name + ' ' + Date.now();
    await page.locator('[data-document-title]').fill(title);
    await page.locator('[data-seo-title]').fill('Reviewable SEO Title');
    await expect(page.locator('[data-seo-preview-title]')).toHaveText('Reviewable SEO Title');
    await page.locator('button[type="submit"][form="editorial-form"]').click();
    await expect(page).toHaveURL(/\/dashboard\/editorial\/posts\/\d+\/edit/);
    await expect(page.locator('[data-editor-visual] h2')).toContainText('Structured learning');
    await page.getByRole('link', { name: 'Preview saved document' }).click();
    await expect(page.getByRole('heading', { name: /Preview:/ })).toBeVisible();
    await expect(page.locator('.rich-public-content strong')).toContainText('rich');
    await expect(page.locator('.rich-public-content script')).toHaveCount(0);
  });

  test('ticket rich content is saved to a private conversation', async ({ page }, info) => {
    await page.goto('/support/tickets/create?lang=en');
    await page.locator('#support-subject').fill('Editor QA ticket ' + info.project.name + ' ' + Date.now());
    await page.locator('[data-editor-visual]').fill('Please review this support message.');
    await page.locator('#support-priority').selectOption('high');
    await page.locator('button[form="support-create-form"]').click();
    await expect(page).toHaveURL(/\/support\/tickets\/\d+/);
    await expect(page.locator('[data-support-feed]')).toContainText('Please review this support message.');
    await expect(page.locator('#ticket-priority')).toHaveValue('high');
  });
});
