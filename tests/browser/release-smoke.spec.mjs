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
  ['cefr', '/cefr'],
  ['library', '/library'],
  ['reading', '/reading'],
  ['exam-catalog', '/exam-catalog'],
  ['recommendations', '/recommendations'],
  ['leaderboard', '/leaderboard'],
  ['course-watch', '/course-watch'],
  ['upload-studio', '/upload-studio'],
];
const screenshots = new Set(['home', 'categories', 'library', 'about', 'exam-catalog']);

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
