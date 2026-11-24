#!/usr/bin/env python3
"""Fail-closed additive browser-admin MCP bridge route overlay for aaPanel.

Read STAGE/backup/patch-0 (web) + patch-1 (API), write STAGE/patch/0..1.
Never change the operator's files. Reject route conflicts or unfamiliar auth.
"""
from pathlib import Path
import re
import sys

stage = Path(sys.argv[1])
web = (stage / "backup/patch-0").read_text()
api = (stage / "backup/patch-1").read_text()

# The foundation release on production installs a separate route group with
# explicit auth+role:admin. Do not assume the tracked legacy system-settings
# group or its Social Auth text matches the live operator checkout.
route = re.compile(
    r"Route::get\(\s*['\"]/mcp['\"]\s*,\s*"
    r"\[\\App\\Http\\Controllers\\Workspace\\AdminMcpManagementController::class,"
    r"\s*['\"]index['\"]\s*\]\s*\)\s*"
    r"->name\(\s*['\"]mcp['\"]\s*\);",
    re.S,
)
matches = list(route.finditer(web))
if len(matches) != 1:
    raise SystemExit("MCP_FOUNDATION_ADMIN_ROUTE_NOT_UNIQUE")
m = matches[0]

# Inspect the adjacent explicitly authenticated group, not a public /mcp route.
before = web[max(0, m.start()-550):m.start()]
after = web[m.end():m.end()+150]
auth_group = re.search(
    r"Route::middleware\(\s*\[\s*['\"]auth['\"]\s*,\s*['\"]role:admin['\"]\s*\]\s*\)"
    r"\s*->prefix\(\s*['\"]dashboard/system-settings['\"]\s*\)"
    r"\s*->name\(\s*['\"]admin\.system-settings\.['\"]\s*\)"
    r"\s*->group\(\s*function\s*\(\s*\)\s*:\s*void\s*\{",
    before, re.S
)
if not auth_group or not re.match(r"\s*\}\);", after):
    raise SystemExit("MCP_ADMIN_ROUTE_GROUP_NOT_RECOGNIZED")

for token in (
    "mcp.sessions.issue", "mcp.sessions.revoke",
    "mcp.approvals.show", "mcp.approvals.decide",
):
    if token in web:
        raise SystemExit("MCP_WEB_ROUTE_ALREADY_REGISTERED:"+token)

# Insert before the foundation group's closing brace.
added = """
        Route::post('/mcp/sessions', [\\App\\Http\\Controllers\\Workspace\\McpAdminSessionController::class, 'issue'])
            ->middleware(['password.confirm','throttle:5,1'])->name('mcp.sessions.issue');
        Route::delete('/mcp/sessions/{token}', [\\App\\Http\\Controllers\\Workspace\\McpAdminSessionController::class, 'revoke'])
            ->whereNumber('token')->middleware('throttle:10,1')->name('mcp.sessions.revoke');
        Route::get('/mcp/approvals/{approval}', [\\App\\Http\\Controllers\\Workspace\\McpBrowserApprovalController::class, 'show'])
            ->middleware('password.confirm')->name('mcp.approvals.show');
        Route::post('/mcp/approvals/{approval}', [\\App\\Http\\Controllers\\Workspace\\McpBrowserApprovalController::class, 'decide'])
            ->middleware(['password.confirm','throttle:5,1'])->name('mcp.approvals.decide');
"""
web = web[:m.end()] + "\n" + added + web[m.end():]
(stage / "patch/0").write_text(web)
print("MCP_ADMIN_SESSION_ROUTE_OVERLAY=PASS")

# API routes must remain behind the existing explicit Sanctum auth middleware.
use_anchor = "use Illuminate\\Support\\Facades\\Route;"
api_anchor = "Route::get('/navigation/categories', [CatalogController::class, 'navigationCategories'])"
if api.count(use_anchor) != 1 or api.count(api_anchor) != 1:
    raise SystemExit("MCP_API_ROUTE_ANCHOR_DRIFT")
if "name('mcp.rpc')" in api or "McpBridgeController;" in api:
    raise SystemExit("MCP_API_ROUTE_ALREADY_PRESENT")
api_insert = """Route::get('/mcp/auth-guide', [McpBridgeController::class, 'authGuide'])
    ->middleware('throttle:20,1')->name('mcp.auth-guide');
Route::post('/mcp', [McpBridgeController::class, 'handle'])
    ->middleware(['auth:sanctum','throttle:60,1'])->name('mcp.rpc');
Route::post('/mcp/media', [McpBridgeController::class, 'upload'])
    ->middleware(['auth:sanctum','throttle:15,1'])->name('mcp.media.upload');

"""
api = api.replace(
    use_anchor, use_anchor + "\nuse App\\Http\\Controllers\\Api\\McpBridgeController;", 1
).replace(api_anchor, api_insert + api_anchor, 1)
(stage / "patch/1").write_text(api)
print("MCP_API_ROUTE_OVERLAY=PASS")
print("MCP_ADDITIVE_ROUTE_PATCHES=PASS")
