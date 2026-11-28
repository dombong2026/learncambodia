import { defineConfig } from '@playwright/test';
import base from './playwright.config.mjs';

export default defineConfig({
  ...base,
  testMatch: '**/assessment-acceptance.qa.mjs',
  workers: 1,
  retries: 0,
});
