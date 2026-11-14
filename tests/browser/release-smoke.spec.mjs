import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';

const pages = [
  ['home', '/'],
  ['courses', '/courses'],
  ['categories', '/categories'],
  ['instructors', '/instructors'],
  ['blog', '/articles'],
  ['authors', '/authors'],
  ['about', '/information/about'],
  ['contact', '/information/contact'],
  ['information-faq', '/information/faq'],
  ['information-privacy', '/information/privacy'],
  ['cefr', '/cefr'],
  ['library', '/library'],
  ['reading', '/reading'],
  ['exam-catalog', '/exam-catalog'],
  ['recommendations', '/recommendations'],
  ['leaderboard', '/leaderboard'],
  ['course-watch', '/course-watch'],
  ['upload-studio', '/upload-studio'],
];
const screenshots = new Set(['home', 'categories', 'library', 'about', 'exam-catalog', 'information-faq']);

test.describe('Actual rendered public pages on isolated QA server', () => {
  for (const [name, path] of pages) {
    test(name + ' responds without internal errors and fits viewport', async ({ page }, info) => {
      const errors = [];
      page.on('pageerror', error => errors.push(String(error.message)));
      const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
      expect(response?.status(), name + ': HTTP status').toBe(200);
      await expect(page.locator('#main-content')).toBeVisible();
      await expect(page.locator('a[aria-label="Learn Cambodia home"]')).toBeVisible();
      const width = await page.evaluate(() => ({
        scroll: document.documentElement.scrollWidth,
        viewport: document.documentElement.clientWidth,
      }));
      if (width.scroll > width.viewport + 2) {
        const offenders = await page.evaluate(() => [...document.querySelectorAll('body *')]
          .map(el => ({ el, bounds: el.getBoundingClientRect() }))
          .filter(x => {
            if (x.bounds.right <= document.documentElement.clientWidth + 2 || x.bounds.width <= 0
              || getComputedStyle(x.el).position === 'fixed') return false;
            for (let parent = x.el.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
              if (['hidden', 'clip', 'scroll', 'auto'].includes(getComputedStyle(parent).overflowX)) return false;
            }
            return true;
          })
          .slice(0, 24)
          .map(x => ({ tag: x.el.tagName, class: String(x.el.className).slice(0, 180),
            text: String(x.el.textContent).trim().slice(0, 80), right: Math.round(x.bounds.right),
            width: Math.round(x.bounds.width) })));
        console.log('OVERFLOW_DIAGNOSTICS ' + name + ' ' + JSON.stringify(offenders));
      }
      expect(width.scroll, name + ': horizontal overflow').toBeLessThanOrEqual(width.viewport + 2);
      expect(errors, name + ': browser JS exceptions').toEqual([]);
      if (screenshots.has(name)) {
        const dir = join('test-results', 'screenshots');
        mkdirSync(dir, { recursive: true });
        await page.screenshot({ path: join(dir, info.project.name + '-' + name + '.png'), fullPage: info.project.name.includes('mobile') ? false : true });
      }
    });
  }
});

test('language switcher changes HTML locale from EN to Khmer and Chinese', async ({ page }) => {
  await page.goto('/');
  const toggle = page.locator('button[aria-controls="locale-menu"]');
  await toggle.click();
  await expect(page.locator('#locale-menu')).toBeVisible();
  await page.locator('#locale-menu button[name="locale"][value="km"]').click();
  await expect(page.locator('html')).toHaveAttribute('lang', 'km');
  await page.locator('button[aria-controls="locale-menu"]').click();
  await page.locator('#locale-menu button[name="locale"][value="zh"]').click();
  await expect(page.locator('html')).toHaveAttribute('lang', 'zh');
});

test('login/password toggle is interactive and student registration is reachable', async ({ page }) => {
  await page.goto('/login');
  await expect(page.locator('input[type="email"]')).toBeVisible();
  const pwd = page.locator('#login_password');
  await expect(pwd).toHaveAttribute('type', 'password');
  await page.locator('[data-password-toggle]').click();
  await expect(pwd).toHaveAttribute('type', 'text');
  const response = await page.goto('/register');
  expect(response?.status()).toBe(200);
});

