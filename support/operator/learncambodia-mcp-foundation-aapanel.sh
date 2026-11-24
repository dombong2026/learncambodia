#!/usr/bin/env bash
# Admin-only MCP Management foundation. No remote endpoint, migrations or tokens.
set -Eeuo pipefail
umask 077
RELEASE="${1:?Pinned reviewed commit SHA required}"
[[ "$RELEASE" =~ ^[0-9a-f]{40}$ ]] || { echo MCP_INVALID_COMMIT >&2; exit 2; }
test "$(id -u)" -eq 0 || { echo MCP_ROOT_REQUIRED >&2; exit 2; }
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
REPO=dombong2026/learncambodia
BRANCH=feature/learncambodia-mcp-management-foundation-20261124
SITE=/www/wwwroot/learncambodia.com
PHP=/www/server/php/85/bin/php
MARKER="$SITE/storage/app/private/learncambodia-mcp-management-foundation.txt"
NEW_FILES=(
 config/mcp.php
 app/Support/McpToolCatalog.php
 app/Http/Controllers/Workspace/AdminMcpManagementController.php
 resources/views/workspace/admin/mcp-management.blade.php
 public/css/mcp-management.css
 public/js/mcp-management.js
)
PATCH_FILES=(
 routes/web.php
 config/workspaces.php
 app/Support/WorkspaceMenu.php
)
for tool in gh python3 curl flock runuser; do
 command -v "$tool" >/dev/null || { echo "MCP_MISSING_TOOL=$tool" >&2; exit 2; }
done
test -x "$PHP" && test "$("$PHP" -r 'echo PHP_VERSION_ID;')" -ge 80401
test -f "$SITE/artisan" && test -d "$SITE/vendor"
test ! -e "$MARKER" || { echo MCP_FOUNDATION_ALREADY_DEPLOYED; exit 0; }
for file in "${NEW_FILES[@]}"; do
 test ! -e "$SITE/$file" || { echo "MCP_EXISTING_NEW_FILE=$file" >&2; exit 2; }
done
for file in "${PATCH_FILES[@]}"; do
 test -f "$SITE/$file" && test ! -L "$SITE/$file" || { echo "MCP_UNSAFE_FILE=$file" >&2; exit 2; }
done
mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/learncambodia-mcp.lock
flock -n 9 || { echo MCP_DEPLOYMENT_LOCK_BUSY >&2; exit 2; }
HEAD="$(gh api "repos/$REPO/git/ref/heads/$BRANCH" --jq '.object.sha')"
test "$HEAD" = "$RELEASE" || { echo "MCP_BRANCH_MOVED=$HEAD" >&2; exit 2; }
gh api "repos/$REPO/actions/runs?head_sha=$RELEASE&per_page=100" | python3 -c '
import json,sys
runs=json.load(sys.stdin).get("workflow_runs",[])
if not any(r.get("name")=="MCP Management foundation acceptance"
 and r.get("status")=="completed" and r.get("conclusion")=="success" for r in runs):
 raise SystemExit("MCP_ACCEPTANCE_NOT_PASSED")
print("MCP_PINNED_CI=PASS")
'
STAGE="$(mktemp -d /var/backups/learncambodia/mcp-foundation.XXXXXXXX)"
mkdir -p "$STAGE/new" "$STAGE/patch" "$STAGE/backup"
echo "MCP_BACKUP_DIR=$STAGE"
for i in "${!NEW_FILES[@]}"; do
 gh api -H 'Accept: application/vnd.github.raw+json' \
  "repos/$REPO/contents/${NEW_FILES[$i]}?ref=$RELEASE" > "$STAGE/new/$i"
 test -s "$STAGE/new/$i"
done
for i in "${!PATCH_FILES[@]}"; do
 cp -p "$SITE/${PATCH_FILES[$i]}" "$STAGE/backup/$i"
done
python3 - "$STAGE" <<'PY'
from pathlib import Path
import sys
s=Path(sys.argv[1])
edits=[
 ("0","Route::get('/social-auth', 'socialAuth')->name('social-auth');",
 "\n            Route::get('/mcp', [\\App\\Http\\Controllers\\Workspace\\AdminMcpManagementController::class, 'index'])->name('mcp');"),
 ("1",'{"key": "cloud-storage", "label": "Cloud Storage", "route": "admin.system-settings.cloud-storage"}',
 ',\n        {"key": "mcp", "label": "MCP Management", "route": "admin.system-settings.mcp"}'),
 ("2","'system-settings/cloud-storage' => 'cloud-storage',",
 "\n            'system-settings/mcp' => 'terminal',")
]
for i,needle,extra in edits:
 old=(s/'backup'/i).read_text()
 if old.count(needle)!=1 or extra.strip() in old:
  raise SystemExit("MCP_LIVE_ANCHOR_DRIFT:"+i)
 (s/'patch'/i).write_text(old.replace(needle,needle+extra,1))
