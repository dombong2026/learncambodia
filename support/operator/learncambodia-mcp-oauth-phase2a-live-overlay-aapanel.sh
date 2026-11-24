#!/usr/bin/env bash
# Phase 2A OAuth metadata deployment, safely layered onto the actual production routes.
# No OAuth server/keys/migrations/token grants/writes. Exact pinned live-route hash.
set -Eeuo pipefail
set +x
umask 077
test "$#" -eq 1 || { echo "USAGE: script <reviewed-commit-SHA>" >&2; exit 2; }
[[ "$1" =~ ^[0-9a-f]{40}$ ]] || { echo "INVALID_RELEASE_SHA" >&2; exit 2; }
test "$(id -u)" -eq 0 || { echo "ROOT_REQUIRED" >&2; exit 2; }
RELEASE="$1"
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh

REPO="dombong2026/learncambodia"
BRANCH="feature/learncambodia-mcp-oauth-discovery-20261124"
SOURCE="5b686eba5596ed1cd3e7720c11d666464eb04158"
SITE="/www/wwwroot/learncambodia.com"
PHP="/www/server/php/85/bin/php"
FPM="/etc/init.d/php-fpm-85"
ROUTES="$SITE/routes/web.php"
CONFIG="$SITE/config/mcp.php"
CONTROLLER="$SITE/app/Http/Controllers/Api/McpOAuthDiscoveryController.php"
MARKER="$SITE/storage/app/private/learncambodia-mcp-oauth-phase2a-live-overlay.txt"

LIVE_ROUTES_BLOB="9160b1afaf28f9651585ace8a319337f57109f7f"
LIVE_CONFIG_BLOB="55b145f760879478e5b83ee63f3ffdf4d66b626c"
NEW_CONFIG_BLOB="4edfe4827731147fd91c0d732efa29ce49331304"
CONTROLLER_BLOB="cffdcdc694ffa0eebbaa5ee75f2416e47329b867"

for prog in gh git python3 curl flock mktemp runuser; do
  command -v "$prog" >/dev/null || { echo "MISSING_TOOL=$prog" >&2; exit 2; }
done
test -x "$PHP" && test -x "$FPM" && test -f "$SITE/artisan"
test -f "$SITE/storage/app/private/learncambodia-mcp-direct-activation.txt"
test -f "$SITE/storage/app/private/learncambodia-mcp-analytics-read-fix.txt"
test -f "$ROUTES" && test -f "$CONFIG"
test ! -L "$ROUTES" && test ! -L "$CONFIG" && test ! -L "$CONTROLLER"

mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/mcp-oauth-phase2a-overlay.lock
flock -n 9 || { echo "MCP_OAUTH_DEPLOYMENT_LOCK_BUSY" >&2; exit 2; }

test "$(gh api "repos/$REPO/git/ref/heads/$BRANCH" --jq '.object.sha')" = "$RELEASE" || {
  echo "RELEASE_SHA_NO_LONGER_BRANCH_HEAD" >&2; exit 2;
}
gh api "repos/$REPO/actions/runs?head_sha=$RELEASE&per_page=100" | python3 -c '
import json,sys
runs=json.load(sys.stdin).get("workflow_runs",[])
for name in ("MCP OAuth Discovery Phase 2A Preflight",
             "MCP OAuth Passport Compatibility (Isolated)",
             "MCP OAuth Live Overlay Operator Safety"):
    if not any(x.get("name")==name and x.get("status")=="completed"
               and x.get("conclusion")=="success" for x in runs):
        raise SystemExit("MCP_OAUTH_PINNED_CI_FAILED="+name)
print("MCP_OAUTH_OVERLAY_PINNED_CI=PASS")
'

# Detect a previous successful deployment without touching anything.
if test -f "$MARKER"; then
  source_commit="$(sed -n 's/^SOURCE_COMMIT=//p' "$MARKER")"
  installed_routes="$(sed -n 's/^INSTALLED_ROUTES_BLOB=//p' "$MARKER")"
  test "$source_commit" = "$SOURCE" && test -n "$installed_routes"
  test "$(git hash-object "$ROUTES")" = "$installed_routes"
  test "$(git hash-object "$CONFIG")" = "$NEW_CONFIG_BLOB"
  test -f "$CONTROLLER" && test "$(git hash-object "$CONTROLLER")" = "$CONTROLLER_BLOB"
  echo "MCP_OAUTH_PHASE2A_ALREADY_INSTALLED=PASS"
  exit 0
fi

# Never overwrite another LearnCambodia release's routes.
test "$(git hash-object "$ROUTES")" = "$LIVE_ROUTES_BLOB" || {
  echo "ROUTES_LIVE_CHANGED_AGAIN_ABORT" >&2; exit 2;
}
test "$(git hash-object "$CONFIG")" = "$LIVE_CONFIG_BLOB" || {
  echo "MCP_CONFIG_LIVE_CHANGED_ABORT" >&2; exit 2;
}
test ! -e "$CONTROLLER" || { echo "OAUTH_CONTROLLER_PRESENT_ABORT" >&2; exit 2; }