test('guests cannot view private dashboard routes', async ({ page }) => {
  for (const url of ['/dashboard', '/account', '/instructor', '/developer']) {
    await page.goto(url);
    await expect(page).toHaveURL(/\/login(?:[?#]|$)/);
  }
});

test('installer and private server files do not appear publicly', async ({ request }) => {
  for (const path of ['/install', '/.env', '/vendor/autoload.php', '/storage/app/private/learncambodia.installed']) {
    const response = await request.get(path);
    if (path === '/install' || path === '/.env' || path === '/vendor/autoload.php') {
      expect(response.status(), path + ' should be absent').toBe(404);
    } else {
      expect([403,404], path + ' must be denied').toContain(response.status());
    }
  }
});


test('locale remains in the URL while switching pages, not redirected to home', async ({ page }) => {
  await page.goto('/courses?lang=en');
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  await page.locator('button[aria-controls="locale-menu"]').click();
  await page.locator('#locale-menu button[name="locale"][value="km"]').click();
  await expect(page).toHaveURL(/\/courses\?lang=km/);
  await expect(page.locator('html')).toHaveAttribute('lang', 'km');
  await page.locator('a[href*="/articles"]').first().click();
  await expect(page).toHaveURL(/\/articles\?lang=km/);
});

test('auth UI uses inline floating labels, recovery link and modal home icon', async ({ page }) => {
  await page.goto('/login?lang=en');
  await expect(page.locator('[data-auth-body] .auth-modal')).toBeVisible();
  await expect(page.locator('label[for="login_email"]')).toBeVisible();
  await page.locator('#login_email').fill('student@example.test');
  await expect(page.locator('label[for="login_email"]')).toBeVisible();
  await expect(page.locator('[data-auth-body] [name="locale"]')).toHaveCount(0);
  await expect(page.locator('[data-auth-body] a[aria-label*="home" i]')).toHaveCount(1);
  await page.locator('a[href*="/forgot-password"]').click();
  await expect(page).toHaveURL(/\/forgot-password/);
  await expect(page.locator('input[name="email"]')).toBeVisible();
});


test('auth headings are centered and Home is in the top-left on all guest auth screens', async ({ page }) => {
  for (const [url, copy] of [
    ['/login?lang=en', 'Learning and Managing Your Account'],
    ['/register?lang=en', 'Create a student learning account. Instructor access is granted by an administrator.'],
    ['/forgot-password?lang=en', 'Enter your email address to request a password reset link.'],
    ['/reset-password/not-a-real-token?lang=en', 'Create a new strong password for your account.'],
  ]) {
    await page.goto(url);
    const modal = page.locator('.auth-modal');
    const heading = modal.locator('header h1');
    const introduction = modal.locator('header p').last();
    const home = modal.locator('header a[aria-label="Back to home"]');
    await expect(modal).toBeVisible();
    await expect(introduction).toHaveText(copy);
    await expect(home).toBeVisible();
    await expect(modal.getByText('Secure access · Learn Cambodia')).toHaveCount(0);
    await expect(modal.locator('a[aria-label="Back to home"]')).toHaveCount(1);
    const positions = await Promise.all([modal, heading, introduction, home].map(x => x.boundingBox()));
    const [panel, h, intro, back] = positions;
    expect(panel && h && intro && back).toBeTruthy();
    const center = panel.x + panel.width / 2;
    expect(Math.abs((h.x + h.width / 2) - center)).toBeLessThan(8);
    expect(Math.abs((intro.x + intro.width / 2) - center)).toBeLessThan(8);
    expect(back.y).toBeLessThan(h.y);
    expect(back.x).toBeLessThan(center - 35);
  }
});


test('profile dropdown light/dark switch persists on public and auth pages', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'light' });
  await page.goto('/?lang=en');
  const profile = page.locator('button[aria-controls="profile-menu"]');
  await profile.click();
  const switcher = page.locator('#profile-menu [data-theme-toggle]');
  await expect(switcher).toBeVisible();
  await expect(switcher).toHaveAttribute('role', 'switch');
  await expect(switcher).toHaveAttribute('aria-checked', 'false');
  const lightBackground = await page.locator('body').evaluate(el => getComputedStyle(el).backgroundColor);
  await switcher.click();
  await expect(page.locator('html')).toHaveClass(/dark/);
  await expect(switcher).toHaveAttribute('aria-checked', 'true');
  await expect(page.locator('header img[src*="/brand/logo-dark.png"]').first()).toBeVisible();
  const darkBackground = await page.locator('body').evaluate(el => getComputedStyle(el).backgroundColor);
  expect(darkBackground).not.toBe(lightBackground);

  await page.goto('/login?lang=en');
  await expect(page.locator('html')).toHaveClass(/dark/);
  await expect(page.locator('.auth-modal')).toBeVisible();

  await page.goto('/?lang=en');
  await profile.click();
  await expect(switcher).toHaveAttribute('aria-checked', 'true');
  await switcher.click();
  await expect(page.locator('html')).not.toHaveClass(/dark/);
  await expect(switcher).toHaveAttribute('aria-checked', 'false');
  await expect(page.locator('header img[src*="/brand/logo-light.png"]').first()).toBeVisible();

  await page.reload();
  await expect(page.locator('html')).not.toHaveClass(/dark/);
  await profile.click();
  await expect(switcher).toHaveAttribute('aria-checked', 'false');
});

