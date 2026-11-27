import { expect, test } from '@playwright/test';

for (const [role,landing] of [['student','/account'],['instructor','/instructor'],['developer','/developer']]) {
  test(`${role} dashboard follows the approved glass card system`, async ({page}) => {
    test.slow();
    await page.goto('/login?lang=en');
    await page.locator('#login_email').fill(`${role}-glass-browser@example.test`);
    await page.locator('#login_password').fill('Only-A-CI-Browser-Fixture-2026!');
    await page.locator('button[type="submit"]').first().click();
    await expect(page).toHaveURL(new RegExp(landing+'(?:\\?|$)'));
    const grid=page.locator('[data-dashboard-card-grid]');
    const panels=grid.locator(':scope > section');
    const rows=grid.locator('[data-dashboard-role-row]');
    const actions=panels.nth(2).locator('a[href]');
    await expect(panels).toHaveCount(3);
    await expect(grid.locator('[data-dashboard-review-row]')).toHaveCount(0);
    await expect(rows.first()).toBeVisible();
    await expect(actions).toHaveCount(3);
    await expect(page.locator('link[href*="workspace-unified-glass.css?v=3"]')).toHaveCount(1);
    if (role==='student') await expect(rows.filter({hasText:'Dashboard fixture deadline'})).toHaveCount(1);
    if (role==='instructor') await expect(rows.filter({hasText:'Dashboard fixture grading'})).toHaveCount(1);
    if (role==='developer') await expect(rows).toHaveCount(3);
    for (const row of await rows.all()) {
      await expect(row.locator('svg')).toHaveCount(1);
      await expect(row.locator('[data-dashboard-role-label]')).not.toBeEmpty();
      await expect(row.locator('[data-dashboard-role-detail]')).not.toBeEmpty();
      await expect(row.locator('[data-dashboard-role-meta]')).toHaveText(role==='developer'?/^\d[\d.,]*[KMB]?$/i:/^[A-Z][a-z]{2} \d{1,2}, \d{2}:\d{2}$/);
      const destination=new URL(await row.evaluate(el=>el.href));
      expect(destination.origin).toBe(new URL(page.url()).origin);
      expect(destination.pathname.startsWith(landing+'/')).toBe(true);
      const meta=await row.locator('[data-dashboard-role-meta]').boundingBox();
      const label=await row.locator('[data-dashboard-role-label]').boundingBox();
      expect(meta.x).toBeGreaterThan(label.x);
    }
    for (const dark of [false,true]) {
      await page.evaluate(value=>document.documentElement.classList.toggle('dark',value),dark);
      await expect(panels.nth(1)).toHaveCSS('background-color',dark?'rgba(19, 37, 72, 0.56)':'rgba(255, 255, 255, 0.38)');
      await expect(panels.nth(1)).toHaveCSS('animation-name','lc-workspace-glass-reflection');
      // Preserve the intentional -2px hover lift. Measure resting geometry only.
      await page.mouse.move(0,0);
      const readBounds=()=>panels.evaluateAll(nodes=>nodes.map(el=>({top:el.getBoundingClientRect().top,height:el.getBoundingClientRect().height,bottom:el.getBoundingClientRect().bottom})));
      if (await page.evaluate(()=>matchMedia('(min-width: 80rem)').matches)) {
        await expect.poll(()=>grid.evaluate(el=>el.matches(':hover'))).toBe(false);
        await expect.poll(async()=>{
          const bounds=await readBounds();
          return Math.max(...bounds.map(x=>x.top))-Math.min(...bounds.map(x=>x.top));
        }).toBeLessThan(2);
        const bounds=await readBounds();
        expect(Math.max(...bounds.map(x=>x.height))-Math.min(...bounds.map(x=>x.height))).toBeLessThan(2);
      } else {
        const bounds=await readBounds();
        expect(bounds[1].top).toBeGreaterThan(bounds[0].bottom);
        expect(bounds[2].top).toBeGreaterThan(bounds[1].bottom);
      }
      if (await page.evaluate(()=>matchMedia('(hover: hover)').matches)) {
        for (const row of [...await rows.all(),...await actions.all()]) {
          await row.hover();
          await expect(row).toHaveCSS('background-color',dark?'rgb(37, 70, 105)':'rgb(255, 247, 229)');
          await expect(row).toHaveCSS('border-top-color',dark?'rgb(98, 141, 191)':'rgb(245, 196, 92)');
        }
        await expect(rows.first().locator('[data-dashboard-role-label]')).toHaveCSS('color',dark?'rgb(231, 239, 251)':'rgb(32, 53, 82)');
      }
      expect(await page.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
    }
    await page.emulateMedia({reducedMotion:'reduce'});
    await expect(panels.nth(1)).toHaveCSS('animation-name','none');
  });
}