BACKUP="$(mktemp -d /var/backups/learncambodia/mcp-oauth-overlay.XXXXXXXX)"
mkdir -p "$BACKUP/route-cache"
cp -p -- "$ROUTES" "$BACKUP/original-routes.php"
cp -p -- "$CONFIG" "$BACKUP/original-mcp.php"
echo "MCP_OAUTH_BACKUP_DIR=$BACKUP"

gh api -H 'Accept: application/vnd.github.raw+json' \
  "repos/$REPO/contents/config/mcp.php?ref=$SOURCE" > "$BACKUP/new-mcp.php"
gh api -H 'Accept: application/vnd.github.raw+json' \
  "repos/$REPO/contents/app/Http/Controllers/Api/McpOAuthDiscoveryController.php?ref=$SOURCE" > "$BACKUP/new-controller.php"

test "$(git hash-object "$BACKUP/new-mcp.php")" = "$NEW_CONFIG_BLOB"
test "$(git hash-object "$BACKUP/new-controller.php")" = "$CONTROLLER_BLOB"

# Append only the two read-only metadata endpoints to the verified live file,
# without reverting its Social Auth, course resources, SMTP or other routes.
python3 - "$BACKUP/original-routes.php" "$BACKUP/new-routes.php" <<'PY'
from pathlib import Path
import sys
source=Path(sys.argv[1]).read_bytes()
anchor=b"use Illuminate\\Support\\Facades\\Route;\n"
if source.count(anchor)!=1:
    raise SystemExit("UNEXPECTED_ROUTE_IMPORT_ANCHOR")
if (b"McpOAuthDiscoveryController" in source
    or b"/.well-known/oauth-protected-resource" in source
    or b"/.well-known/oauth-authorization-server" in source):
    raise SystemExit("OAUTH_ROUTES_ALREADY_EXIST_ABORT")
addition=b"""use App\\Http\\Controllers\\Api\\McpOAuthDiscoveryController;

// OAuth Phase 2A: discovery is disabled until a reviewed OAuth provider exists.
Route::get('/.well-known/oauth-protected-resource', [McpOAuthDiscoveryController::class, 'protectedResource'])
    ->middleware('throttle:30,1')->name('mcp.oauth.metadata.resource');
Route::get('/.well-known/oauth-authorization-server', [McpOAuthDiscoveryController::class, 'authorizationServer'])
    ->middleware('throttle:30,1')->name('mcp.oauth.metadata.authorization-server');

"""
patched=source.replace(anchor,anchor+addition,1)
assert patched.replace(addition,b"",1)==source
Path(sys.argv[2]).write_bytes(patched)
print("MCP_OAUTH_PRESERVE_ALL_EXISTING_ROUTES=PASS")
PY

grep -Fq "'oauth_provider_accepted' => false" "$BACKUP/new-mcp.php"
grep -Fq "'oauth_ready' => false" "$BACKUP/new-mcp.php"
grep -Fq "'scopes_supported' => ['mcp:read']" "$BACKUP/new-controller.php"

for file in new-routes.php new-mcp.php new-controller.php; do
  "$PHP" -l "$BACKUP/$file"
done
echo "MCP_OAUTH_PINNED_FILES_AND_GATES=PASS"

cd "$SITE"
runuser -u www -- "$PHP" artisan route:list --json > "$BACKUP/routes-before.json"
cache_present=0
for file in "$SITE"/bootstrap/cache/routes-*.php; do
  if test -f "$file"; then
    cache_present=1
    cp -p -- "$file" "$BACKUP/route-cache/"
  fi
done

replace_file() {
  local from="$1" to="$2" tmp
  tmp="$(mktemp "$(dirname "$to")/.oauth-overlay.XXXXXXXX")"
  cat -- "$from" > "$tmp"
  chown --reference="$to" "$tmp"
  chmod --reference="$to" "$tmp"
  mv -f -- "$tmp" "$to"
}
create_controller() {
  local tmp
  tmp="$(mktemp "$(dirname "$CONTROLLER")/.oauth-overlay.XXXXXXXX")"
  cat -- "$BACKUP/new-controller.php" > "$tmp"
  chown --reference="$SITE/app/Http/Controllers/Api/McpBridgeController.php" "$tmp"
  chmod --reference="$SITE/app/Http/Controllers/Api/McpBridgeController.php" "$tmp"
  mv -f -- "$tmp" "$CONTROLLER"
}

