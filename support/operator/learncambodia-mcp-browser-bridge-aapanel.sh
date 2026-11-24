#!/usr/bin/env bash
# LearnCambodia browser-admin MCP bridge. Fails closed. NO OAuth claim or remote enablement.
set -Eeuo pipefail
umask 077
RELEASE="${1:?Pinned reviewed source commit required}"
[[ "$RELEASE" =~ ^[0-9a-f]{40}$ ]] || { echo INVALID_MCP_BRIDGE_COMMIT >&2; exit 2; }
test "$(id -u)" -eq 0 || { echo MCP_BRIDGE_ROOT_REQUIRED >&2; exit 2; }
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
REPO="dombong2026/learncambodia"
BRANCH="feature/learncambodia-browser-admin-mcp-bridge-20261124"
BASE="aea1ba93185404a60cda79f05edac2153688ca3d"
SITE="/www/wwwroot/learncambodia.com"
PHP="/www/server/php/85/bin/php"
BASE_MARKER="$SITE/storage/app/private/learncambodia-mcp-management-foundation.txt"
MARKER="$SITE/storage/app/private/learncambodia-mcp-browser-bridge.txt"
MIGRATION="database/migrations/2026_11_24_120000_create_mcp_browser_bridge_records.php"
NEW_FILES=(
  app/Http/Controllers/Workspace/McpAdminSessionController.php
  app/Http/Controllers/Workspace/McpBrowserApprovalController.php
  app/Http/Controllers/Api/McpBridgeController.php
  app/Support/McpActionConsent.php
  app/Support/McpCrudEngine.php
  app/Models/McpActionApproval.php
  app/Models/McpActionAudit.php
  resources/views/workspace/admin/mcp-approval.blade.php
  "$MIGRATION"
)
REPLACE_FILES=(
  app/Http/Controllers/Workspace/AdminMcpManagementController.php
  resources/views/workspace/admin/mcp-management.blade.php
  public/js/mcp-management.js
  public/css/mcp-management.css
)
PATCH_FILES=(routes/web.php routes/api.php)
for cmd in gh python3 curl flock cmp runuser; do
 command -v "$cmd" >/dev/null || { echo "MCP_MISSING_TOOL=$cmd" >&2; exit 2; }
done
test -x "$PHP" && test "$("$PHP" -r 'echo PHP_VERSION_ID;')" -ge 80401
test -f "$SITE/artisan" && test -f "$BASE_MARKER" &&
 grep -Fxq "SOURCE_COMMIT=$BASE" "$BASE_MARKER" || {
  echo "MCP_FOUNDATION_MISSING_OR_DRIFTED_DEPLOY_FOUNDATION_FIRST" >&2; exit 2;
}
test ! -e "$MARKER" || { echo MCP_BRIDGE_ALREADY_DEPLOYED; exit 0; }
for file in "${NEW_FILES[@]}"; do
 test ! -e "$SITE/$file" || { echo "NEW_PATH_ALREADY_EXISTS=$file" >&2; exit 2; }
done
for file in "${REPLACE_FILES[@]}" "${PATCH_FILES[@]}"; do
 test -f "$SITE/$file" && test ! -L "$SITE/$file" || {
   echo "UNSAFE_LIVE_PATH=$file" >&2; exit 2;
 }
done
mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/mcp-browser-bridge.lock
flock -n 9 || { echo MCP_DEPLOY_LOCK_BUSY >&2; exit 2; }
test "$(gh api "repos/$REPO/git/ref/heads/$BRANCH" --jq '.object.sha')" = "$RELEASE" || {
 echo MCP_BRANCH_HEAD_MOVED >&2; exit 2;
}
gh api "repos/$REPO/actions/runs?head_sha=$RELEASE&per_page=100" | python3 -c '
import json,sys
runs=json.load(sys.stdin).get("workflow_runs",[])
name="MCP Browser Admin CRUD Bridge acceptance"
if not any(r.get("name")==name and r.get("status")=="completed"
 and r.get("conclusion")=="success" for r in runs):
 raise SystemExit("PINNED_MCP_BROWSER_BRIDGE_CI_NOT_PASSED")
