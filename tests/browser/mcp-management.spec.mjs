import {test,expect} from '@playwright/test';

test('MCP tool detail dialog is usable and does not dispatch network requests',async({page})=>{
    const requests=[];
    page.on('request',request=>{
        if(!request.url().startsWith('data:')&&!request.url().startsWith('about:'))requests.push(request.url());
    });
    await page.goto('/up');
    await page.setContent([
        '<html><body><div data-mcp-manager>',
        '<button type="button" data-mcp-tool-details data-name="campaigns.list"',
        ' data-title="Homepage Campaigns" data-scope="campaigns:read"',
        ' data-risk="Low" data-summary="Read published campaign metadata.">',
        'Campaign tool details</button>',
        '<dialog id="mcp-tool-dialog" aria-labelledby="mcp-dialog-title">',
        '<h3 id="mcp-dialog-title"></h3><dd id="mcp-detail-id"></dd>',
        '<dd id="mcp-detail-scope"></dd><dd id="mcp-detail-risk"></dd>',
        '<p id="mcp-detail-summary"></p>',
        '<button type="button" data-mcp-dialog-close>Close</button>',
        '</dialog></div></body></html>'
    ].join(''));
    requests.length=0;
    await page.addScriptTag({path:'public/js/mcp-management.js'});
    await page.getByRole('button',{name:'Campaign tool details'}).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await expect(page.locator('#mcp-dialog-title')).toHaveText('Homepage Campaigns');
    await expect(page.locator('#mcp-detail-id')).toHaveText('campaigns.list');
    await expect(page.locator('#mcp-detail-scope')).toHaveText('campaigns:read');
    await expect(page.locator('#mcp-detail-risk')).toHaveText('Low');
    await expect(page.locator('#mcp-detail-summary')).toHaveText('Read published campaign metadata.');
    await expect.poll(()=>requests.length).toBe(0);
    await page.getByRole('button',{name:'Close'}).click();
    await expect(page.getByRole('dialog')).toBeHidden();
    await page.getByRole('button',{name:'Campaign tool details'}).click();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toBeHidden();
});

test('MCP details are inserted as text, not executable HTML',async({page})=>{
    await page.goto('/up');
    await page.setContent([
        '<html><body><div data-mcp-manager>',
        '<button id="unsafe" data-mcp-tool-details>Open tool</button>',
        '<dialog id="mcp-tool-dialog" aria-labelledby="mcp-dialog-title">',
        '<h3 id="mcp-dialog-title"></h3><div id="mcp-detail-id"></div>',
        '<div id="mcp-detail-scope"></div><div id="mcp-detail-risk"></div>',
        '<p id="mcp-detail-summary"></p><button data-mcp-dialog-close>Close</button>',
        '</dialog></div></body></html>'
    ].join(''));
    await page.evaluate(()=>{
        const button=document.querySelector('#unsafe');
        button.dataset.title='<img src=x onerror=alert(1)>';
        button.dataset.name='unsafe<script>';
        button.dataset.scope='<script>';
        button.dataset.risk='Low';
        button.dataset.summary='<svg onload=alert(1)>';
    });
    await page.addScriptTag({path:'public/js/mcp-management.js'});
    await page.getByRole('button',{name:'Open tool'}).click();
    await expect(page.locator('#mcp-dialog-title')).toContainText('<img');
    await expect(page.locator('#mcp-detail-summary')).toContainText('<svg');
    await expect(page.locator('#mcp-tool-dialog img')).toHaveCount(0);
    await expect(page.locator('#mcp-tool-dialog svg')).toHaveCount(0);
});
