#!/usr/bin/env python3
"""Minimal and fail-closed MCP foundation route/menu/icon overlays.

Takes STAGE with backup/0 (routes/web.php), backup/1 (config/workspaces.php),
backup/2 (app/Support/WorkspaceMenu.php). Writes patch/0..2; never alters live files.
"""
import json
import re
import sys
from pathlib import Path

stage = Path(sys.argv[1])
route_source = (stage / "backup/0").read_text()
menu_source = (stage / "backup/1").read_text()
icon_source = (stage / "backup/2").read_text()

# Never require the live site to have an exact spelling of a Social Auth route.
# Existing MCP routes are acceptable ONLY when they point at the intended admin
# controller. Otherwise add a standalone fully authenticated admin route; do NOT
# append a bare public GET route outside the existing route groups.
candidates = list(re.finditer(r"Route::get\s*\(\s*['\"]/mcp['\"]\s*,", route_source))
if len(candidates) > 1:
    raise SystemExit("MCP_ROUTE_DUPLICATE: manual review required")
if len(candidates) == 1:
    snippet = route_source[candidates[0].start():candidates[0].start() + 360]
    if not re.search(r"AdminMcpManagementController::class", snippet) or not re.search(
        r"->name\(\s*['\"]mcp['\"]\s*\)", snippet
    ):
        raise SystemExit("MCP_ROUTE_CONFLICT: existing /mcp route is not the expected controller")
    route_mode = "already_present"
else:
    if "?> " in route_source or re.search(r"\?>\s*$", route_source):
        raise SystemExit("MCP_ROUTE_END_TAG: manual review required")
    if "role:admin" not in route_source:
        raise SystemExit("MCP_ADMIN_MIDDLEWARE_UNRECOGNIZED: manual review required")
    route_source = route_source.rstrip() + """

// LearnCambodia MCP Management — independent admin-only route, no remote access.
Route::middleware(['auth', 'role:admin'])
    ->prefix('dashboard/system-settings')->name('admin.system-settings.')
    ->group(function (): void {
        Route::get('/mcp', [\\App\\Http\\Controllers\\Workspace\\AdminMcpManagementController::class, 'index'])
            ->name('mcp');
    });
"""
    route_mode = "dedicated_auth_admin_group"
(stage / "patch/0").write_text(route_source)

# Preserve the existing navigation JSON and formatting. Do not overwrite the
# whole live workspace configuration with a repository copy.
def config_sections(source):
    match = re.search(r"<<<'JSON'\s*\n(.*?)\nJSON\b", source, flags=re.S)
    if not match:
        raise SystemExit("MCP_MENU_JSON_FORMAT_UNRECOGNIZED")
    decoded = json.loads(match.group(1))
    if not isinstance(decoded, dict) or not isinstance(decoded.get("admin"), list):
        raise SystemExit("MCP_MENU_ADMIN_CONFIG_UNRECOGNIZED")
    found = [x for x in decoded["admin"] if isinstance(x, dict) and x.get("key") == "system-settings"]
    if len(found) != 1 or not isinstance(found[0].get("items"), list):
        raise SystemExit("MCP_MENU_ADMIN_SECTION_UNRECOGNIZED")
    return found[0]["items"]

items = config_sections(menu_source)
entry = [x for x in items if isinstance(x, dict)
         and (x.get("key") == "mcp" or x.get("route") == "admin.system-settings.mcp")]
if entry:
    if len(entry) != 1 or entry[0].get("key") != "mcp" \
       or entry[0].get("route") != "admin.system-settings.mcp":
        raise SystemExit("MCP_MENU_CONFLICT: manual review required")
    menu_mode = "already_present"
else:
    anchor_pattern = r'\{\s*"key"\s*:\s*"(?:cloud-storage|social-auth)"[^{}]*\}'
    candidates = list(re.finditer(anchor_pattern, menu_source))
    candidates = [m for m in candidates if '"route"' in m.group(0)]
    if not candidates:
        raise SystemExit("MCP_MENU_INSERTION_ANCHOR_MISSING")
    # Prefer Cloud Storage; fall back to Social Auth.
    candidates.sort(key=lambda m: (0 if '"cloud-storage"' in m.group(0) else 1, m.start()))
    chosen = candidates[0]
    addition = ',\n        {"key": "mcp", "label": "MCP Management", "route": "admin.system-settings.mcp"}'
    menu_source = menu_source[:chosen.end()] + addition + menu_source[chosen.end():]
    menu_mode = "added"
updated = config_sections(menu_source)
matches = [x for x in updated if isinstance(x, dict) and x.get("route") == "admin.system-settings.mcp"]
if len(matches) != 1 or matches[0].get("key") != "mcp":
    raise SystemExit("MCP_MENU_VALIDATION_FAILED")
(stage / "patch/1").write_text(menu_source)

# The icon mapping is the only other change. Keep all unrelated entries.
existing = re.findall(r"['\"]system-settings/mcp['\"]\s*=>\s*['\"]([^'\"]+)['\"]", icon_source)
if existing:
    if len(existing) != 1 or existing[0] != "terminal":
        raise SystemExit("MCP_ICON_CONFLICT: manual review required")
    icon_mode = "already_present"
else:
    anchor = re.search(
        r"(?m)^(\s*)['\"]system-settings/(?:cloud-storage|social-auth)['\"]\s*=>\s*['\"][^'\"]+['\"],\s*$",
        icon_source,
    )
    if not anchor:
        raise SystemExit("MCP_ICON_INSERTION_ANCHOR_MISSING")
    insert = "\n" + anchor.group(1) + "'system-settings/mcp' => 'terminal',"
    icon_source = icon_source[:anchor.end()] + insert + icon_source[anchor.end():]
    icon_mode = "added"
if len(re.findall(r"['\"]system-settings/mcp['\"]\s*=>", icon_source)) != 1:
    raise SystemExit("MCP_ICON_VALIDATION_FAILED")
(stage / "patch/2").write_text(icon_source)
print("MCP_ROUTE_OVERLAY=" + route_mode)
print("MCP_MENU_OVERLAY=" + menu_mode)
print("MCP_ICON_OVERLAY=" + icon_mode)
print("MCP_ADDITIVE_LIVE_PATCHES=PASS")