print("PINNED_MCP_BROWSER_BRIDGE_CI=PASS")
'
cd "$SITE"
# An explicit separate operation must enable transport only after OAuth review.
"$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config("mcp.gateway_enabled") || config("mcp.remote_writes_enabled"))
 throw new RuntimeException("MCP gateway/write flags must remain off during this release.");
if(\Illuminate\Support\Facades\Schema::hasTable("mcp_action_approvals")
 || \Illuminate\Support\Facades\Schema::hasTable("mcp_action_audits"))
 throw new RuntimeException("MCP approval/audit tables already exist; manual schema review required.");
echo "MCP_FEATURE_FLAGS=DISABLED\nMCP_NEW_TABLES_ABSENT=PASS\n";
'
STAGE="$(mktemp -d /var/backups/learncambodia/mcp-bridge.XXXXXXXX)"
mkdir -p "$STAGE/new" "$STAGE/base" "$STAGE/backup" "$STAGE/patch"
echo "MCP_BRIDGE_BACKUP_DIR=$STAGE"
fetch(){
  gh api -H 'Accept: application/vnd.github.raw+json' \
  "repos/$REPO/contents/$1?ref=$2" > "$3"
  test -s "$3"
}
for i in "${!NEW_FILES[@]}"; do
 fetch "${NEW_FILES[$i]}" "$RELEASE" "$STAGE/new/$i"
done
for i in "${!REPLACE_FILES[@]}"; do
 file="${REPLACE_FILES[$i]}"
 cp -p "$SITE/$file" "$STAGE/backup/replace-$i"
 fetch "$file" "$BASE" "$STAGE/base/$i"
 cmp -s "$STAGE/base/$i" "$STAGE/backup/replace-$i" || {
  echo "FOUNDATION_FILE_DRIFT=$file" >&2; exit 2;
 }
 fetch "$file" "$RELEASE" "$STAGE/new/replace-$i"
done
for i in "${!PATCH_FILES[@]}"; do
 cp -p "$SITE/${PATCH_FILES[$i]}" "$STAGE/backup/patch-$i"
done
PATCHER="$STAGE/mcp-bridge-patcher.py"
gh api -H "Accept: application/vnd.github.raw+json" \
  "repos/$REPO/contents/support/operator/learncambodia-mcp-browser-bridge-patcher.py?ref=$RELEASE" > "$PATCHER"
test -s "$PATCHER"
python3 -B "$PATCHER" "$STAGE"
for i in "${!NEW_FILES[@]}"; do
 case "${NEW_FILES[$i]}" in *.php) "$PHP" -l "$STAGE/new/$i" >/dev/null;; esac
done
for i in "${!REPLACE_FILES[@]}"; do
 case "${REPLACE_FILES[$i]}" in *.php) "$PHP" -l "$STAGE/new/replace-$i" >/dev/null;; esac
