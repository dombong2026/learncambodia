import { expect, test } from '@playwright/test';

for (const [role,landing,count] of [['admin','/dashboard',7],['student','/account',6],['instructor','/instructor',3],['developer','/developer',3]]) {
  test(`${role} profile menu has only frequent shortcuts and unchanged account controls`, async ({page}) => {
    test.slow();
    await page.goto('/login?lang=en');
    await page.locator('#login_email').fill(`${role}-glass-browser@example.test`);
    await page.locator('#login_password').fill('Only-A-CI-Browser-Fixture-2026!');
    await page.locator('button[type="submit"]').first().click();
    await expect(page).toHaveURL(new RegExp(landing+'(?:\\?|$)'));
    for (const [path,id] of [[landing,'workspace-profile'],['/','profile-menu']]) {
      await page.goto(path+'?lang=en');
      const trigger=page.locator('button[aria-controls="'+id+'"]');
      await trigger.click();
      const menu=page.locator('#'+id);
      await expect(menu).toBeVisible();
      await expect(menu.locator('[data-profile-role-menu]')).toHaveAttribute('data-profile-role-menu',role);
      const shortcuts=menu.locator('[data-profile-role-link]');
      await expect(shortcuts).toHaveCount(count);
      await expect(menu.locator('[data-profile-dashboard]')).toHaveCount(1);
      await expect(menu.locator('[data-profile-nav-group], [data-profile-settings]')).toHaveCount(0);
      await expect(menu.locator('[data-profile-divider]')).toHaveCount(2);
      await expect(menu.locator('[data-theme-toggle]')).toBeVisible();
      await expect(menu.locator('form[action*="/logout"] button[type="submit"]')).toBeVisible();
      const hrefs=await shortcuts.evaluateAll(nodes=>nodes.map(el=>el.href));
      expect(new Set(hrefs).size).toBe(count);
      for (const row of await shortcuts.all()) {
        await expect(row.locator('svg')).toHaveCount(1);
        const url=new URL(await row.evaluate(el=>el.href));
        expect(url.origin).toBe(new URL(page.url()).origin);
        expect(url.pathname.startsWith(landing+'/')).toBe(true);
      }
      const box=await menu.boundingBox();
      const width=await page.evaluate(()=>document.documentElement.clientWidth);
      expect(box.x).toBeGreaterThanOrEqual(0);
      expect(box.x+box.width).toBeLessThanOrEqual(width+1);
      await page.keyboard.press('Escape');
      await expect(menu).toBeHidden();
    }
  });
}
