import { expect, test } from '@playwright/test';

test('workspace glass preserves real navigation in expanded, rail and mobile modes', async ({page}) => {
  await page.goto('/login?lang=en');
  await page.locator('#login_email').fill('editor-browser-qa@example.test');
  await page.locator('#login_password').fill('Only-A-CI-Browser-Fixture-2026!');
  await page.locator('button[type="submit"]').first().click();
  await expect(page).toHaveURL(/\/dashboard(?:\?|$)/);
  const shell=page.locator('[data-workspace]');
  await expect(shell).toHaveAttribute('data-glass-sidebar','');
  const sidebar=page.locator('[data-sidebar]');
  const header=page.locator('[data-sidebar-toggle]');
  const edge=page.locator('[data-glass-sidebar-toggle]');
  const group=sidebar.locator('[data-nav-group]>summary').first();
  await expect(group).toHaveText('Dashboard');
  await expect(group.locator('..').locator('[data-workspace-link]').first()).toHaveText('Overview');
  await expect(page.locator('[data-dashboard-compact-heading]')).toHaveClass(/sr-only/);
  await expect(page.locator('[data-dashboard-actions]')).toBeVisible();
  const desktop=await page.evaluate(()=>matchMedia('(min-width: 64rem)').matches);
  if (desktop) {
    await expect(sidebar).toBeVisible();
    await expect(edge).toBeVisible();
    await expect(header).toBeHidden();
    await expect(shell).toHaveCSS('padding-inline-start','266px');
    const originalLinks=await sidebar.locator('[data-workspace-link]').evaluateAll(items=>items.map(x=>x.getAttribute('href')));
    await edge.click();
    await expect(edge).toHaveAttribute('aria-expanded','false');
    await expect.poll(async()=>Math.round((await sidebar.boundingBox()).width)).toBe(72);
    await expect(group).toBeVisible();
    expect(await sidebar.evaluate(el=>el.inert)).toBe(false);
    await page.reload();
    await expect(edge).toHaveAttribute('aria-expanded','false');
    await group.click();
    await expect(edge).toHaveAttribute('aria-expanded','true');
    await expect(shell).toHaveCSS('padding-inline-start','266px');
    await expect.poll(async()=>Math.round((await sidebar.boundingBox()).width)).toBe(254);
    expect(await sidebar.locator('[data-workspace-link]').evaluateAll(items=>items.map(x=>x.getAttribute('href')))).toEqual(originalLinks);
  } else {
    await expect(sidebar).toBeHidden();
    await expect(header).toBeVisible();
    await header.click();
    await expect(sidebar).toBeVisible();
    await expect(edge).toBeHidden();
    await expect(group).toBeVisible();
    await page.locator('[data-sidebar-close]').click();
    await expect(sidebar).toBeHidden();
    await expect(header).toBeFocused();
  }
  await page.evaluate(()=>document.documentElement.classList.remove('dark'));
  const light=await sidebar.evaluate(el=>getComputedStyle(el).color);
  const selected=sidebar.locator('[data-workspace-link][aria-current="page"]').first();
  await expect(selected).toHaveCSS('color','rgb(255, 255, 255)');
  const lightSurface=await sidebar.evaluate(el=>getComputedStyle(el).backgroundImage);
  await page.evaluate(()=>document.documentElement.classList.add('dark'));
  const dark=await sidebar.evaluate(el=>getComputedStyle(el).color);
  expect(light).not.toBe(dark);
  expect(await sidebar.evaluate(el=>getComputedStyle(el).backgroundImage)).not.toBe(lightSurface);
  await expect(selected).toHaveCSS('color','rgb(255, 255, 255)');
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
  const body=page.locator('[data-workspace-body]');
  const card=page.locator('[data-workspace-content] a.premium-card').first();
  await expect(card).toBeVisible();
  await expect(page.locator('link[href*="workspace-unified-glass.css"]')).toHaveCount(1);
  await expect(card).toHaveCSS('background-color','rgba(19, 37, 72, 0.56)');
  await expect(card).toHaveCSS('animation-name','lc-workspace-glass-reflection');
  const darkCanvas=await body.evaluate(el=>getComputedStyle(el).backgroundImage);
  await page.evaluate(()=>document.documentElement.classList.remove('dark'));
  await expect(card).toHaveCSS('background-color','rgba(255, 255, 255, 0.38)');
  expect(await body.evaluate(el=>getComputedStyle(el).backgroundImage)).not.toBe(darkCanvas);
  const glassPalette=await page.evaluate(()=>{
    const shell=document.querySelector('[data-workspace]');
    const body=document.querySelector('[data-workspace-body]');
    return [getComputedStyle(shell).getPropertyValue('--glass-surface').trim(),getComputedStyle(body).getPropertyValue('--unified-sidebar').trim()];
  });
  expect(glassPalette[0]).toBe(glassPalette[1]);
  expect(glassPalette[0]).not.toBe('');
  await page.emulateMedia({reducedMotion:'reduce'});
  await expect(card).toHaveCSS('animation-name','none');
  await page.emulateMedia({reducedMotion:'no-preference'});
  await page.locator('[data-saver-toggle]').click();
  await expect(body).toHaveAttribute('data-saver','on');
  await expect(card).toHaveCSS('animation-name','none');
  await page.locator('[data-saver-toggle]').click();
  await expect(body).toHaveAttribute('data-saver','off');
  // Verify actual review links and responsive panel alignment in both themes.
  const cards=page.locator('[data-dashboard-card-grid] > section');
  const reviews=page.locator('[data-dashboard-review-row]');
  await expect(cards).toHaveCount(3);
  await expect(reviews).toHaveCount(4);
  for (const [index,label] of ['Course reviews','Enrollment reviews','Homework reviews','Appearance reviews'].entries()) {
    const row=reviews.nth(index);
    await expect(row).toBeVisible();
    await expect(row).toContainText(label);
    await expect(row.locator('svg')).toHaveCount(1);
    await expect(row.locator('[data-dashboard-review-count]')).toHaveText(/^\d[\d.,]*[KMB]?$/i);
    await expect(row).toHaveAttribute('href',/^https?:\/\//);
    await expect(row).not.toContainText('Across approval queues');
    const labelBox=await row.locator('span').nth(1).boundingBox();
    const countBox=await row.locator('[data-dashboard-review-count]').boundingBox();
    expect(countBox.x).toBeGreaterThan(labelBox.x);
  }
  await expect(cards.nth(2)).not.toContainText('Only verified records are shown.');
  for (const dark of [false,true]) {
    await page.evaluate(value=>document.documentElement.classList.toggle('dark',value),dark);
    const bounds=await cards.evaluateAll(nodes=>nodes.map(el=>({top:el.getBoundingClientRect().top,height:el.getBoundingClientRect().height,bottom:el.getBoundingClientRect().bottom})));
    if (await page.evaluate(()=>matchMedia('(min-width: 80rem)').matches)) {
      expect(Math.max(...bounds.map(x=>x.top))-Math.min(...bounds.map(x=>x.top))).toBeLessThan(2);
      expect(Math.max(...bounds.map(x=>x.height))-Math.min(...bounds.map(x=>x.height))).toBeLessThan(2);
    } else {
      expect(bounds[1].top).toBeGreaterThan(bounds[0].bottom);
      expect(bounds[2].top).toBeGreaterThan(bounds[1].bottom);
    }
  }
  await page.goto('/?lang=en');
  await expect(page.locator('link[href*="workspace-unified-glass.css"]')).toHaveCount(0);
});
