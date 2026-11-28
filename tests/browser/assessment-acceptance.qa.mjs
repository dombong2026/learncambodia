import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

function fixtures() {
  return JSON.parse(readFileSync(
    new URL('./.qa-assessment-fixtures.json', import.meta.url),
    'utf8'
  ));
}

async function verifyCountdown(page) {
  const timer = page.locator('[data-exam-countdown]');

  await expect(timer).toBeVisible();
  await expect(timer).toHaveText(/\d/);

  const initial = await timer.textContent();
  const expiration = await timer.getAttribute('data-expires');

  expect(Number(expiration)).toBeGreaterThan(Date.now() / 1000);

  await expect.poll(
    () => timer.textContent(),
    { timeout: 5000 }
  ).not.toBe(initial);

  return expiration;
}

async function verifyForeignDenial(browser, baseURL, attempt, audio) {
  const foreign = await browser.newContext({ baseURL });

  try {
    const response = await foreign.request.get(attempt);
    expect(response.status()).toBe(404);

    if (audio) {
      const protectedAudio = await foreign.request.get(audio);
      expect(protectedAudio.status()).toBe(404);
    }
  } finally {
    await foreign.close();
  }
}

test('specialized assessment preserves participation and result ownership', async ({
  page, browser, baseURL,
}) => {
  test.setTimeout(60_000);

  const fixture = fixtures();

  const response = await page.goto(fixture.specialized + '?lang=en');

  expect(response.status()).toBe(200);
  await expect(page).toHaveURL(/\/specialized-exams\//);
  await expect(page.locator('.specialized-exam-shell')).toBeVisible();

  const start = page.locator('form[action$="/start"]');

  await expect(start).toHaveCount(1);
  await start.locator('button').click();

  await expect(page).toHaveURL(/\/specialized-exams\/attempts\/\d+/);

  const attempt = page.url();
  const expiration = await verifyCountdown(page);

  await page.reload();

  await expect(page.locator('[data-exam-countdown]'))
    .toHaveAttribute('data-expires', expiration);

  await verifyForeignDenial(browser, baseURL, attempt);

  await page.locator('input[name="answers[0]"][value="a"]').check();

  const form = page.locator('form[data-exam-attempt]');
  await form.locator('button[type="submit"]').click();

  await expect(page.locator('.assessment-result-score'))
    .toHaveText('100%');

  await page.reload();

  await expect(page.locator('.assessment-result-score'))
    .toHaveText('100%');

  await verifyForeignDenial(browser, baseURL, attempt);
});

test('native guest assessment preserves countdown, audio authorization and scoring', async ({
  page, browser, baseURL,
}) => {
  test.setTimeout(60_000);

  const fixture = fixtures();

  const response = await page.goto(fixture.guest + '?lang=en');
  expect(response.status()).toBe(200);

  await page.getByRole('button', {
    name: 'Start as guest',
    exact: true,
  }).click();

  await expect(page).toHaveURL(/\/guest-assessments\/attempts\/\d+/);

  const attempt = page.url();
  const expiration = await verifyCountdown(page);

  await page.reload();

  await expect(page.locator('[data-exam-countdown]'))
    .toHaveAttribute('data-expires', expiration);

  // The premium player intentionally hides native controls.
  // Keep the native element for media and URL verification.
  const player = page.locator(
    'audio[aria-label="Listening audio for question 1"]'
  );

  await expect(player).toHaveCount(1);
  await expect(player).toBeAttached();
  await expect(player).toHaveAttribute('data-lc-audio-ready', '');
  await expect(player).toBeHidden();

  const premium = page.locator(
    '.lc-audio[role="group"][aria-label="Listening audio for question 1"]'
  );

  await expect(premium).toHaveCount(1);
  await expect(premium).toBeVisible();

  const play = premium.getByRole('button', {
    name: 'Play',
    exact: true,
  });

  await expect(play).toBeVisible();
  await expect(play).toBeEnabled();

  const audioURL = new URL(
    await player.getAttribute('src'),
    page.url()
  ).href;

  expect(new URL(audioURL).pathname)
    .toMatch(/^\/guest-assessments\/attempts\/\d+\/audio\/\d+$/);

  const authorized = await page.request.get(audioURL);

  expect(authorized.status()).toBe(200);
  expect(authorized.headers()['content-type']).toContain('audio/wav');
  expect(authorized.headers()['cache-control']).toContain('no-store');

  // Start playback through the real user-facing control.
  // Do not force-click or invoke audio.play() from the test.
  expect(await player.evaluate(audio => audio.currentTime)).toBe(0);

  await play.click();

  await expect.poll(
    () => player.evaluate(audio => audio.readyState),
    { timeout: 10000 }
  ).toBeGreaterThanOrEqual(1);

  expect(await player.evaluate(audio => audio.error)).toBeNull();

  // The fixture is a short one-second WAV. Checking time
  // advancement also works if playback has already ended.
  await expect.poll(
    () => player.evaluate(audio => audio.currentTime),
    { timeout: 10000 }
  ).toBeGreaterThan(0);

  expect(await player.evaluate(audio => audio.duration))
    .toBeGreaterThan(0);

  await expect(premium.locator('.lc-audio-status')).toBeHidden();

  await verifyForeignDenial(browser, baseURL, attempt, audioURL);

  await expect(page.locator('[data-exam-check]')).toHaveCount(0);
  await expect(page.locator('body'))
    .not.toContainText('Private QA answer explanation');

  const form = page.locator('form[data-exam-attempt]');
  const csrf = await form.locator('input[name="_token"]').inputValue();

  await form.locator('input[name="answers[0]"][value="a"]').check();
  await form.getByRole('button', { name: 'Submit attempt' }).click();

  await expect(page.getByRole('heading', {
    name: 'Passed',
    exact: true,
  })).toBeVisible();

  await expect(page.locator('#main-content'))
    .toContainText('100% scored');

  const duplicate = await page.request.post(attempt, {
    form: {
      _token: csrf,
      'answers[0]': 'a',
    },
    maxRedirects: 0,
  });

  expect(duplicate.status()).toBe(409);

  expect((await page.request.get(audioURL)).status()).toBe(404);

  await page.reload();

  await expect(page.locator('#main-content'))
    .toContainText('100% scored');

  await verifyForeignDenial(browser, baseURL, attempt, audioURL);
});