test('profile theme preference is manual and overrides operating system color scheme', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.goto('/?lang=en');
  await expect(page.locator('html')).toHaveClass(/dark/);
  await page.locator('button[aria-controls="profile-menu"]').click();
  const switcher = page.locator('#profile-menu [data-theme-toggle]');
  await switcher.click();
  await expect(page.locator('html')).not.toHaveClass(/dark/);
  await page.reload();
  await expect(page.locator('html')).not.toHaveClass(/dark/);
});


test('premium flag-only language and profile menus animate while preserving existing actions', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'no-preference', colorScheme: 'light' });
  await page.goto('/?lang=en');
  const flag = page.locator('button[aria-controls="locale-menu"]');
  const profile = page.locator('button[aria-controls="profile-menu"]');

  await expect(flag).toBeVisible();
  await expect(flag.locator('svg')).toHaveCount(0); // No dropdown chevron.
  await expect(flag.locator('.premium-language-flag')).toContainText('🇬🇧');

  if (await page.evaluate(() => matchMedia('(hover: hover)').matches)) {
    await flag.hover();
    await page.waitForTimeout(280);
    const rotated = await flag.locator('.premium-language-flag').evaluate(
      el => getComputedStyle(el).transform
    );
    expect(rotated).not.toBe('none');
  }

  await flag.click();
  await expect(flag).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('#locale-menu')).toBeVisible();
  await expect(page.locator('#locale-menu [name="locale"]')).toHaveCount(3);
  await expect(page.locator('#locale-menu')).toHaveCSS('animation-name', 'premium-menu-enter');

  await profile.click();
  await expect(flag).toHaveAttribute('aria-expanded', 'false');
  await expect(profile).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('#profile-menu')).toBeVisible();
  await expect(page.locator('#profile-menu [data-theme-toggle]')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(profile).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('#profile-menu')).toBeHidden();
});

test('card corner SVGs are large and decorative; card names and actions stay usable', async ({ page }) => {
  await page.goto('/?lang=en');
  const cards = page.locator('.premium-card');
  expect(await cards.count()).toBeGreaterThanOrEqual(6);
  const icon = cards.locator('.premium-corner-icon svg').first();
  await expect(icon).toBeVisible();
  const size = await icon.boundingBox();
  expect(size).not.toBeNull();
  expect(size.width).toBeGreaterThanOrEqual(39);
  expect(size.height).toBeGreaterThanOrEqual(39);
  await expect(icon.locator('xpath=..')).toHaveAttribute('aria-hidden', 'true');

  const homeCard = page.locator('a.premium-card[href]').first();
  await expect(homeCard).toHaveAttribute('href', /\/courses|\/categories|\/articles|\/cefr/);
  await homeCard.click();
  await expect(page.locator('#main-content')).toBeVisible();
});

