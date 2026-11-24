#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
RELEASE="${1:?Supply the reviewed 40-character release SHA}"
[[ "$RELEASE" =~ ^[0-9a-f]{40}$ ]] || { echo "MCP_INVALID_RELEASE" >&2; exit 2; }
test "$(id -u)" -eq 0 || { echo "MCP_ROOT_REQUIRED" >&2; exit 2; }
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
REPO="dombong2026/learncambodia"
BRANCH="feature/mcp-sidebar-password-direct-gateway-20261124"
BASE="cbc4b72899ce4b8ec35390d90ec6fad58e91e8ce"
SITE="/www/wwwroot/learncambodia.com"
PHP="/www/server/php/85/bin/php"
MARKER="$SITE/storage/app/private/learncambodia-mcp-direct-activation.txt"
ENV="$SITE/.env"
NEW=(
 app/Http/Controllers/Workspace/McpAdminSessionController.php
 resources/views/workspace/admin/mcp-management.blade.php
 public/js/mcp-management.js
 public/css/mcp-management.css
)
MODIFIED=(routes/web.php app/Support/WorkspaceMenu.php)
for c in gh python3 curl flock cmp runuser; do
 command -v "$c" >/dev/null || { echo "MCP_MISSING_COMMAND=$c" >&2; exit 2; }
done
test -x "$PHP" && test -f "$SITE/artisan" && test -f "$ENV" && test ! -L "$ENV"
grep -Fxq "SOURCE_COMMIT=$BASE" \
 "$SITE/storage/app/private/learncambodia-mcp-browser-bridge.txt" || {
 echo "MCP_DEPLOYED_BRIDGE_BASELINE_MISSING" >&2; exit 2;
}
test ! -e "$MARKER" || { echo MCP_DIRECT_ALREADY_INSTALLED; exit 0; }
for f in "${NEW[@]}" "${MODIFIED[@]}"; do
 test -f "$SITE/$f" && test ! -L "$SITE/$f" || {
  echo "MCP_LIVE_FILE_MISSING_OR_SYMLINK=$f" >&2; exit 2;
 }
done
mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/mcp-direct.lock
flock -n 9 || { echo "MCP_DEPLOY_LOCKED" >&2; exit 2; }
test "$(gh api "repos/$REPO/git/ref/heads/$BRANCH" --jq '.object.sha')" = "$RELEASE" || {
 echo "MCP_RELEASE_BRANCH_MOVED" >&2; exit 2;
}
gh api "repos/$REPO/actions/runs?head_sha=$RELEASE&per_page=100" | python3 -c '
import json,sys
runs=json.load(sys.stdin).get("workflow_runs",[])
if not any(r.get("name")=="MCP Direct Bridge Menu Password Acceptance"
 and r.get("status")=="completed" and r.get("conclusion")=="success" for r in runs):
 raise SystemExit("MCP_DIRECT_ACCEPTANCE_NOT_PASSED")
print("PINNED_MCP_DIRECT_CI=PASS")
'
STAGE="$(mktemp -d /var/backups/learncambodia/mcp-direct.XXXXXXXX)"
mkdir -p "$STAGE/backup" "$STAGE/patch" "$STAGE/source" "$STAGE/new"
echo "MCP_BACKUP_DIR=$STAGE"
cp -p "$ENV" "$STAGE/backup/.env"
for i in "${!NEW[@]}"; do
 f="${NEW[$i]}"
 cp -p "$SITE/$f" "$STAGE/backup/file-$i"
 gh api -H 'Accept: application/vnd.github.raw+json' \
  "repos/$REPO/contents/$f?ref=$BASE" > "$STAGE/source/$i"
 cmp -s "$STAGE/backup/file-$i" "$STAGE/source/$i" || {
  echo "MCP_LIVE_FILE_CHANGED=$f" >&2; exit 2;
 }
 gh api -H 'Accept: application/vnd.github.raw+json' \
  "repos/$REPO/contents/$f?ref=$RELEASE" > "$STAGE/new/$i"
 test -s "$STAGE/new/$i"
done
cp -p "$SITE/routes/web.php" "$STAGE/backup/route"
cp -p "$SITE/app/Support/WorkspaceMenu.php" "$STAGE/backup/menu"
PATCHER="$STAGE/mcp-patcher.py"
GUARD="$STAGE/mcp-route-assert.php"
gh api -H 'Accept: application/vnd.github.raw+json' \
 "repos/$REPO/contents/support/operator/learncambodia-mcp-activation-patcher.py?ref=$RELEASE" > "$PATCHER"
gh api -H 'Accept: application/vnd.github.raw+json' \
 "repos/$REPO/contents/support/operator/learncambodia-mcp-activation-route-assert.php?ref=$RELEASE" > "$GUARD"
