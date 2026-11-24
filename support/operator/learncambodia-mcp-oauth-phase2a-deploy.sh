#!/usr/bin/env bash
# Guarded release for LearnCambodia OAuth Phase 2A: metadata ONLY, fail-closed.
set -Eeuo pipefail
set +x
umask 077

[[ "$(id -u)" = 0 ]] || { echo ROOT_REQUIRED >&2; exit 2; }
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
REPO=dombong2026/learncambodia
REF=5b686eba5596ed1cd3e7720c11d666464eb04158
BASE=a4becc6b9696fb1e2e1b378c93ea2a22878f9799
SITE=/www/wwwroot/learncambodia.com
PHP=/www/server/php/85/bin/php
FPM=/etc/init.d/php-fpm-85
WEB=routes/web.php
CONFIG=config/mcp.php
CTRL=app/Http/Controllers/Api/McpOAuthDiscoveryController.php
MARKER="$SITE/storage/app/private/learncambodia-mcp-oauth-phase2a-deployment.txt"

for c in gh git python3 cmp curl mktemp flock runuser; do
 command -v "$c" >/dev/null || { echo "MISSING_TOOL=$c" >&2; exit 2; }
done
test -x "$PHP" && test -x "$FPM" && test -f "$SITE/artisan"
test -f "$SITE/storage/app/private/learncambodia-mcp-direct-activation.txt"
test -f "$SITE/storage/app/private/learncambodia-mcp-analytics-read-fix.txt"
test ! -L "$SITE/$WEB" && test ! -L "$SITE/$CONFIG" && test ! -L "$SITE/$CTRL"
mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/mcp-oauth-phase2a.lock
flock -n 9 || { echo MCP_OAUTH_LOCK_BUSY >&2; exit 2; }

echo '=== Verify the pinned CI checks ==='
for RUN in 45392341654 45392341534; do
 test "$(gh api "repos/$REPO/actions/runs/$RUN" --jq '.head_sha')" = "$REF"
 test "$(gh api "repos/$REPO/actions/runs/$RUN" --jq '.conclusion')" = success
done
echo MCP_OAUTH_PINNED_CI=PASS

if test -e "$MARKER"; then
 test "$(git hash-object "$SITE/$WEB")" = 59c0bcdd45faa4cd19e96b9a85d1723351fa0f00
 test "$(git hash-object "$SITE/$CONFIG")" = 4edfe4827731147fd91c0d732efa29ce49331304
 test -f "$SITE/$CTRL"
 test "$(git hash-object "$SITE/$CTRL")" = cffdcdc694ffa0eebbaa5ee75f2416e47329b867
 echo MCP_OAUTH_PHASE2A_ALREADY_APPLIED=PASS
 exit 0
fi

test "$(git hash-object "$SITE/$WEB")" = 42a98926506c43efae6c54c8dd53650c48d2bcdb || { echo ROUTES_LIVE_DRIFT_ABORT >&2; exit 2; }
test "$(git hash-object "$SITE/$CONFIG")" = 55b145f760879478e5b83ee63f3ffdf4d66b626c || { echo CONFIG_LIVE_DRIFT_ABORT >&2; exit 2; }
test ! -e "$SITE/$CTRL" || { echo CONTROLLER_ALREADY_EXISTS_ABORT >&2; exit 2; }

STAGE="$(mktemp -d /var/backups/learncambodia/mcp-oauth-phase2a.XXXXXXXX)"
mkdir -p "$STAGE/original" "$STAGE/route-cache"
echo "MCP_OAUTH_BACKUP_DIR=$STAGE"
cp -p "$SITE/$WEB" "$STAGE/original/routes.php"
cp -p "$SITE/$CONFIG" "$STAGE/original/mcp.php"

gh api -H 'Accept: application/vnd.github.raw+json' "repos/$REPO/contents/$WEB?ref=$BASE" > "$STAGE/base-routes.php"
gh api -H 'Accept: application/vnd.github.raw+json' "repos/$REPO/contents/$CONFIG?ref=$BASE" > "$STAGE/base-mcp.php"
cmp -s "$STAGE/original/routes.php" "$STAGE/base-routes.php" || { echo ROUTES_BASELINE_CHANGED >&2; exit 2; }
cmp -s "$STAGE/original/mcp.php" "$STAGE/base-mcp.php" || { echo CONFIG_BASELINE_CHANGED >&2; exit 2; }
gh api -H 'Accept: application/vnd.github.raw+json' "repos/$REPO/contents/$WEB?ref=$REF" > "$STAGE/new-routes.php"
gh api -H 'Accept: application/vnd.github.raw+json' "repos/$REPO/contents/$CONFIG?ref=$REF" > "$STAGE/new-mcp.php"
gh api -H 'Accept: application/vnd.github.raw+json' "repos/$REPO/contents/$CTRL?ref=$REF" > "$STAGE/new-controller.php"
test "$(git hash-object "$STAGE/new-routes.php")" = 59c0bcdd45faa4cd19e96b9a85d1723351fa0f00
test "$(git hash-object "$STAGE/new-mcp.php")" = 4edfe4827731147fd91c0d732efa29ce49331304
test "$(git hash-object "$STAGE/new-controller.php")" = cffdcdc694ffa0eebbaa5ee75f2416e47329b867
for file in new-routes.php new-mcp.php new-controller.php; do
 "$PHP" -l "$STAGE/$file"