test('light and dark header menu controls preserve text contrast and motion settings', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'light' });
  await page.goto('/?lang=en');
  const flag = page.locator('.premium-language-trigger');
  const profile = page.locator('button[aria-controls="profile-menu"]');
  await expect(flag).toBeVisible();
  await expect(profile).toBeVisible();
  const initial = await profile.evaluate(el => getComputedStyle(el).backgroundColor);
  await profile.hover();
  const hover = await profile.evaluate(el => getComputedStyle(el).backgroundColor);
  expect(hover).toBe(initial); // Solid dark profile chip must remain legible on hover.
  await profile.click();
  await page.locator('#profile-menu [data-theme-toggle]').click();
  await expect(page.locator('html')).toHaveClass(/dark/);
  await expect(flag).toBeVisible();
  await expect(profile).toBeVisible();
  await expect(page.locator('header img[src*="logo-dark.png"]').first()).toBeVisible();
});


test('all active buttons and dropdown controls show the hand cursor, inputs remain text', async ({ page }) => {
  await page.goto('/?lang=en');
  const language = page.locator('button[aria-controls="locale-menu"]');
  const profile = page.locator('button[aria-controls="profile-menu"]');
  await expect(language).toHaveCSS('cursor', 'pointer');
  await expect(profile).toHaveCSS('cursor', 'pointer');
  await expect(page.locator('header a[aria-label="Learn Cambodia home"]')).toHaveCSS('cursor', 'pointer');
  await expect(page.locator('input#global-search')).toHaveCSS('cursor', 'text');

  await language.click();
  await expect(page.locator('#locale-menu button[name="locale"][value="km"]')).toHaveCSS('cursor', 'pointer');
  await profile.click();
  await expect(page.locator('#profile-menu [data-theme-toggle]')).toHaveCSS('cursor', 'pointer');

  await page.goto('/categories?lang=en');
  await expect(page.locator('#category-hero-search')).toHaveCSS('cursor', 'text');
  await expect(page.locator('form[role="search"] button[type="submit"]').first()).toHaveCSS('cursor', 'pointer');

  await page.goto('/login?lang=en');
  await expect(page.locator('button[type="submit"]').first()).toHaveCSS('cursor', 'pointer');
  await expect(page.locator('[data-password-toggle]')).toHaveCSS('cursor', 'pointer');
  await expect(page.locator('input[type="email"]')).toHaveCSS('cursor', 'text');
  await expect(page.locator('input[type="password"]')).toHaveCSS('cursor', 'text');
  await expect(page.locator('input[type="checkbox"]')).toHaveCSS('cursor', 'pointer');
});

test('disabled or aria-disabled controls never misleadingly show the hand cursor', async ({ page }) => {
  await page.goto('/?lang=en');
  await page.evaluate(() => {
    const disabled = document.createElement('button');
    disabled.type = 'button';
    disabled.disabled = true;
    disabled.id = 'pointer-disabled-test';
    document.body.appendChild(disabled);
    const ariaDisabled = document.createElement('button');
    ariaDisabled.type = 'button';
    ariaDisabled.setAttribute('aria-disabled', 'true');
    ariaDisabled.id = 'pointer-aria-disabled-test';
    document.body.appendChild(ariaDisabled);
  });
  await expect(page.locator('#pointer-disabled-test')).toHaveCSS('cursor', 'not-allowed');
  await expect(page.locator('#pointer-aria-disabled-test')).toHaveCSS('cursor', 'not-allowed');
});


test('learning focus cards center a large visible icon, name and count on desktop/mobile', async ({ page }) => {
  for (const size of [
    { width: 1440, height: 900 },
    { width: 768, height: 1024 },
    { width: 390, height: 844 },
  ]) {
    await page.setViewportSize(size);
    await page.goto('/categories?lang=en');
    const cards = page.locator('#learning-focus [data-learning-focus-card]');
    await expect(cards).toHaveCount(5);

    for (const card of await cards.all()) {
      const icon = card.locator('[data-learning-focus-icon] svg');
      const name = card.locator('[data-learning-focus-name]');
      const count = card.locator('[data-learning-focus-count]');
      await expect(icon).toBeVisible();
      await expect(name).toBeVisible();
      await expect(count).toBeVisible();
      const [cardBox, svg, heading, counter] = await Promise.all(
        [card, icon, name, count].map(item => item.boundingBox())
      );
      expect(cardBox && svg && heading && counter).toBeTruthy();
      const centerX = cardBox.x + cardBox.width / 2;
      for (const rect of [svg, heading, counter]) {
        expect(Math.abs(rect.x + rect.width / 2 - centerX)).toBeLessThan(4);
      }
      expect(svg.width).toBeGreaterThanOrEqual(70);
      expect(svg.height).toBeGreaterThanOrEqual(70);
      expect(heading.y).toBeGreaterThanOrEqual(svg.y + svg.height - 3);
      expect(counter.y).toBeGreaterThanOrEqual(heading.y + heading.height - 3);
      expect(counter.y + counter.height).toBeLessThanOrEqual(cardBox.y + cardBox.height);
    }
  }
});