test -s "$PATCHER" && test -s "$GUARD"
python3 -B "$PATCHER" "$STAGE"
"$PHP" -l "$GUARD" >/dev/null
for i in 0 1; do "$PHP" -l "$STAGE/new/$i" >/dev/null; done
for f in route menu; do "$PHP" -l "$STAGE/patch/$f" >/dev/null; done
python3 - "$STAGE/backup/.env" "$STAGE/new/.env" <<'PY'
from pathlib import Path
import re,sys
src=Path(sys.argv[1]).read_text()
for key in ("MCP_GATEWAY_ENABLED","MCP_REMOTE_WRITES_ENABLED"):
    regex=re.compile(r"(?m)^"+key+r"=.*$")
    found=regex.findall(src)
    if len(found)>1: raise SystemExit("MCP_ENV_DUPLICATE_KEY:"+key)
    if found: src=regex.sub(key+"=true",src)
    else: src=src.rstrip("\n")+"\n"+key+"=true\n"
Path(sys.argv[2]).write_text(src)
print("MCP_DIRECT_FLAGS_PREPARED=PASS")
PY
replace(){
 local src="$1" dst="$2" temp
 temp="$(mktemp "$(dirname "$dst")/.lc-mcp.XXXXXXXX")"
 cat "$src" > "$temp"
 chown --reference="$dst" "$temp"
 chmod --reference="$dst" "$temp"
 mv -f -- "$temp" "$dst"
}
applied=0
complete=0
rollback(){
 local rc=$?
 trap - ERR EXIT
 if test "$applied" -eq 1 && test "$complete" -eq 0; then
  set +e
  echo MCP_DIRECT_ROLLBACK=STARTED >&2
  for i in "${!NEW[@]}"; do
   replace "$STAGE/backup/file-$i" "$SITE/${NEW[$i]}"
  done
  replace "$STAGE/backup/route" "$SITE/routes/web.php"
  replace "$STAGE/backup/menu" "$SITE/app/Support/WorkspaceMenu.php"
  replace "$STAGE/backup/.env" "$ENV"
  rm -f -- "$MARKER"
  cd "$SITE"
  runuser -u www -- "$PHP" artisan config:clear >/dev/null 2>&1
  runuser -u www -- "$PHP" artisan route:clear >/dev/null 2>&1
  runuser -u www -- "$PHP" artisan view:clear >/dev/null 2>&1
  runuser -u www -- "$PHP" artisan view:cache >/dev/null 2>&1
  echo MCP_DIRECT_ROLLBACK=RESTORED >&2
 fi
 exit "$rc"
}
trap rollback ERR EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
applied=1
for i in "${!NEW[@]}"; do
 replace "$STAGE/new/$i" "$SITE/${NEW[$i]}"
done
replace "$STAGE/patch/route" "$SITE/routes/web.php"
replace "$STAGE/patch/menu" "$SITE/app/Support/WorkspaceMenu.php"
replace "$STAGE/new/.env" "$ENV"
cd "$SITE"
runuser -u www -- "$PHP" artisan config:clear
runuser -u www -- "$PHP" artisan route:clear
runuser -u www -- "$PHP" artisan view:clear
runuser -u www -- "$PHP" artisan view:cache
"$PHP" "$GUARD"
for path in / /css/mcp-management.css /js/mcp-management.js; do
 status="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
  --resolve learncambodia.com:443:127.0.0.1 \
  -o /dev/null -w '%{http_code}' "https://learncambodia.com$path")"
 test "$status" = 200 || { echo "MCP_HTTP_FAILURE=$path:$status" >&2; exit 2; }
done
status="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
  --resolve learncambodia.com:443:127.0.0.1 \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize"}' \
  -o /dev/null -w '%{http_code}' https://learncambodia.com/api/mcp)"
test "$status" = 401 || { echo "MCP_ANONYMOUS_ACCESS_NOT_DENIED=$status" >&2; exit 2; }
printf 'SOURCE_COMMIT=%s\nBACKUP_DIR=%s\n' "$RELEASE" "$STAGE" > "$STAGE/marker"
cp "$STAGE/marker" "$MARKER"
chown root:www "$MARKER"
chmod 0640 "$MARKER"
complete=1
echo MCP_DIRECT_ACTIVATION=PASS
echo MCP_SIDEBAR_LINK=PASS
echo MCP_PASSWORD_PROMPT=ENABLED
echo MCP_DIRECT_BEARER_GATEWAY=ENABLED
echo MCP_SCOPED_DRAFT_CRUD=ENABLED
echo MCP_OAUTH_AUTOMATIC_CALLBACK=NOT_YET_IMPLEMENTED
echo MCP_PUBLISH_INSTALL_PUSH=BLOCKED
echo "MCP_BACKUP_DIR=$STAGE"
echo "MCP_URL=https://learncambodia.com/dashboard/system-settings/mcp?lang=en"
