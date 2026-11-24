#!/usr/bin/env bash
# Targeted, pinned UI repair. Never changes .env, routes, database or MCP scopes.
set -Eeuo pipefail
umask 077
RELEASE="${1:?Pinned reviewed SHA required}"
[[ "$RELEASE" =~ ^[0-9a-f]{40}$ ]] || { echo BAD_RELEASE >&2; exit 2; }
test "$(id -u)" -eq 0 || { echo ROOT_REQUIRED >&2; exit 2; }
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
REPO=dombong2026/learncambodia
BRANCH=fix/mcp-browser-password-cache-20261124
BASE=90595e4cf3a55681ad1b8ac8d3168096ea09c249
SITE=/www/wwwroot/learncambodia.com
PHP=/www/server/php/85/bin/php
FILE=resources/views/workspace/admin/mcp-management.blade.php
MARKER="$SITE/storage/app/private/learncambodia-mcp-password-cache-fix.txt"
for cmd in gh python3 sha256sum curl flock cmp runuser; do
 command -v "$cmd" >/dev/null || { echo "MISSING_TOOL=$cmd" >&2; exit 2; }
done
test -x "$PHP" && test -f "$SITE/artisan"
test -f "$SITE/$FILE" && test ! -L "$SITE/$FILE"
test -f "$SITE/public/js/mcp-management.js"
test -f "$SITE/public/css/mcp-management.css"
grep -Fxq "SOURCE_COMMIT=$BASE" \
 "$SITE/storage/app/private/learncambodia-mcp-direct-activation.txt" || {
 echo "EXPECTED_MCP_DEPLOYMENT_MISSING" >&2; exit 2;
}
test ! -e "$MARKER" || { echo ALREADY_APPLIED; exit 0; }
mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/mcp-password-ui.lock
flock -n 9 || { echo LOCK_BUSY >&2; exit 2; }
test "$(gh api "repos/$REPO/git/ref/heads/$BRANCH" --jq '.object.sha')" = "$RELEASE" || {
 echo RELEASE_BRANCH_MOVED >&2; exit 2;
}
gh api "repos/$REPO/actions/runs?head_sha=$RELEASE&per_page=100" | python3 -c '
import json,sys
runs=json.load(sys.stdin).get("workflow_runs",[])
name="MCP Password Prompt Cache Regression"
if not any(x.get("name")==name and x.get("conclusion")=="success"
  and x.get("status")=="completed" for x in runs):
 raise SystemExit("MCP_UI_REGRESSION_CI_NOT_GREEN")
print("MCP_UI_PINNED_CI=PASS")
'
STAGE="$(mktemp -d /var/backups/learncambodia/mcp-password-ui.XXXXXXXX)"
echo "MCP_PASSWORD_UI_BACKUP=$STAGE"
cp -p "$SITE/$FILE" "$STAGE/original.blade.php"
gh api -H 'Accept: application/vnd.github.raw+json' \
 "repos/$REPO/contents/$FILE?ref=$BASE" > "$STAGE/baseline.blade.php"
cmp -s "$STAGE/original.blade.php" "$STAGE/baseline.blade.php" || {
 echo "LIVE_MCP_VIEW_DRIFT_ABORT" >&2; exit 2;
}
gh api -H 'Accept: application/vnd.github.raw+json' \
 "repos/$REPO/contents/$FILE?ref=$RELEASE" > "$STAGE/release.blade.php"
test -s "$STAGE/release.blade.php"
grep -Fq "hash_file('sha256', public_path('js/mcp-management.js'))" "$STAGE/release.blade.php"
grep -Fq "route('password.confirm')" "$STAGE/release.blade.php"
grep -Fq 'data-mcp-ui-version="password-reauth-v2"' "$STAGE/release.blade.php"
grep -Fq 'id="mcp-browser-password"' "$STAGE/release.blade.php"
grep -Fq 'passwordVerifiedForThisConsent' "$SITE/public/js/mcp-management.js"
grep -Fq "passwordForm?.addEventListener('submit'" "$SITE/public/js/mcp-management.js"
echo "MCP_PASSWORD_SOURCE_COMPATIBILITY=PASS"
replace(){
 local src="$1" dest="$2" tmp
 tmp="$(mktemp "$(dirname "$dest")/.lc-mcp.XXXXXXXX")"
 cat "$src" > "$tmp"
 chown --reference="$dest" "$tmp"
 chmod --reference="$dest" "$tmp"
 mv -f -- "$tmp" "$dest"
}
applied=0; complete=0
rollback(){
 local code=$?
 trap - ERR EXIT
 if test "$applied" -eq 1 && test "$complete" -eq 0; then
  set +e
  replace "$STAGE/original.blade.php" "$SITE/$FILE"
  rm -f -- "$MARKER"
  cd "$SITE"
  runuser -u www -- "$PHP" artisan view:clear >/dev/null 2>&1
  runuser -u www -- "$PHP" artisan view:cache >/dev/null 2>&1
  echo MCP_PASSWORD_UI_ROLLBACK=RESTORED >&2
 fi
 exit "$code"
}
trap rollback ERR EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
applied=1
replace "$STAGE/release.blade.php" "$SITE/$FILE"
cd "$SITE"
runuser -u www -- "$PHP" artisan view:clear
runuser -u www -- "$PHP" artisan view:cache
JS_SHA="$(sha256sum public/js/mcp-management.js | cut -c1-16)"
CODE="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
  --resolve learncambodia.com:443:127.0.0.1 \
  -o "$STAGE/js-response" -w '%{http_code}' \
  "https://learncambodia.com/js/mcp-management.js?v=$JS_SHA")"
test "$CODE" = 200 || { echo "JS_ASSET_HTTP_FAILED=$CODE" >&2; exit 2; }
grep -Fq 'passwordVerifiedForThisConsent' "$STAGE/js-response" || {
 echo JS_ASSET_STALE >&2; exit 2;
}
CODE="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
  --resolve learncambodia.com:443:127.0.0.1 \
  -o /dev/null -w '%{http_code}' \
  https://learncambodia.com/dashboard/system-settings/mcp)"
test "$CODE" = 302 || { echo "ADMIN_AUTH_GUARD_UNEXPECTED=$CODE" >&2; exit 2; }
printf 'SOURCE_COMMIT=%s\nBACKUP_DIR=%s\n' "$RELEASE" "$STAGE" > "$STAGE/marker"
cp "$STAGE/marker" "$MARKER"
chown root:www "$MARKER"; chmod 0640 "$MARKER"
complete=1
echo MCP_PASSWORD_UI_FIX=PASS
echo MCP_PASSWORD_CONFIRM_LINK=VISIBLE
echo MCP_JS_CACHE_BUST=SHA256
echo MCP_ANONYMOUS_ADMIN_GUARD=PASS
echo MCP_GATEWAY_FLAGS=UNCHANGED
echo MCP_CONTENT_MUTATIONS=NONE
echo "MCP_BACKUP_DIR=$STAGE"
echo MCP_URL=https://learncambodia.com/dashboard/system-settings/mcp?lang=en