test('category filter and reset preserve query, link destinations and focus', async ({ page }) => {
  await page.goto('/categories?q=IELTS&group=exam-preparation&lang=en');
  const nav = page.locator('nav[aria-label]').filter({ has: page.locator('a[href*="group="]') }).last();
  const nextGroup = nav.locator('a[href*="group=english-courses"]');
  await expect(nextGroup).toHaveAttribute('href', /q=IELTS/);
  await expect(nextGroup).toHaveAttribute('href', /lang=en/);

  await nextGroup.click();
  await expect(page).toHaveURL(/group=english-courses/);
  await expect(page).toHaveURL(/q=IELTS/);
  const clear = page.locator('#category-directory').locator('a').filter({ hasText: 'Clear filters' });
  await expect(clear).toHaveCount(1);
  await clear.click();
  await expect(page).toHaveURL(/\/categories\?lang=en/);
  await expect(page).not.toHaveURL(/group=|q=/);

  const footer = page.locator('footer');
  await expect(footer.locator('a').filter({ hasText: /^TOEFL$/ })).toHaveAttribute('href', /q=TOEFL/);
  await expect(footer.locator('a').filter({ hasText: /^TOEIC$/ })).toHaveAttribute('href', /q=TOEIC/);
});

test('category directory cards have blended unique art and one working link each', async ({ page }) => {
  await page.goto('/categories?lang=en');
  const cards = page.locator('#category-directory a.premium-card[href]');
  expect(await cards.count()).toBeGreaterThan(5);
  const icons = await cards.locator('.premium-corner-icon svg path').evaluateAll(nodes =>
    [...new Set(nodes.map(node => node.getAttribute('d')))]
  );
  expect(icons.length).toBeGreaterThan(2);
  await cards.first().click();
  await expect(page).toHaveURL(/\/categories\/[^/?]+/);
});

test('informational pages keep legal drafts visible and match the theme switch', async ({ page }) => {
  await page.goto('/information/privacy?lang=en');
  const section = page.locator('[data-information-page="privacy"]');
  await expect(section).toBeVisible();
  await expect(section.locator('[role="note"]')).toContainText('preliminary draft');
  await expect(section.locator('a[href*="/categories"]')).toBeVisible();
  const original = await section.evaluate(el => getComputedStyle(el).backgroundColor);
  await page.locator('button[aria-controls="profile-menu"]').click();
  await page.locator('#profile-menu [data-theme-toggle]').click();
  await expect(page.locator('html')).toHaveClass(/dark/);
  const dark = await section.evaluate(el => getComputedStyle(el).backgroundColor);
  expect(dark).not.toEqual(original);
  await expect(section.locator('[role="note"]')).toContainText('preliminary draft');
});

test('CEFR information cards have consistent pointer motion and do not pretend to be links', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'light', reducedMotion: 'no-preference' });
  await page.goto('/cefr?lang=en#A1');
  const cards = page.locator('[data-cefr-level-card]');
  await expect(cards).toHaveCount(6);
  const a1 = page.locator('article#A1[data-cefr-level-card]');
  await expect(a1).toBeVisible();
  await expect(a1).toHaveCSS('cursor', 'auto');
  await expect(a1.locator('a[href]')).toHaveCount(0);

  const pointerHover = await page.evaluate(() =>
    matchMedia('(hover: hover) and (pointer: fine)').matches
  );
  if (pointerHover) {
    await a1.hover();
    await expect.poll(() => a1.evaluate(el => getComputedStyle(el).transform))
      .not.toBe('none');

    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await a1.hover();
    await expect.poll(() => a1.evaluate(el => getComputedStyle(el).transform))
      .not.toBe('none');
  }

  await page.emulateMedia({ reducedMotion: 'reduce' });
  await a1.hover();
  await expect(a1).toHaveCSS('transform', 'none');
  await expect(a1).toHaveCSS('translate', 'none');
});

