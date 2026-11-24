#!/usr/bin/env python3
"""Surgically add the MCP password-confirm route and sidebar fallback to live Laravel.
Read stage/backup/route and /menu, write stage/patch/route and /menu.
Never alter the real live file directly.
"""
from pathlib import Path
import re
import sys

stage=Path(sys.argv[1])
web=(stage/"backup/route").read_text()
menu=(stage/"backup/menu").read_text()

route_anchor=re.compile(
    r"(?P<indent>^[ \t]*)Route::post\('/mcp/sessions', "
    r"\[\\App\\Http\\Controllers\\Workspace\\McpAdminSessionController::class, 'issue'\]\)"
    r"\s*->middleware\(\['password.confirm','throttle:5,1'\]\)"
    r"->name\('mcp.sessions.issue'\);",
    re.M
)
matches=list(route_anchor.finditer(web))
if len(matches)!=1:
    raise SystemExit("MCP_SESSION_ROUTE_DRIFT: expected exactly one protected issuance route")
if "mcp.confirm-password" in web or "/mcp/confirm-password" in web:
    raise SystemExit("MCP_PASSWORD_ROUTE_ALREADY_PRESENT: manual review required")
match=matches[0]
addition=(
    "\n"+match.group("indent")+
    "Route::post('/mcp/confirm-password', "
    "[\\App\\Http\\Controllers\\Workspace\\McpAdminSessionController::class, 'confirmWebsitePassword'])\n"+
    match.group("indent")+"    ->middleware('throttle:5,1')->name('mcp.confirm-password');"
)
web=web[:match.end()]+addition+web[match.end():]
(stage/"patch/route").write_text(web)

anchor="            if ($items !== []) {\n                $groups[] = compact('key', 'title', 'icon', 'items');"
if menu.count(anchor)!=1:
    raise SystemExit("MCP_SIDEBAR_INSERTION_DRIFT")
if "&& $key === 'system-settings'" in menu and "Route::has('admin.system-settings.mcp')" in menu:
    raise SystemExit("MCP_SIDEBAR_FALLBACK_ALREADY_PRESENT")
fallback="""            // Preserve the MCP link when production caches an older workspaces config.
            if ($role === 'admin' && $key === 'system-settings'
                && Route::has('admin.system-settings.mcp')
                && !collect($items)->contains(fn (array $item): bool =>
                    ($item['route'] ?? null) === 'admin.system-settings.mcp')) {
                $items[] = [
                    'key' => 'mcp',
                    'label' => 'MCP Management',
                    'route' => 'admin.system-settings.mcp',
                    'icon' => 'terminal',
                    'section_title' => 'System Settings',
                ];
            }
"""
menu=menu.replace(anchor,fallback+anchor,1)
(stage/"patch/menu").write_text(menu)
print("MCP_PASSWORD_ROUTE_PATCH=PASS")
print("MCP_SIDEBAR_LINK_PATCH=PASS")
