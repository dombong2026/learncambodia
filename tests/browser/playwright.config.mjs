import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.mjs',
  fullyParallel: false,
  workers: 2,
  retries: 0,
  timeout: 30_000,
  expect: { timeout: 10_000 },
  reporter: [['list']],
  use: {
    baseURL: process.env.BROWSER_BASE_URL || 'http://127.0.0.1:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    ignoreHTTPSErrors: false,
  },
  projects: [
    { name: 'desktop-chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
    { name: 'mobile-chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
    { name: 'tablet-chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 768, height: 1024 }, isMobile: true, hasTouch: true } },
    { name: 'desktop-webkit', use: { ...devices['Desktop Safari'], viewport: { width: 1440, height: 900 } } },
    { name: 'mobile-webkit', use: { ...devices['iPhone 13'] } },
  ],
});