test('public guidance and featured metric cards share the premium motion class', async ({ page }) => {
  for (const [path, selector] of [
    ['/information/about?lang=en', '#about-mission article.premium-card'],
    ['/information/about?lang=en', 'div.premium-card-on-navy'],
    ['/authors?lang=en', 'div.premium-card-on-navy'],
    ['/reading?lang=en', 'div.premium-card:has-text("Read with a purpose")'],
    ['/exam-catalog?lang=en', 'div.premium-card:has-text("Choose a reviewed course")'],
    ['/leaderboard?lang=en', 'div.premium-card:has-text("Enrollment milestone")'],
    ['/recommendations?lang=en', 'div.premium-card:has-text("Own learning interests")'],
  ]) {
    await page.goto(path);
    const card = page.locator(selector).first();
    await expect(card).toBeVisible();
    await expect(card).toHaveCSS('cursor', 'auto');
    if (await page.evaluate(() => matchMedia('(hover: hover) and (pointer: fine)').matches)) {
      await card.hover();
      await expect.poll(() => card.evaluate(el => getComputedStyle(el).transform))
        .not.toBe('none');
    }
  }

  await page.goto('/categories?lang=en');
  const linked = page.locator('#learning-focus a.premium-card').first();
  await expect(linked).toHaveAttribute('href', /group=/);
  await expect(linked).toHaveCSS('cursor', 'pointer');
});

test('homepage learning steps display compact 2–4 responsive cards with visible icons', async ({ page }) => {
  for (const [width, columns] of [[390, 2], [820, 2], [1440, 4]]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/?lang=en');

    const grid = page.locator('[data-home-learning-steps]');
    const cards = grid.locator('[data-home-step-card]');
    await expect(cards).toHaveCount(4);

    const count = await grid.evaluate(el => getComputedStyle(el).gridTemplateColumns.trim().split(/\s+/).length);
    expect(count, `homepage grid at ${width}px`).toBe(columns);

    for (const card of await cards.all()) {
      const icon = card.locator('[data-home-step-icon] svg');
      const title = card.locator('h3');
      await expect(icon).toBeVisible();
      await expect(title).toBeVisible();
      const [cardBox, iconBox, titleBox] = await Promise.all(
        [card, icon, title].map(el => el.boundingBox())
      );
      expect(cardBox && iconBox && titleBox).toBeTruthy();
      expect(iconBox.y + iconBox.height).toBeLessThanOrEqual(titleBox.y + 2);
      expect(iconBox.x).toBeGreaterThanOrEqual(cardBox.x);
      expect(iconBox.x + iconBox.width).toBeLessThanOrEqual(cardBox.x + cardBox.width);
    }

    const level = page.locator('[data-home-level-guidance]');
    const icon = level.locator('svg');
    const heading = level.locator('h2');
    await expect(level).toBeVisible();
    await expect(icon).toBeVisible();
    const [svg, h2] = await Promise.all([icon.boundingBox(), heading.boundingBox()]);
    expect(svg && h2).toBeTruthy();
    expect(h2.y - (svg.y + svg.height)).toBeLessThan(90);

    const pathways = page.locator('[data-home-pathways]');
    const header = pathways.locator('h2').first();
    const action = pathways.getByRole('link', { name: /view all/i });
    await expect(action).toBeVisible();
    const [headingBox, actionBox] = await Promise.all([header.boundingBox(), action.boundingBox()]);
    expect(headingBox && actionBox).toBeTruthy();
    const overlaps = headingBox.x < actionBox.x + actionBox.width &&
      headingBox.x + headingBox.width > actionBox.x &&
      headingBox.y < actionBox.y + actionBox.height &&
      headingBox.y + headingBox.height > actionBox.y;
    expect(overlaps, 'pathways title and View all must not overlap').toBe(false);
  }
});

