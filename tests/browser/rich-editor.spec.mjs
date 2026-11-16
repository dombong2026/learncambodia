import { test, expect } from '@playwright/test';

test.describe('Authenticated rich editor against isolated Laravel fixture', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login?lang=en');
    await page.locator('#login_email').fill('editor-browser-qa@example.test');
    await page.locator('#login_password').fill('Only-A-CI-Browser-Fixture-2026!');
    await page.locator('button[type="submit"]').first().click();
    await expect(page).toHaveURL(/\/dashboard(?:\?|$)/);
  });

  test('Course Builder saves forward, protects edits and opens one lesson editor', async ({ page }, info) => {
    await page.goto('/dashboard/course-builder/create?lang=en');
    const builder = page.locator('[data-course-builder]');
    const courseTitle = 'Builder Browser Course ' + info.project.name + ' ' + Date.now();
    await builder.getByLabel('Course title').fill(courseTitle);
    await builder.getByLabel('Category', { exact: true }).selectOption({ index: 1 });
    await builder.getByLabel('Summary', { exact: true }).fill('A short English practice course.');
    await builder.getByLabel('Full course description').fill('A guided session on introductions.');
    await builder.getByRole('button', { name: /Create draft & continue/ }).click();
    await expect(page).toHaveURL(/step=media/);

    await builder.getByLabel('Media type').selectOption('color');
    await expect(builder.getByText('Unsaved changes', { exact: true })).toBeVisible();
    page.once('dialog', dialog => dialog.dismiss());
    await builder.getByRole('link', { name: 'Back', exact: false }).filter({ hasText: '← Back' }).click();
    await expect(page).toHaveURL(/step=media/);
    await builder.getByRole('button', { name: /Save & continue/ }).click();
    await expect(page).toHaveURL(/step=curriculum/);
    await builder.getByRole('link', { name: '+ Add module' }).click();
    await builder.getByLabel('Module title').fill('Introductions');
    await builder.getByRole('button', { name: 'Save changes' }).click();
    await builder.getByRole('link', { name: '+ Add lesson' }).click();
    await builder.getByLabel('Lesson title').fill('Say hello');
    await builder.getByLabel('Lesson content').fill('Hello, my name is Samith.');
    await builder.getByRole('button', { name: 'Save changes' }).click();
    await expect(builder.getByRole('textbox', { name: 'Lesson content' })).toHaveCount(1);
    await expect(builder.getByRole('textbox', { name: 'Lesson content' })).toHaveValue('Hello, my name is Samith.');
    await builder.getByRole('button', { name: /Save & continue/ }).click();
    await expect(page).toHaveURL(/step=quizzes/);
    await builder.getByRole('link', { name: /Continue/ }).click();
    await expect(builder.getByRole('heading', { name: 'Review & Submit' })).toBeVisible();
    await expect(builder.getByRole('button', { name: 'Submit for approval' })).toBeEnabled();
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
    await expect(dialog.locator('[data-editor-preview-content] h2')).toHaveText('Structured learning');
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

  test('publication sidebar can copy the slug, upload and publish a reusable featured image', async ({ page }, info) => {
    await page.goto('/dashboard/editorial/pages/create?lang=en');
    const sidebar = page.getByRole('complementary', { name: 'Document settings' });
    const publication = sidebar.getByRole('region', { name: 'Publication' });
    await expect(publication.getByRole('button', { name: 'Save Draft', exact: true })).toBeVisible();
    await expect(publication.getByRole('button', { name: 'Publish', exact: true })).toBeVisible();
    const title = 'Browser Cover ' + info.project.name + ' ' + Date.now();
    await page.locator('[data-document-title]').fill(title);
    await sidebar.getByRole('button', { name: 'Click to copy' }).click();
    await expect(sidebar.getByLabel('URL slug')).toHaveValue(title.toLowerCase().replaceAll(' ', '-'));
    await page.locator('[data-editor-visual]').fill('Featured media for Learn Cambodia.');
    await sidebar.getByLabel('Media type').selectOption('image');
    await sidebar.getByRole('button', { name: 'Select from Media Library' }).click();
    const picker = page.getByRole('dialog', { name: 'Media Library' });
    await expect(picker).toBeVisible();
    await picker.locator('[data-media-upload]').setInputFiles({
      name: 'browser-cover.png',
      mimeType: 'image/png',
      buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGMQsisEAAEnAMIfy+WiAAAAAElFTkSuQmCC', 'base64'),
    });
    await expect(picker).toBeHidden();
    await expect(sidebar.getByText('browser-cover.png')).toBeVisible();
    await sidebar.getByLabel('Image description').fill('Sample learning graphic');
    await publication.getByRole('button', { name: 'Publish', exact: true }).click();
    await expect(page).toHaveURL(/dashboard\/editorial\/pages\/\d+\/edit/);
    await publication.getByRole('link', { name: 'View published' }).click();
    await expect(page.getByRole('heading', { name: title })).toBeVisible();
    const featured = page.getByRole('img', { name: 'Sample learning graphic' });
    await expect(featured).toBeVisible();
    await expect.poll(() => featured.evaluate(image => image.naturalWidth)).toBe(1);
  });

  test('media grid uploads, previews, renames and deletes an image', async ({ page }, info) => {
    await page.goto('/dashboard/media-library?lang=en');
    const file = 'library-' + info.project.name + '-' + Date.now() + '.png';
    await page.getByRole('button', { name: 'Upload media' }).click();
    const upload = page.getByRole('dialog', { name: 'Upload media' });
    await upload.getByLabel('Image or video').setInputFiles({
      name: file,
      mimeType: 'image/png',
      buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGMQsisEAAEnAMIfy+WiAAAAAElFTkSuQmCC', 'base64'),
    });
    await upload.getByRole('button', { name: 'Upload', exact: true }).click();
    await expect(page.getByText('Media uploaded.', { exact: true })).toBeVisible();
    const search = page.getByRole('search');
    await search.getByLabel('Title').fill(file);
    await search.getByRole('button', { name: 'Search' }).click();
    await page.getByRole('button', { name: 'Preview ' + file, exact: true }).click();
    const preview = page.getByRole('dialog', { name: file });
    await expect(preview).toBeVisible();
    await expect.poll(() => preview.getByRole('img').evaluate(image => image.naturalWidth)).toBe(1);
    await page.keyboard.press('Escape');
    await expect(preview).toBeHidden();

    const actions = page.locator('article[data-library-item]').filter({ hasText: file }).locator('summary');
    await actions.click();
    await page.getByRole('button', { name: 'Edit', exact: true }).click();
    const edit = page.getByRole('dialog', { name: 'Edit media' });
    await edit.getByLabel('Title').fill(file + ' renamed');
    await edit.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('Media updated.', { exact: true })).toBeVisible();
    await actions.click();
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    const remove = page.getByRole('dialog', { name: 'Delete media' });
    await remove.getByRole('button', { name: 'Delete media', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'No matching media' })).toBeVisible();
  });

  test('media video preview uses transparent speed and hides controls when inactive', async ({ page }, info) => {
    await page.goto('/dashboard/media-library?lang=en');
    const file = 'compact-player-' + info.project.name + '-' + Date.now() + '.webm';
    await page.getByRole('button', { name: 'Upload media' }).click();
    const upload = page.getByRole('dialog', { name: 'Upload media' });
    await upload.getByLabel('Image or video').setInputFiles({
      name: file,
      mimeType: 'video/webm',
      buffer: Buffer.from('GkXfo59ChoEBQveBAULygQRC84EIQoKEd2VibUKHgQJChYECGFOAZwEAAAAAAAJnEU2bdLpNu4tTq4QVSalmU6yBoU27i1OrhBZUrmtTrIHWTbuMU6uEElTDZ1OsggEjTbuMU6uEHFO7a1OsggJR7AEAAAAAAABZAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAVSalmsCrXsYMPQkBNgIxMYXZmNjEuNy4xMDNXQYxMYXZmNjEuNy4xMDNEiYhAp3AAAAAAABZUrmvIrgEAAAAAAAA/14EBc8WI6p6I8dTtfZOcgQAitZyDdW5kiIEAhoVWX1ZQOIOBASPjg4Q7msoA4JCwgSC6gSCagQJVsIRVuYEBElTDZ/tzc59jwIBnyJlFo4dFTkNPREVSRIeMTGF2ZjYxLjcuMTAzc3PWY8CLY8WI6p6I8dTtfZNnyKFFo4dFTkNPREVSRIeUTGF2YzYxLjE5LjEwMSBsaWJ2cHhnyKFFo4hEVVJBVElPTkSHkzAwOjAwOjAzLjAwMDAwMDAwMAAfQ7Z1QKjngQCjv4EAAIDQAgCdASogACAAAEcIhYWImYSIAgICdaoD+AIIISmecP79IUX/8Bf/gL/8Bf/+Av//fAn3gT7wJ/tsAKOwgQPoANEBAAEQEAAYAB5X9AwAQQ4A/v0hRf/wF/+Av/wF//4C//98CfeBPvAn+2wAo7CBB9AA0QEAARAQABgAHlf0DABBDgD+/SFF//AX/4C//AX//gL//3wJ94E+8Cf7bAAcU7trkbuPs4EAt4r3gQHxggGj8IED', 'base64'),
    });
    await upload.getByRole('button', { name: 'Upload', exact: true }).click();
    await page.getByRole('search').getByLabel('Title').fill(file);
    await page.getByRole('search').getByRole('button', { name: 'Search' }).click();
    await page.getByRole('button', { name: 'Preview ' + file, exact: true }).click();

    const dialog = page.getByRole('dialog', { name: file });
    const player = dialog.locator('[data-compact-video]');
    const controls = player.locator('[data-video-controls]');
    const speed = dialog.getByRole('button', { name: 'Speed', exact: true });
    await player.dispatchEvent('pointermove', { pointerType: 'mouse' });
    await expect(controls).toHaveCSS('opacity', '1');
    await expect(controls).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await expect(speed).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await speed.click();
    await dialog.getByRole('button', { name: '1.5×', exact: true }).click();
    await expect.poll(() => player.locator('video').evaluate(video => video.playbackRate)).toBe(1.5);

    const volumeButton = dialog.getByRole('button', { name: 'Volume', exact: true });
    await volumeButton.scrollIntoViewIfNeeded();
    const volumeButtonBox = await volumeButton.boundingBox();
    await page.mouse.move(volumeButtonBox.x + volumeButtonBox.width / 2, volumeButtonBox.y + volumeButtonBox.height / 2);
    await volumeButton.click();
    await expect(volumeButton).toHaveAttribute('aria-expanded', 'true');
    const volume = dialog.getByRole('slider', { name: 'Volume level' });
    if (await volume.isVisible()) {
      const bounds = await volume.boundingBox();
      expect(bounds.height).toBeGreaterThan(bounds.width);
      await page.mouse.click(bounds.x + bounds.width / 2, bounds.y + bounds.height * .65);
      const adjustedVolume = await player.locator('video').evaluate(video => video.volume);
      expect(adjustedVolume).toBeGreaterThan(0.2);
      expect(adjustedVolume).toBeLessThan(0.5);
      await volume.fill('35');
      await expect.poll(() => player.locator('video').evaluate(video => Math.round(video.volume * 100))).toBe(35);
      await expect(volume).toHaveAttribute('aria-valuetext', '35%');
      await dialog.getByRole('button', { name: 'Mute', exact: true }).click();
      await expect(volume).toHaveValue('0');
      await dialog.getByRole('button', { name: 'Unmute', exact: true }).click();
      await expect(volume).toHaveValue('35');
    } else {
      await expect(dialog.getByText('Use your device volume controls.')).toBeVisible();
    }
    await volumeButton.click();
    await expect(volumeButton).toHaveAttribute('aria-expanded', 'false');

    const settings = dialog.getByRole('button', { name: 'Settings', exact: true });
    await settings.click();
    const loop = dialog.getByRole('checkbox', { name: 'Loop video' });
    await loop.check();
    await expect.poll(() => player.locator('video').evaluate(video => video.loop)).toBe(true);
    await loop.uncheck();
    await expect.poll(() => player.locator('video').evaluate(video => video.loop)).toBe(false);
    await settings.click();
    await expect(settings).toHaveAttribute('aria-expanded', 'false');
    await expect(player.locator('input[type=file], [download]')).toHaveCount(0);
    await expect(player.getByRole('button', { name: /upload|download/i })).toHaveCount(0);

    await player.dispatchEvent('pointerleave', { pointerType: 'mouse' });
    await expect(controls).toHaveCSS('opacity', '0', { timeout: 500 });
    await player.dispatchEvent('pointermove', { pointerType: 'mouse' });
    await expect(controls).toHaveCSS('opacity', '1');
    await expect(controls).toHaveCSS('opacity', '0', { timeout: 3000 });
    await dialog.getByRole('button', { name: 'Close preview' }).click();
    await expect(dialog).toBeHidden();
    await page.getByRole('button', { name: 'Preview ' + file, exact: true }).click();
    await expect(speed).toHaveAttribute('title', 'Speed (1×)');
    await expect.poll(() => player.locator('video').evaluate(video => video.playbackRate)).toBe(1);
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