print("MCP_ADDITIVE_LIVE_PATCHES=PASS")
PY
for i in 0 1 2; do
 "$PHP" -l "$STAGE/new/$i" >/dev/null
 "$PHP" -l "$STAGE/patch/$i" >/dev/null
done
grep -Fq "oauth_ready' => false" "$STAGE/new/0"
grep -Fq "transport_ready' => false" "$STAGE/new/0"
grep -Fq 'isAdministrator()' "$STAGE/new/2"
echo MCP_SOURCE_GATES=PASS

replace(){
 local src="$1" dest="$2" tmp
 tmp="$(mktemp "$(dirname "$dest")/.lc-mcp.XXXXXXXX")"
 cat "$src" > "$tmp"
 chown --reference="$dest" "$tmp"
 chmod --reference="$dest" "$tmp"
 mv -f -- "$tmp" "$dest"
}
applied=0
complete=0
rollback(){
 local rc=$?
 trap - ERR EXIT
 if test "$applied" -eq 1 && test "$complete" -eq 0; then
  set +e
  echo MCP_FOUNDATION_ROLLBACK=STARTED >&2
  for i in "${!PATCH_FILES[@]}"; do
   replace "$STAGE/backup/$i" "$SITE/${PATCH_FILES[$i]}"
  done
  for file in "${NEW_FILES[@]}"; do rm -f -- "$SITE/$file"; done
  rm -f -- "$MARKER"
  cd "$SITE"
  runuser -u www -- "$PHP" artisan route:clear >/dev/null 2>&1
  runuser -u www -- "$PHP" artisan view:clear >/dev/null 2>&1
  runuser -u www -- "$PHP" artisan view:cache >/dev/null 2>&1
  echo MCP_FOUNDATION_ROLLBACK=RESTORED >&2
 fi
 exit "$rc"
}
trap rollback ERR EXIT
trap 'exit 143' TERM
trap 'exit 130' INT
applied=1
for i in "${!PATCH_FILES[@]}"; do
 replace "$STAGE/patch/$i" "$SITE/${PATCH_FILES[$i]}"
done
for i in "${!NEW_FILES[@]}"; do
 install -m 0644 "$STAGE/new/$i" "$SITE/${NEW_FILES[$i]}"
done
cd "$SITE"
test -d storage/framework/views && test ! -L storage/framework/views
chown www:www storage/framework/views
chmod 0750 storage/framework/views
runuser -u www -- "$PHP" artisan route:clear
runuser -u www -- "$PHP" artisan view:clear
runuser -u www -- "$PHP" artisan view:cache
"$PHP" artisan route:list --json | python3 -c '
import json,sys
routes=json.load(sys.stdin)
matched=[r for r in routes if r.get("name")=="admin.system-settings.mcp"]
if len(matched)!=1 or "GET" not in matched[0].get("method",""):
 raise SystemExit("MCP_ADMIN_ROUTE_MISSING")
middleware=" ".join(matched[0].get("middleware") or [])
if "role:admin" not in middleware:
 raise SystemExit("MCP_ADMIN_AUTH_MISSING")
print("MCP_ADMIN_ROUTE_AUTH=PASS")
'
for path in / /css/mcp-management.css /js/mcp-management.js; do
 code="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
   --resolve learncambodia.com:443:127.0.0.1 \
   -o /dev/null -w '%{http_code}' "https://learncambodia.com$path")"
 test "$code" = 200 || { echo "MCP_ASSET_FAILED=$path:$code" >&2; exit 2; }
done
guest="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
 --resolve learncambodia.com:443:127.0.0.1 \
 -o /dev/null -w '%{http_code}' https://learncambodia.com/dashboard/system-settings/mcp)"
test "$guest" = 302 || { echo "MCP_GUEST_NOT_REDIRECTED=$guest" >&2; exit 2; }
printf 'SOURCE_COMMIT=%s\nBACKUP_DIR=%s\n' "$RELEASE" "$STAGE" > "$STAGE/marker"
cp "$STAGE/marker" "$MARKER"
chown root:www "$MARKER"
chmod 0640 "$MARKER"
complete=1
echo MCP_MANAGEMENT_FOUNDATION_DEPLOY=PASS
echo MCP_REMOTE_TRANSPORT=DISABLED
echo MCP_REMOTE_WRITES=DISABLED
echo MCP_TOOL_CATALOG=PLANNED_ONLY
echo MCP_DATA_MUTATIONS=NONE
echo "MCP_BACKUP_DIR=$STAGE"
echo "MCP_URL=https://learncambodia.com/dashboard/system-settings/mcp?lang=en"