test('footer uses two phone columns, four tablet/desktop columns and accessible social icons', async ({ page }) => {
  for (const [width, columns] of [[390, 2], [820, 4], [1440, 4]]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/?lang=en');

    const grid = page.locator('footer [data-footer-columns]');
    const count = await grid.evaluate(el => getComputedStyle(el).gridTemplateColumns.trim().split(/\s+/).length);
    expect(count, `footer grid at ${width}px`).toBe(columns);

    const footer = page.locator('footer');
    await expect(footer).toContainText('All Rights Reserved.');
    await expect(footer).not.toContainText('Original educational content and branding reserved.');
    await expect(footer).not.toContainText('Independent preparation information.');
    const social = footer.locator('[data-footer-social] a');
    await expect(social).toHaveCount(4);
    for (const link of await social.all()) {
      await expect(link).toBeVisible();
      await expect(link).toHaveAttribute('aria-label', /Share LearnCambodia on/);
      await expect(link).toHaveAttribute('rel', /noopener noreferrer/);
      await expect(link.locator('svg')).toBeVisible();
    }

    const bounds = await footer.locator('[data-footer-bottom]').boundingBox();
    const socialBounds = await footer.locator('[data-footer-social]').boundingBox();
    expect(bounds && socialBounds).toBeTruthy();
    expect(socialBounds.x).toBeGreaterThanOrEqual(bounds.x - 2);
    expect(socialBounds.x + socialBounds.width).toBeLessThanOrEqual(bounds.x + bounds.width + 2);
  }
});

test('footer social icons are transparent and borderless on phone and desktop', async ({ page }) => {
  for (const width of [390, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.goto('/?lang=en');

    const social = page.locator('footer [data-footer-social] a');
    await expect(social).toHaveCount(4);
    for (const link of await social.all()) {
      await expect(link).toBeVisible();
      await expect(link).toHaveAttribute('href', /https:\/\//);
      await expect(link).toHaveAttribute('aria-label', /Share LearnCambodia on/);
      await expect(link.locator('svg')).toBeVisible();
      const originalStyle = await link.evaluate(el => {
        const computed = getComputedStyle(el);
        return {
          background: computed.backgroundColor,
          borderTop: computed.borderTopWidth,
          borderRight: computed.borderRightWidth,
          borderBottom: computed.borderBottomWidth,
          borderLeft: computed.borderLeftWidth,
        };
      });
      expect(originalStyle.background).toBe('rgba(0, 0, 0, 0)');
      expect(originalStyle.borderTop).toBe('0px');
      expect(originalStyle.borderRight).toBe('0px');
      expect(originalStyle.borderBottom).toBe('0px');
      expect(originalStyle.borderLeft).toBe('0px');

      await link.hover();
      await expect(link).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
      await expect(link).toHaveCSS('border-top-width', '0px');
    }
  }
});

test('guest profile dropdown has real icon-prefixed actions on desktop and mobile', async ({ page }) => {
  for (const width of [390, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/?lang=en');
    await page.locator('button[aria-controls="profile-menu"]').click();
    const menu = page.locator('#profile-menu');
    await expect(menu).toBeVisible();
    for (const [selector, icon] of [
      ['[data-profile-sign-in]', '[data-profile-sign-in-icon]'],
      ['[data-profile-register]', '[data-profile-register-icon]'],
    ]) {
      const link = menu.locator(selector);
      await expect(link).toBeVisible();
      await expect(link.locator(icon)).toBeVisible();
      await expect(link.locator('svg')).toHaveCount(1);
    }
    await expect(menu.locator('[data-theme-toggle] svg')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(menu).toBeHidden();
  }
});

test('exam catalog sign-in preserves validated reading destination instead of losing context', async ({ page }) => {
  await page.goto('/exam-catalog?lang=en');
  await page.locator('button[aria-controls="profile-menu"]').click();
  const link = page.locator('#profile-menu [data-profile-sign-in]');
  const href = await link.getAttribute('href');
  const query = new URL(href, 'https://learncambodia.com').searchParams;
  expect(query.get('next')).toBe('/exam-catalog?lang=en');

  await link.click();
  await expect(page).toHaveURL(/\/login\?/);
  const next = new URL(page.url()).searchParams.get('next');
  expect(next).toBe('/exam-catalog?lang=en');

  const register = page.getByRole('link', { name: /create account/i });
  const registration = new URL(await register.getAttribute('href'), 'https://learncambodia.com');
  expect(registration.searchParams.get('next')).toBe('/exam-catalog?lang=en');
});
