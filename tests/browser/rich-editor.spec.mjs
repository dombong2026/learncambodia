import { test, expect } from '@playwright/test';

test.describe('Authenticated rich editor against isolated Laravel fixture', () => {
  test.beforeEach(async ({ page }, info) => {
    const scenario = [
      'System Settings opens every administrator page below Analytics',
      'Classic and Sunrise homepage previews retain independent responsive layouts',
    ].includes(info.title)
      ? 'settings'
      : info.title === 'ticket rich content is saved to a private conversation' ? 'ticket' : null;
    const email = scenario
      ? `editor-${scenario}-${info.project.name}@example.test`
      : 'editor-browser-qa@example.test';
    await page.goto('/login?lang=en');
    await page.locator('#login_email').fill(email);
    await page.locator('#login_password').fill('Only-A-CI-Browser-Fixture-2026!');
    await page.locator('button[type="submit"]').first().click();
    await expect(page).toHaveURL(/\/dashboard(?:\?|$)/);
  });

  test('System Settings opens every administrator page below Analytics', async ({ page }) => {

    test.setTimeout(90_000);

    const nav = page.getByRole('navigation', { name: 'Account navigation' });

    const settings = nav.locator('[data-nav-group]').filter({
      has: page.locator('summary').getByText('System Settings', { exact: true }),
    });

    // Cloud Storage is last so the original protected
    // provider configuration journey remains intact.
    const pages = [
      ['Branding', '#platform-branding-form'],
      ['Security', '.system-settings-row:first-of-type'],
      ['Social Auth', '#social-providers'],
      ['SMTP', '#smtp-unlock-heading, #smtp-heading'],
      ['MCP Management', '[data-mcp-manager]'],
      ['OpenAI API', '#openai-settings-heading'],
      ['Player Settings', '#audio-settings-heading'],
      ['Cloud Storage', '#storage-providers-title'],
    ];

    for (const [label, selector] of pages) {
      await expect(page.locator('[data-glass-sidebar]')).toBeVisible();

      const desktop = await page.evaluate(() =>
        matchMedia('(min-width: 64rem)').matches
      );

      const toggle = desktop
        ? page.locator('[data-glass-sidebar-toggle]')
        : page.locator('[data-sidebar-toggle]');

      await expect(toggle).toBeVisible();

      if (await toggle.getAttribute('aria-expanded') === 'false') {
        await toggle.click();
      }

      await expect(settings.locator(
        'xpath=preceding-sibling::details[1]/summary'
      )).toHaveText('Analytics');

      if (await settings.getAttribute('open') === null) {
        await settings.locator('summary').click();
      }

      await expect(settings.locator('a[data-workspace-link]')).toHaveText([
        'Branding', 'Security', 'Social Auth', 'SMTP',
        'Cloud Storage', 'MCP Management', 'OpenAI API', 'Player Settings',
      ]);

      const link = settings.getByRole('link', { name: label, exact: true });
      await expect(link).toBeVisible();

      const destination = new URL(await link.getAttribute('href'), page.url());

      if (!desktop) {
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');

        // Scroll the existing navigation container, not the
        // document or an unrelated workspace element.
        await link.evaluate(element => {
          const nav = element.closest('[data-workspace-nav]');

          if (!nav) throw new Error('Workspace navigation container missing');

          const bounds = nav.getBoundingClientRect();
          const target = element.getBoundingClientRect();
          const top = Math.max(0, bounds.top);
          const bottom = Math.min(window.innerHeight, bounds.bottom);

          if (bottom <= top) {
            throw new Error('Navigation viewport is not visible');
          }

          const center = (top + bottom) / 2;
          const targetCenter = (target.top + target.bottom) / 2;

          nav.scrollTo({
            top: nav.scrollTop + targetCenter - center,
            behavior: 'instant',
          });
        });

        // Wait until actual hit-testing reaches the link.
        // Never bypass an intercepted pointer interaction.
        await expect.poll(async () => {
          return await link.evaluate(element => {
            const rect = element.getBoundingClientRect();
            const x = rect.left + rect.width / 2;
            const y = rect.top + rect.height / 2;
            const hit = document.elementFromPoint(x, y);

            return Boolean(hit && element.contains(hit));
          });
        }, {
          timeout: 10000,
          message: 'Mobile sidebar link must receive pointer events',
        }).toBe(true);
      }

      await link.click();

      await expect.poll(() => new URL(page.url()).pathname)
        .toBe(destination.pathname);

      await expect(page.getByRole('heading', {
        name: label,
        exact: true,
      })).toBeVisible();

      await expect(page.locator('[data-workspace-content]').locator(selector))
        .toBeVisible();

      if (label === 'MCP Management') {
        const viewport = page.viewportSize();

        expect(viewport).not.toBeNull();

        // The accessible Actions label must not escape
        // the table scroll container and widen the page.
        await expect.poll(async () => {
          return page.evaluate(expectedWidth => {
            const root = document.documentElement;

            return window.innerWidth <= expectedWidth + 1
              && root.scrollWidth <= root.clientWidth + 1;
          }, viewport.width);
        }, {
          timeout: 10000,
          message: 'MCP Management must not expand the document viewport',
        }).toBe(true);

        const tools = page.locator(
          'section[aria-labelledby="mcp-tools-heading"]'
        );

        const tableScroll = tools.locator('.mcp-table-scroll');

        await expect(tableScroll).toBeVisible();
        await expect(tableScroll).toHaveCSS('position', 'relative');
        await expect(tableScroll).toHaveCSS('overflow-x', 'auto');

        await expect(tools.getByRole('columnheader', {
          name: 'Actions',
          exact: true,
        })).toHaveCount(1);

        const contained = await tableScroll.evaluate(container => {
          const label = container.querySelector('.mcp-visually-hidden');

          return Boolean(label && label.offsetParent === container);
        });

        expect(contained).toBe(true);

        // The table must remain horizontally scrollable
        // at mobile and tablet widths.
        if (viewport.width <= 768) {
          const scrollDistance = await tableScroll.evaluate(container => {
            const original = container.scrollLeft;

            container.scrollTo({
              left: container.scrollWidth,
              behavior: 'instant',
            });

            const distance = container.scrollLeft;

            container.scrollTo({
              left: original,
              behavior: 'instant',
            });

            return distance;
          });

          expect(scrollDistance).toBeGreaterThan(0);
        }
      }

      const sidebar = page.locator('[data-sidebar]');

      if (!desktop) {
        // Navigation deliberately closes the mobile drawer.
        // Verify that contract before reopening it.
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(sidebar).toHaveJSProperty('inert', true);

        await toggle.click();

        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(sidebar).toHaveJSProperty('inert', false);
        await expect(settings).toHaveAttribute('open', '');
      }

      const current = settings.locator('a[aria-current="page"]');

      await expect(current).toHaveCount(1);
      await expect(current).toHaveText(label);
      await expect(current).toBeVisible();

      if (!desktop) {
        await page.locator('[data-sidebar-close]').click();

        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(sidebar).toHaveJSProperty('inert', true);
      }
    }

    const cssUrl = await page.locator('link[href*="/css/system-settings.css"]').getAttribute('href');
    const stylesheet = await page.request.get(cssUrl);
    expect(stylesheet.ok()).toBeTruthy();

    const table = page.getByRole('table', { name: 'Storage providers' });
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await table.getByRole('link', { name: 'Configure Amazon S3' }).click();
    await page.getByLabel('Current password').fill('Only-A-CI-Browser-Fixture-2026!');
    const confirmation = page.waitForResponse(response =>
      new URL(response.url()).pathname === '/security/confirm-password' && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Confirm password' }).click();
    expect((await confirmation).status(), 'Password confirmation POST must redirect, not be throttled').toBe(302);
    const s3 = page.getByRole('dialog', { name: 'Configure Amazon S3' });
    await expect(s3).toBeVisible();
    await expect(s3.getByLabel('AWS region')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(table).toBeVisible();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await table.getByRole('link', { name: 'Configure Cloudflare R2' }).click();
    const r2 = page.getByRole('dialog', { name: 'Configure Cloudflare R2' });
    await expect(r2.getByLabel('S3 API endpoint')).toBeVisible();
    await r2.getByRole('link', { name: 'Close configuration' }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
  });


  test('Classic and Sunrise homepage previews retain independent responsive layouts', async ({ page }) => {
    test.setTimeout(90_000);

    for (const [width, classicColumns, sunriseColumns] of [
      [390,1,1], [820,1,2], [1440,2,4],
    ]) {
      await page.setViewportSize({ width, height: 900 });

      const classic = await page.goto('/dashboard/appearance/home/preview/classic?lang=en');
      expect(classic.status()).toBe(200);

      await expect(page.locator('.lc-home')).toBeVisible();
      await expect(page.locator('[data-home-levels] a')).toHaveCount(6);

      const classicSection = page.locator('#home-cefr');

      expect(await classicSection.evaluate(el =>
        getComputedStyle(el).gridTemplateColumns.trim().split(/\s+/).length
      )).toBe(classicColumns);

      const sunrise = await page.goto('/dashboard/appearance/home/preview/sunrise?lang=en');
      expect(sunrise.status()).toBe(200);

      const home = page.locator('[data-home-layout="sunrise"]');

      await expect(home).toBeVisible();
      await expect(home.locator('.sr-level')).toHaveCount(6);
      await expect(home.locator('.sr-stats > a')).toHaveCount(4);

      const steps = home.locator('.sr-tile').filter({
        has: page.locator('.sr-step'),
      });

      await expect(steps).toHaveCount(4);

      const grid = steps.first().locator('..');

      expect(await grid.evaluate(el =>
        getComputedStyle(el).gridTemplateColumns.trim().split(/\s+/).length
      )).toBe(sunriseColumns);

      for (const [index, step] of (await steps.all()).entries()) {
        await expect(step.locator('.sr-step')).toHaveText(
          String(index + 1).padStart(2, '0')
        );
        await expect(step.locator('h3')).toBeVisible();
        await expect(step).toHaveAttribute('href', /.+/);
      }

      const tiles = home.locator('.sr-tile').filter({
        has: page.locator('.sr-icon'),
      });

      await expect(tiles).toHaveCount(4);

      for (const tile of await tiles.all()) {
        await expect(tile.locator('.sr-icon svg')).toBeVisible();
        await expect(tile.locator('h3')).toBeVisible();
      }

      for (const [index, level] of ['A1','A2','B1','B2','C1','C2'].entries()) {
        const link = home.locator('.sr-level').nth(index);
        const url = new URL(await link.getAttribute('href'), page.url());

        expect(url.pathname).toBe('/cefr');
        expect(url.hash).toBe('#' + level);
      }

      const tile = tiles.first();

      for (const scheme of ['light', 'dark']) {
        await page.evaluate(scheme =>
          document.documentElement.classList.toggle('dark', scheme === 'dark'),
          scheme
        );

        if (scheme === 'light') {
          const background = await tile.evaluate(el =>
            getComputedStyle(el).backgroundColor
          );
          expect(background).not.toBe('rgba(0, 0, 0, 0)');
        } else {
          await expect(tile).toHaveCSS('background-color', 'rgb(34, 51, 43)');
        }
      }

      const dimensions = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        scroll: document.documentElement.scrollWidth,
      }));

      expect(dimensions.scroll).toBeLessThanOrEqual(dimensions.viewport + 2);
    }
  });

  test('Course Builder saves forward, protects edits and opens one lesson editor', async ({ page }, info) => {
    // This journey traverses several server-rendered steps and uploads media.
    // Keep individual assertion timeouts unchanged; budget the complete journey.
    test.setTimeout(90_000);
    await page.goto('/dashboard/course-builder/create?lang=en');
    const builder = page.locator('[data-course-builder]');
    const courseTitle = 'Builder Browser Course ' + info.project.name + ' ' + Date.now();
    await builder.getByLabel('Course title').fill(courseTitle);
    await builder.getByLabel('Category', { exact: true }).selectOption({ index: 1 });
    await builder.getByLabel('Summary', { exact: true }).fill('A short English practice course.');
    const editor = builder.getByRole('region', { name: 'Rich text editor' });
    const visual = editor.getByRole('textbox', { name: 'Full course description' });
    await visual.fill('A guided session on introductions.');
    await expect(builder.getByText('Unsaved changes', { exact: true })).toBeVisible();
    const bold = editor.getByRole('button', { name: 'Bold', exact: true });
    await expect(bold).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await editor.getByRole('button', { name: 'HTML', exact: true }).click();
    const source = editor.getByRole('textbox', { name: 'Edit safe HTML source' });
    await expect(source).toHaveValue(/A guided session on introductions/);
    await source.fill('<h2>Introductions</h2><p>Practise greetings <strong>aloud</strong>.</p><table><tbody><tr><td>Welcome</td><td>Goodbye</td></tr></tbody></table><script>window.__unsafe=1</script>');
    await editor.getByRole('button', { name: 'Text', exact: true }).click();
    await expect(visual.locator('strong')).toHaveText('aloud');
    await expect(visual.locator('script')).toHaveCount(0);
    await visual.getByRole('cell', { name: 'Welcome' }).click();
    await editor.getByRole('button', { name: 'Add row', exact: true }).click();
    await expect(visual.getByRole('row')).toHaveCount(2);
    await visual.getByRole('cell', { name: 'Welcome' }).click();
    await editor.getByRole('button', { name: 'Remove table', exact: true }).click();
    await expect(visual.getByRole('table')).toHaveCount(0);
    await editor.getByRole('button', { name: 'Preview', exact: true }).click();
    const preview = editor.getByRole('dialog', { name: 'Live content preview' });
    await expect(preview.getByRole('heading', { name: 'Introductions', exact: true })).toBeVisible();
    await preview.getByRole('button', { name: 'Close preview' }).click();
    await builder.getByRole('button', { name: /Create draft & continue/ }).click();
    await expect(page).toHaveURL(/step=media/);

    await expect(builder).toHaveAttribute('data-builder-ready', 'true');
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
    const lessonEditor = builder.locator('[data-editor-context="lesson"]');
    const lessonContent = lessonEditor.getByRole('textbox', { name: 'Lesson content' });
    await lessonContent.fill('Hello, my name is Samith.');
    await lessonEditor.getByRole('button', { name: 'YouTube', exact: true }).click();
    let insert = lessonEditor.getByRole('dialog', { name: 'Insert YouTube video' });
    await insert.getByLabel('URL or video ID').fill('https://youtu.be/dQw4w9WgXcQ');
    await insert.getByLabel('Label', { exact: true }).fill('Lesson video');
    await insert.getByRole('button', { name: 'Insert', exact: true }).click();
    await expect(lessonContent.locator('figure[data-youtube]')).toHaveText('Lesson video');

    await lessonEditor.getByRole('button', { name: 'Code', exact: true }).click();
    insert = lessonEditor.getByRole('dialog', { name: 'Insert code block' });
    await insert.getByLabel('Language').selectOption('python');
    await insert.getByLabel('Code', { exact: true }).fill('print("Hello!")');
    await insert.getByRole('button', { name: 'Insert', exact: true }).click();

    await lessonEditor.getByRole('button', { name: 'Resource button', exact: true }).click();
    insert = lessonEditor.getByRole('dialog', { name: 'Insert resource button' });
    await expect(insert).toBeVisible();
    const modal = await insert.evaluate(dialog => {
      const { x, y, width, height } = dialog.getBoundingClientRect();
      return { x, y, width, height, viewportWidth: window.innerWidth, viewportHeight: window.innerHeight };
    });
    expect(Math.abs(modal.x + modal.width / 2 - modal.viewportWidth / 2)).toBeLessThan(2);
    expect(Math.abs(modal.y + modal.height / 2 - modal.viewportHeight / 2)).toBeLessThan(2);
    expect(modal.x).toBeGreaterThanOrEqual(15);
    expect(modal.y).toBeGreaterThanOrEqual(15);
    await insert.getByLabel('Label', { exact: true }).fill('Study guide');
    await insert.getByLabel('URL or video ID').fill('/reference');
    await insert.getByRole('button', { name: 'Insert', exact: true }).click();
    await expect(lessonContent.locator('a[data-resource]')).toHaveText('Study guide');

    await lessonEditor.getByLabel('Upload lesson file').setInputFiles({
      name: 'lesson-handout.pdf',
      mimeType: 'application/pdf',
      buffer: Buffer.from('%PDF-1.4\n%%EOF'),
    });
    await expect(lessonContent.getByRole('link', { name: 'lesson-handout.pdf' })).toBeVisible();
    await lessonEditor.getByRole('button', { name: 'HTML', exact: true }).click();
    const lessonSource = lessonEditor.getByRole('textbox', { name: 'Edit safe HTML source' });
    await expect(lessonSource).toHaveValue(/data-language="python"/);
    const originalLesson = await lessonSource.inputValue();
    // Check the player and its saved HTML independently of a platform's media codecs.
    await page.route('**/editor-media/9999999', route => route.abort());
    await lessonSource.fill(originalLesson + '<figure><video src="/editor-media/9999999" controls></video><figcaption>Uploaded example.mp4</figcaption></figure>'
      + '<figure><video src="/editor-media/9999999" controls></video><figcaption>Test playback</figcaption></figure>');
    await lessonEditor.getByRole('button', { name: 'Text', exact: true }).click();
    await expect(lessonContent).not.toContainText('Uploaded example.mp4');
    await expect(lessonContent).toContainText('Test playback');
    const player = lessonContent.locator('lesson-media-player').last();
    await expect(player).toHaveAttribute('data-kind', 'video');
    await expect(player.locator('video')).not.toHaveAttribute('controls');
    await expect(player.getByRole('button', { name: 'Playback settings' })).toBeVisible();
    await expect(player.locator('.overlay')).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    const fullScreen = player.getByRole('button', { name: 'Full screen', exact: true });
    await expect(fullScreen.locator('svg')).toHaveCount(1);
    await fullScreen.click();
    const expanded = player.getByRole('dialog', { name: 'Lesson media full screen' });
    await expect(expanded).toBeVisible();
    expect(await page.evaluate(() => document.fullscreenElement)).toBeNull();
    await expect(expanded.getByRole('button', { name: 'Exit full screen' }).locator('svg')).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(expanded).toBeHidden();
    await expect(fullScreen).toBeFocused();
    await lessonEditor.getByRole('button', { name: 'HTML', exact: true }).click();
    await expect(lessonSource).toHaveValue(/<video src="\/editor-media\/9999999"/);
    await expect(lessonSource).not.toHaveValue(/lesson-media-player|<button|<svg|Uploaded example\.mp4/);
    await lessonSource.fill(originalLesson);
    await lessonEditor.getByRole('button', { name: 'Text', exact: true }).click();
    await builder.getByRole('button', { name: 'Save changes' }).click();
    await expect(builder.getByRole('textbox', { name: 'Lesson content' })).toHaveCount(1);
    await expect(lessonContent).toContainText('Hello, my name is Samith.');
    await expect(lessonContent.locator('code[data-language="python"]')).toHaveText('print("Hello!")');
    await expect(lessonContent.getByRole('link', { name: 'lesson-handout.pdf' })).toBeVisible();
    await lessonContent.locator('figure[data-youtube]').click();
    await lessonEditor.getByRole('button', { name: 'Remove element', exact: true }).click();
    await expect(lessonContent.locator('figure[data-youtube]')).toHaveCount(0);
    await builder.getByRole('button', { name: /Save & continue/ }).click();
    await expect(page).toHaveURL(/step=quizzes/);
    await expect(builder).toHaveAttribute('data-builder-ready', 'true');
    await builder.getByRole('link', { name: /Continue/ }).click();
    await expect(page).toHaveURL(/step=review/);
    await expect(builder.getByRole('heading', { name: 'Review & Submit' })).toBeVisible();
    await expect(builder.locator('.course-description-content strong')).toHaveText('aloud');
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
    await expect(page).toHaveURL(/\/dashboard\/editorial\/posts\/\d{6}-[a-z0-9-]+\/edit(?:\?|$)/);
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
    await expect(page).toHaveURL(/\/dashboard\/editorial\/pages\/\d{6}-[a-z0-9-]+\/edit(?:\?|$)/);
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
    // Upload, search, preview, playback settings and the real idle period share this budget.
    test.setTimeout(60_000);
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
    // A synthetic pointermove does not move the browser's real mouse. The
    // auto-hide timer can expire during the CSS assertions on slower WebKit.
    // Wake controls with genuine pointer input immediately before interaction.
    // Keep normal Playwright hit-testing: no force click or synthetic click.
    const clickActiveControl = async button => {
      await button.scrollIntoViewIfNeeded();
      const bounds = await button.boundingBox();
      expect(bounds).not.toBeNull();
      const x = bounds.x + bounds.width / 2;
      const y = bounds.y + bounds.height / 2;
      await page.mouse.move(x - 2, y);
      await page.mouse.move(x, y);
      await button.click();
    };
    await clickActiveControl(speed);
    await dialog.getByRole('button', { name: '1.5×', exact: true }).click();
    await expect.poll(() => player.locator('video').evaluate(video => video.playbackRate)).toBe(1.5);

    const volumeButton = dialog.getByRole('button', { name: 'Volume', exact: true });
    await clickActiveControl(volumeButton);
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
    await clickActiveControl(volumeButton);
    await expect(volumeButton).toHaveAttribute('aria-expanded', 'false');

    const settings = dialog.getByRole('button', { name: 'Settings', exact: true });
    await clickActiveControl(settings);
    const loop = dialog.getByRole('checkbox', { name: 'Loop video' });
    await loop.check();
    await expect.poll(() => player.locator('video').evaluate(video => video.loop)).toBe(true);
    await loop.uncheck();
    await expect.poll(() => player.locator('video').evaluate(video => video.loop)).toBe(false);
    await clickActiveControl(settings);
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
    const submitted = page.waitForResponse(response =>
      new URL(response.url()).pathname === '/support/tickets' && response.request().method() === 'POST');
    await page.locator('button[form="support-create-form"]').click();
    expect((await submitted).status(), 'Ticket creation POST must redirect, not be throttled').toBe(302);
    await expect(page).toHaveURL(/\/support\/tickets\/\d+/);
    await expect(page.locator('[data-support-feed]')).toContainText('Please review this support message.');
    await expect(page.locator('#ticket-priority')).toHaveValue('high');
  });
});
