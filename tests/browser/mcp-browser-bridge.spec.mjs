import {test,expect} from '@playwright/test';

test('browser-admin permission consent displays short-lived token once and supports revoke',async({page})=>{
    await page.route('**/up',route=>route.fulfill({
        status:200,contentType:'text/html',body:'<html><body>fixture</body></html>'
    }));
    let issuedRequests=0;
    let revocations=0;
    await page.route('**/dashboard/system-settings/mcp/sessions',route=>{
        issuedRequests++;
        const data=route.request().postDataJSON();
        expect(data.client_name).toBe('LearnCambodia Admin MCP');
        expect(data.scopes).toContain('mcp:read');
        expect(data.confirmation).toBe('CONNECT');
        return route.fulfill({
            status:201,contentType:'application/json',
            body:JSON.stringify({
                status:'issued',session_id:77,access_token:'77|one-time-fixture-token',
                token_type:'Bearer',expires_at:'2026-11-24T12:30:00+07:00',
                scopes:['mcp:read'],receipt_id:'fixture-audit'
            })
        });
    });
    await page.route('**/dashboard/system-settings/mcp/sessions/77',route=>{
        revocations++;
        return route.fulfill({
            status:200,contentType:'application/json',
            body:JSON.stringify({status:'revoked',session_id:77})
        });
    });
    await page.goto('/up');
    await page.setContent([
        '<html><head><meta name="csrf-token" content="test-csrf"></head><body>',
        '<div data-mcp-manager><dialog id="mcp-tool-dialog"><h3 id="mcp-dialog-title"></h3>',
        '<p id="mcp-detail-id"></p><p id="mcp-detail-scope"></p><p id="mcp-detail-risk"></p>',
        '<p id="mcp-detail-summary"></p><button data-mcp-dialog-close>Close</button></dialog>',
        '<section class="mcp-browser-section" data-mcp-issue-url="/dashboard/system-settings/mcp/sessions"',
        ' data-mcp-revoke-prefix="/dashboard/system-settings/mcp/sessions">',
        '<button data-mcp-open-auth>Authenticate in Browser</button>',
        '<table><tbody data-mcp-sessions><tr data-mcp-empty><td>None</td></tr></tbody></table></section>',
        '<dialog id="mcp-browser-auth-dialog">',
        '<button data-mcp-auth-close type="button">Close authentication</button>',
        '<form id="mcp-browser-auth-form">',
        '<input id="mcp-client-name" name="client_name" value="LearnCambodia Admin MCP" required minlength="3">',
        '<input name="scopes[]" type="checkbox" value="mcp:read" checked>',
        '<input name="scopes[]" type="checkbox" value="mcp:write">',
        '<select id="mcp-expiry"><option value="30">30m</option></select>',
        '<input type="checkbox" id="mcp-consent-accept" required>',
        '<button type="submit" data-mcp-connect-submit>Approve browser session</button>',
        '</form><div id="mcp-browser-issued" hidden>',
        '<textarea id="mcp-issued-token" readonly></textarea>',
        '<button type="button" data-mcp-copy-token>Copy token</button>',
        '</div><p id="mcp-browser-feedback"></p></dialog>',
        '</div></body></html>'
    ].join(''));
    await page.addScriptTag({path:'public/js/mcp-management.js'});
    await page.getByRole('button',{name:'Authenticate in Browser'}).click();
    await expect(page.locator('#mcp-browser-auth-dialog')).toBeVisible();
    await page.locator('#mcp-consent-accept').check();
    await page.getByRole('button',{name:'Approve browser session'}).click();
    await expect(page.locator('#mcp-issued-token')).toHaveValue('77|one-time-fixture-token');
    expect(issuedRequests).toBe(1);
    await expect(page.locator('[data-mcp-session-id="77"]')).toBeVisible();
    await page.getByRole('button',{name:'Close authentication'}).click();
    await expect(page.locator('#mcp-issued-token')).toHaveValue('');
    await expect(page.locator('#mcp-browser-auth-dialog')).toBeHidden();
    page.once('dialog',dialog=>dialog.accept());
    await page.getByRole('button',{name:'Revoke'}).click();
    expect(revocations).toBe(1);
    await expect(page.locator('[data-mcp-session-id="77"]')).toHaveCount(0);
});

test('consent cannot issue token without explicit approval checkbox',async({page})=>{
    await page.route('**/up',route=>route.fulfill({
        status:200,contentType:'text/html',body:'<html></html>'
    }));
    let requests=0;
    await page.route('**/dashboard/system-settings/mcp/sessions',route=>{
        requests++;return route.fulfill({status:400,body:'{}'});
    });
    await page.goto('/up');
    await page.setContent([
        '<html><head><meta name="csrf-token" content="csrf"></head><body>',
        '<div data-mcp-manager><dialog id="mcp-tool-dialog"><h3 id="mcp-dialog-title"></h3>',
        '<span id="mcp-detail-id"></span><span id="mcp-detail-scope"></span>',
        '<span id="mcp-detail-risk"></span><span id="mcp-detail-summary"></span></dialog>',
        '<section class="mcp-browser-section" data-mcp-issue-url="/dashboard/system-settings/mcp/sessions"',
        ' data-mcp-revoke-prefix="/dashboard/system-settings/mcp/sessions">',
        '<button data-mcp-open-auth>Authenticate in Browser</button></section>',
        '<dialog id="mcp-browser-auth-dialog"><form id="mcp-browser-auth-form">',
        '<input id="mcp-client-name" required value="Test client">',
        '<input name="scopes[]" type="checkbox" value="mcp:read" checked>',
        '<input id="mcp-consent-accept" type="checkbox" required>',
        '<select id="mcp-expiry"><option value="30">30</option></select>',
        '<button type="submit" data-mcp-connect-submit>Approve browser session</button></form>',
        '<div id="mcp-browser-issued" hidden><textarea id="mcp-issued-token"></textarea></div>',
        '<p id="mcp-browser-feedback"></p></dialog></div></body></html>'
    ].join(''));
    await page.addScriptTag({path:'public/js/mcp-management.js'});
    await page.getByRole('button',{name:'Authenticate in Browser'}).click();
    await page.getByRole('button',{name:'Approve browser session'}).click();
    expect(requests).toBe(0);
    await expect(page.locator('#mcp-issued-token')).toHaveValue('');
});
