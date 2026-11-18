import { expect, test } from '@playwright/test';

test('provider gear opens a centered modal and Escape returns to the list', async ({ page }) => {
    const settings = '/dashboard/system-settings/social-auth';
    const password = 'Only-A-CI-Browser-Fixture-2026!';
    await page.goto('/login?lang=en');
    await page.locator('input[name="email"]').fill('editor-browser-qa@example.test');
    await page.locator('input[name="password"]').fill(password);
    await page.locator('input[name="password"]').press('Enter');
    await page.waitForURL(/\/dashboard(?:[/?#]|$)/);

    await page.goto(settings);
    await page.getByRole('link', { name: 'Configure Google' }).click();
    await page.waitForURL(/\/security\/confirm-password/);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'Confirm password' }).click();

    const dialog = page.getByRole('dialog', { name: 'Google OAuth settings' });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Copy callback URL' })).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Save settings' })).toBeVisible();
    await expect(dialog.locator('input[name="client_secret"]')).toHaveValue('');

    const placement = await dialog.evaluate((element) => {
        const rect = element.getBoundingClientRect();
        return {
            modal: element.matches(':modal'),
            inside: rect.left > 0 && rect.top >= 0 && rect.right <= innerWidth && rect.bottom <= innerHeight,
            centered: Math.abs(rect.left + rect.width / 2 - innerWidth / 2) < 2,
        };
    });
    expect(placement).toEqual({ modal: true, inside: true, centered: true });

    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Configure Google' })).toBeVisible();

    await page.getByRole('link', { name: 'Configure GitHub' }).click();
    const github = page.getByRole('dialog', { name: 'GitHub OAuth settings' });
    await expect(github).toBeVisible();
    await github.getByRole('link', { name: 'Close settings' }).click();
    await expect(github).toHaveCount(0);
});
