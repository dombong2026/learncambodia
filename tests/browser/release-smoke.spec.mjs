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