done
grep -Fq "'oauth_provider_accepted' => false" "$STAGE/new-mcp.php"
grep -Fq "'oauth_ready' => false" "$STAGE/new-mcp.php"
grep -Fq "'scopes_supported' => ['mcp:read']" "$STAGE/new-controller.php"
echo MCP_OAUTH_PINNED_FILES_AND_GATES=PASS

had_route_cache=0
for cached in "$SITE"/bootstrap/cache/routes-*.php; do
 if test -f "$cached"; then
  had_route_cache=1
  cp -p "$cached" "$STAGE/route-cache/"
 fi
done
atomic_replace() {
 local from="$1" to="$2" tmp
 tmp="$(mktemp "$(dirname "$to")/.oauth.XXXXXXXX")"
 cat "$from" > "$tmp"
 chown --reference="$to" "$tmp"
 chmod --reference="$to" "$tmp"
 mv -f -- "$tmp" "$to"
}
installed=0
complete=0
rollback() {
 result=$?
 trap - ERR EXIT
 if test "$installed" -eq 1 && test "$complete" -eq 0; then
  set +e
  atomic_replace "$STAGE/original/routes.php" "$SITE/$WEB"
  atomic_replace "$STAGE/original/mcp.php" "$SITE/$CONFIG"
  rm -f -- "$SITE/$CTRL" "$MARKER"
  cd "$SITE"
  runuser -u www -- "$PHP" artisan route:clear >/dev/null 2>&1
  if test "$had_route_cache" -eq 1; then
    for old in "$STAGE"/route-cache/routes-*.php; do
      test -f "$old" && cp -p "$old" "$SITE/bootstrap/cache/"
    done
  fi
  "$FPM" reload >/dev/null 2>&1
  echo MCP_OAUTH_PHASE2A_ROLLBACK=RESTORED >&2
 fi
 exit "$result"
}
trap rollback ERR EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

installed=1
atomic_replace "$STAGE/new-routes.php" "$SITE/$WEB"
atomic_replace "$STAGE/new-mcp.php" "$SITE/$CONFIG"
TEMP_CONTROLLER="$(mktemp "$(dirname "$SITE/$CTRL")/.oauth.XXXXXXXX")"
cat "$STAGE/new-controller.php" > "$TEMP_CONTROLLER"
chown --reference="$SITE/app/Http/Controllers/Api/McpBridgeController.php" "$TEMP_CONTROLLER"
chmod --reference="$SITE/app/Http/Controllers/Api/McpBridgeController.php" "$TEMP_CONTROLLER"
mv -f -- "$TEMP_CONTROLLER" "$SITE/$CTRL"

cd "$SITE"
runuser -u www -- "$PHP" artisan route:clear
if test "$had_route_cache" -eq 1; then
 runuser -u www -- "$PHP" artisan route:cache
fi
runuser -u www -- "$PHP" artisan route:list --json > "$STAGE/routes.json"
python3 - "$STAGE/routes.json" <<'PY'
import json, sys
routes=json.load(open(sys.argv[1]))
names={r.get('name') for r in routes}
for name in ('mcp.oauth.metadata.resource','mcp.oauth.metadata.authorization-server','mcp.rpc'):
 if name not in names: raise SystemExit('MCP_OAUTH_MISSING_ROUTE='+name)
print('MCP_OAUTH_ROUTE_REGISTRY=PASS')
PY
"$FPM" reload
echo MCP_OAUTH_FPM_RELOAD=PASS

http() {
 local path="$1" expected="$2" method="$3" got
 if test "$method" = POST; then
  got="$(curl -4 -sS --connect-timeout 8 --max-time 20 --resolve learncambodia.com:443:127.0.0.1 \
   -H 'Accept: application/json' -H 'Content-Type: application/json' \
   --data '{"jsonrpc":"2.0","id":1,"method":"initialize"}' -o /dev/null -w '%{http_code}' "https://learncambodia.com$path")"
 else
  got="$(curl -4 -sS --connect-timeout 8 --max-time 20 --resolve learncambodia.com:443:127.0.0.1 \
   -H 'Accept: application/json' -o /dev/null -w '%{http_code}' "https://learncambodia.com$path")"
 fi
 test "$got" = "$expected" || { echo "MCP_OAUTH_HTTP_FAIL=$path:$got" >&2; return 2; }
}
http '/.well-known/oauth-protected-resource' 404 GET
http '/.well-known/oauth-authorization-server' 404 GET
echo MCP_OAUTH_DISCOVERY_FAIL_CLOSED=PASS
http '/api/mcp/auth-guide' 200 GET
http '/api/mcp' 401 POST
echo MCP_PHASE1_SECURITY=PASS

printf 'SOURCE_COMMIT=%s\nBACKUP_DIR=%s\n' "$REF" "$STAGE" > "$STAGE/marker"
cp "$STAGE/marker" "$MARKER"
chown root:www "$MARKER"
chmod 0640 "$MARKER"
complete=1
echo MCP_OAUTH_PHASE2A_DEPLOYMENT=PASS
echo MCP_OAUTH_AUTHORIZATION_SERVER=DISABLED
echo MCP_OAUTH_TOKEN_ISSUANCE=DISABLED
echo MCP_REMOTE_WRITES=UNCHANGED
echo MCP_CONTENT_MUTATIONS=NONE
echo "MCP_BACKUP_DIR=$STAGE"