done
for i in 0 1; do "$PHP" -l "$STAGE/patch/$i" >/dev/null; done
echo MCP_BRIDGE_SOURCES=PASS
replace(){
 local src="$1" dest="$2" tmp
 tmp="$(mktemp "$(dirname "$dest")/.lc-mcp-bridge.XXXXXXXX")"
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
  echo MCP_BRIDGE_ROLLBACK=STARTED >&2
  for i in "${!REPLACE_FILES[@]}"; do
   replace "$STAGE/backup/replace-$i" "$SITE/${REPLACE_FILES[$i]}"
  done
  for i in "${!PATCH_FILES[@]}"; do
   replace "$STAGE/backup/patch-$i" "$SITE/${PATCH_FILES[$i]}"
  done
  for file in "${NEW_FILES[@]}"; do rm -f -- "$SITE/$file"; done
  rm -f -- "$MARKER"
  cd "$SITE"
  runuser -u www -- "$PHP" artisan route:clear >/dev/null 2>&1
  runuser -u www -- "$PHP" artisan view:clear >/dev/null 2>&1
  runuser -u www -- "$PHP" artisan view:cache >/dev/null 2>&1
  echo MCP_BRIDGE_ROLLBACK=PREVIOUS_UI_RESTORED >&2
  echo MCP_BRIDGE_NEW_TABLES_RETAINED_FOR_REVIEW=YES >&2
 fi
 exit "$rc"
}
trap rollback ERR EXIT
trap 'exit 143' TERM
trap 'exit 130' INT
applied=1
for i in "${!REPLACE_FILES[@]}"; do
 replace "$STAGE/new/replace-$i" "$SITE/${REPLACE_FILES[$i]}"
done
for i in "${!PATCH_FILES[@]}"; do
 replace "$STAGE/patch/$i" "$SITE/${PATCH_FILES[$i]}"
done
for i in "${!NEW_FILES[@]}"; do
 install -m 0644 "$STAGE/new/$i" "$SITE/${NEW_FILES[$i]}"
done
cd "$SITE"
runuser -u www -- "$PHP" artisan migrate \
 --path="$MIGRATION" --force --no-interaction
chown www:www storage/framework/views
chmod 0750 storage/framework/views
runuser -u www -- "$PHP" artisan route:clear
runuser -u www -- "$PHP" artisan view:clear
runuser -u www -- "$PHP" artisan view:cache
GUARD="$STAGE/mcp-bridge-route-assert.php"
gh api -H "Accept: application/vnd.github.raw+json" \
  "repos/$REPO/contents/support/operator/learncambodia-mcp-browser-bridge-route-assert.php?ref=$RELEASE" > "$GUARD"
test -s "$GUARD"
"$PHP" -l "$GUARD" >/dev/null
"$PHP" "$GUARD"
for path in / /css/mcp-management.css /js/mcp-management.js /api/mcp/auth-guide; do
 code="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
   --resolve learncambodia.com:443:127.0.0.1 \
   -o /dev/null -w '%{http_code}' "https://learncambodia.com$path")"
 test "$code" = 200 || { echo "MCP_HTTP_FAILURE=$path:$code" >&2; exit 2; }
done
guest="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
 --resolve learncambodia.com:443:127.0.0.1 \
 -H 'Accept: application/json' -H 'Content-Type: application/json' \
 --data '{"jsonrpc":"2.0","method":"initialize","id":1}' \
 -o /dev/null -w '%{http_code}' https://learncambodia.com/api/mcp)"
test "$guest" = 401 || { echo "MCP_UNAUTHENTICATED_RPC_NOT_DENIED=$guest" >&2; exit 2; }
printf 'SOURCE_COMMIT=%s\nBACKUP_DIR=%s\n' "$RELEASE" "$STAGE" > "$STAGE/marker"
cp "$STAGE/marker" "$MARKER"
chown root:www "$MARKER"
chmod 0640 "$MARKER"
complete=1
echo MCP_BROWSER_BRIDGE_DEPLOY=PASS
echo MCP_GATEWAY=DISABLED
echo MCP_REMOTE_WRITES=DISABLED
echo MCP_BROWSER_ADMIN_CONSENT=ENABLED
echo MCP_SCOPED_CRUD_DRAFTS=INSTALLED_BUT_LOCKED
echo MCP_DESTRUCTIVE_ACTION_APPROVAL=ENABLED
echo MCP_AUDIT_TABLES=CREATED
echo MCP_EXISTING_CAMPAIGN_RECORDS_UNCHANGED=PASS
echo "MCP_BACKUP_DIR=$STAGE"
echo "MCP_MANAGEMENT_URL=https://learncambodia.com/dashboard/system-settings/mcp?lang=en"