applied=0
complete=0
rollback() {
  status=$?
  trap - ERR EXIT
  if test "$applied" -eq 1 && test "$complete" -eq 0; then
    set +e
    replace_file "$BACKUP/original-routes.php" "$ROUTES"
    replace_file "$BACKUP/original-mcp.php" "$CONFIG"
    rm -f -- "$CONTROLLER" "$MARKER"
    cd "$SITE"
    runuser -u www -- "$PHP" artisan route:clear >/dev/null 2>&1
    if test "$cache_present" -eq 1; then
      for original in "$BACKUP"/route-cache/routes-*.php; do
        if test -f "$original"; then
          cp -p -- "$original" "$SITE/bootstrap/cache/"
        fi
      done
    fi
    "$FPM" reload >/dev/null 2>&1
    echo "MCP_OAUTH_PHASE2A_ROLLBACK=RESTORED" >&2
  fi
  exit "$status"
}
trap rollback ERR EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

applied=1
replace_file "$BACKUP/new-routes.php" "$ROUTES"
replace_file "$BACKUP/new-mcp.php" "$CONFIG"
create_controller
runuser -u www -- "$PHP" artisan route:clear
if test "$cache_present" -eq 1; then
  runuser -u www -- "$PHP" artisan route:cache
fi
runuser -u www -- "$PHP" artisan route:list --json > "$BACKUP/routes-after.json"

python3 - "$BACKUP/routes-before.json" "$BACKUP/routes-after.json" <<'PY'
import json,sys
before=json.load(open(sys.argv[1]))
after=json.load(open(sys.argv[2]))
oauth_names={"mcp.oauth.metadata.resource","mcp.oauth.metadata.authorization-server"}
def signature(row):
    return (row.get("method"),row.get("uri"),row.get("name"),row.get("action"),
            json.dumps(row.get("middleware"),sort_keys=True))
before_set={signature(r) for r in before}
after_old={signature(r) for r in after if r.get("name") not in oauth_names}
assert before_set==after_old, "NON_OAUTH_ROUTES_CHANGED"
names={r.get("name") for r in after}
assert oauth_names.issubset(names), "OAUTH_ROUTE_NOT_REGISTERED"
assert "mcp.rpc" in names, "MCP_RPC_ROUTE_MISSING"
print("MCP_OAUTH_ROUTE_REGISTRY=PASS")
print("MCP_EXISTING_ROUTE_REGISTRY_UNCHANGED=PASS")
PY

"$FPM" reload
echo "MCP_OAUTH_FPM_RELOAD=PASS"
probe() {
  local url="$1" expected="$2" method="$3" code
  if test "$method" = "POST"; then
    code="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
      --resolve learncambodia.com:443:127.0.0.1 \
      -H 'Accept: application/json' -H 'Content-Type: application/json' \
      --data '{"jsonrpc":"2.0","id":1,"method":"initialize"}' \
      -o /dev/null -w '%{http_code}' "https://learncambodia.com$url")"
  else
    code="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
      --resolve learncambodia.com:443:127.0.0.1 \
      -H 'Accept: application/json' \
      -o /dev/null -w '%{http_code}' "https://learncambodia.com$url")"
  fi
  test "$code" = "$expected" || { echo "MCP_OAUTH_HTTP_FAIL=$url:$code" >&2; return 2; }
}
probe '/.well-known/oauth-protected-resource' 404 GET
probe '/.well-known/oauth-authorization-server' 404 GET
echo "MCP_OAUTH_DISCOVERY_FAIL_CLOSED=PASS"
probe '/api/mcp/auth-guide' 200 GET
probe '/api/mcp' 401 POST
echo "MCP_PHASE1_SECURITY=PASS"

# Verify the route mutation was exactly the byte-preserving addition.
test "$(git hash-object "$ROUTES")" = "$(git hash-object "$BACKUP/new-routes.php")"
test "$(git hash-object "$CONFIG")" = "$NEW_CONFIG_BLOB"
test "$(git hash-object "$CONTROLLER")" = "$CONTROLLER_BLOB"
{
  printf 'SOURCE_COMMIT=%s\n' "$SOURCE"
  printf 'RELEASE_COMMIT=%s\n' "$RELEASE"
  printf 'ORIGINAL_ROUTES_BLOB=%s\n' "$LIVE_ROUTES_BLOB"
  printf 'INSTALLED_ROUTES_BLOB=%s\n' "$(git hash-object "$ROUTES")"
  printf 'BACKUP_DIR=%s\n' "$BACKUP"
} > "$BACKUP/marker"
cp -- "$BACKUP/marker" "$MARKER"
chown root:www "$MARKER"
chmod 0640 "$MARKER"
complete=1

echo "MCP_OAUTH_PHASE2A_DEPLOYMENT=PASS"
echo "MCP_OAUTH_AUTHORIZATION_SERVER=DISABLED"
echo "MCP_OAUTH_TOKEN_ISSUANCE=DISABLED"
echo "MCP_REMOTE_WRITES=UNCHANGED"
echo "MCP_CONTENT_MUTATIONS=NONE"
echo "MCP_BACKUP_DIR=$BACKUP"
