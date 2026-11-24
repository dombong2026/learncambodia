#!/usr/bin/env bash
# Safe, disabled-by-default Phase 2B code + vendor release.
# Does NOT migrate, generate keys, register clients, or activate OAuth.
set -Eeuo pipefail
set +x
umask 077
[[ $# == 1 && "$1" =~ ^[0-9a-f]{40}$ ]] || { echo RELEASE_SHA_REQUIRED >&2; exit 2; }
test "$(id -u)" -eq 0 || { echo ROOT_REQUIRED >&2; exit 2; }
RELEASE="$1"
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
REPO=dombong2026/learncambodia
BRANCH=feature/learncambodia-mcp-passport-phase2b-20261124
BASE=f3409f57da1fb39ac339ad80f5d3e70a1045075b
SITE=/www/wwwroot/learncambodia.com
PHP=/www/server/php/85/bin/php
FPM=/etc/init.d/php-fpm-85
MARKER="$SITE/storage/app/private/learncambodia-mcp-oauth-phase2b-staged.txt"
for cmd in gh git python3 patch composer curl flock runuser mktemp; do
 command -v "$cmd" >/dev/null || { echo "MISSING_COMMAND=$cmd" >&2; exit 2; }
done
test -x "$PHP" && test -x "$FPM" && test -f "$SITE/artisan"
test -f "$SITE/storage/app/private/learncambodia-mcp-oauth-phase2a-live-overlay.txt"
test -d "$SITE/vendor" && test -f "$SITE/vendor/autoload.php"
mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/mcp-oauth-phase2b-stage.lock
flock -n 9 || { echo RELEASE_LOCK_BUSY >&2; exit 2; }
test "$(gh api "repos/$REPO/git/ref/heads/$BRANCH" --jq '.object.sha')" = "$RELEASE" || {
 echo RELEASE_BRANCH_MOVED >&2; exit 2;
}
gh api "repos/$REPO/actions/runs?head_sha=$RELEASE&per_page=100" | python3 -c '
import json,sys
runs=json.load(sys.stdin).get("workflow_runs",[])
for name in ("MCP Passport Phase 2B Isolated Regression",
             "MCP OAuth Discovery Phase 2A Preflight",
             "MCP Passport Phase 2B MySQL Acceptance",
             "MCP Passport Phase 2B Operator Preflight Safety",
             "MCP Passport Phase 2B Live Code Safety"):
 if not any(x.get("name")==name and x.get("conclusion")=="success"
            and x.get("status")=="completed" for x in runs):
  raise SystemExit("REQUIRED_PHASE2B_CI_NOT_GREEN="+name)
print("MCP_PHASE2B_PINNED_CI=PASS")
'
if test -f "$MARKER"; then
 test -f "$SITE/vendor/laravel/passport/src/Passport.php"
 echo MCP_PHASE2B_CODE_ALREADY_STAGED=PASS
 exit 0
fi
declare -A expected=(
 [routes/web.php]=d28f586cf17f0c611aca01855ff18c4e102d9579
 [routes/api.php]=6185fd2161ba77c0ef164a0e1b10dab57ebe4c6a
 [config/auth.php]=d7568ff194267ec424aa84e2e9c0d2cbea42b8f8
 [config/mcp.php]=4edfe4827731147fd91c0d732efa29ce49331304
 [app/Providers/AppServiceProvider.php]=8292e92bacc9b9be2a92140f9f235935a14a606f
 [app/Http/Controllers/Api/McpOAuthDiscoveryController.php]=cffdcdc694ffa0eebbaa5ee75f2416e47329b867
 [bootstrap/app.php]=37f2ee943477b906bad487433fe5dae12628480b
 [composer.json]=8ca8df96780fadf4e622e1b89746d4e38699a0df
 [composer.lock]=8748668de1834528ca4b3ae0b22db00422c2b2ef
)
for f in "${!expected[@]}"; do
 test -f "$SITE/$f" && test ! -L "$SITE/$f"
 test "$(git hash-object "$SITE/$f")" = "${expected[$f]}" || {
  echo "LIVE_FILE_DRIFT_ABORT=$f" >&2; exit 2;
 }
done
test "$(git hash-object "$SITE/app/Models/User.php")" = 9faff0c2c9398d71c68c34e0190a6fac60313ea6 || {
 echo SANCTUM_USER_DRIFT_ABORT >&2; exit 2;
}
test "$(stat -c %d "$SITE")" = "$(stat -c %d /var/backups/learncambodia)" || {
 echo VENDOR_SWAP_REQUIRES_SAME_DEVICE >&2; exit 2;
}
changed=(routes/web.php routes/api.php config/auth.php config/mcp.php
 app/Providers/AppServiceProvider.php
 app/Http/Controllers/Api/McpOAuthDiscoveryController.php
 bootstrap/app.php composer.json composer.lock)
new=(
 app/Console/Commands/RegisterMcpOAuthClient.php
 app/Http/Controllers/Api/McpOAuthAccessTokenController.php
 app/Http/Controllers/Api/McpOAuthAuthorizationController.php
 app/Http/Controllers/Api/McpOAuthReadOnlyController.php
 app/Http/Controllers/Workspace/McpOAuthTokenManagementController.php
 app/Http/Middleware/EnsureMcpOAuthStagingEnabled.php
 app/Http/Middleware/RequireMcpOAuthAdmin.php
 app/Http/Middleware/RequireMcpOAuthRead.php
 app/Http/Middleware/ValidateMcpOAuthParameters.php
 app/Models/McpOAuthUser.php
 database/migrations/2016_06_01_000001_create_oauth_auth_codes_table.php
 database/migrations/2016_06_01_000002_create_oauth_access_tokens_table.php
 database/migrations/2016_06_01_000003_create_oauth_refresh_tokens_table.php
 database/migrations/2016_06_01_000004_create_oauth_clients_table.php
 database/migrations/2024_06_01_000001_create_oauth_device_codes_table.php
 database/migrations/2026_11_24_223000_create_mcp_oauth_token_audiences.php
 resources/views/workspace/admin/mcp-oauth-consent.blade.php)
for f in "${new[@]}"; do
 test ! -e "$SITE/$f" && test ! -L "$SITE/$f" || { echo "NEW_FILE_ALREADY_EXISTS=$f" >&2; exit 2; }
done

STAGE="$(mktemp -d /var/backups/learncambodia/mcp-passport-2b.XXXXXXXX)"
echo "MCP_PHASE2B_BACKUP_DIR=$STAGE"
mkdir -p "$STAGE/old" "$STAGE/base" "$STAGE/release" "$STAGE/merged" "$STAGE/qa" "$STAGE/cache"
cp -a "$SITE/bootstrap/cache/." "$STAGE/cache/"
for f in "${changed[@]}"; do
 mkdir -p "$STAGE/old/$(dirname "$f")" "$STAGE/base/$(dirname "$f")" "$STAGE/release/$(dirname "$f")" "$STAGE/merged/$(dirname "$f")"
 cp -p "$SITE/$f" "$STAGE/old/$f"
 gh api -H 'Accept: application/vnd.github.raw+json' "repos/$REPO/contents/$f?ref=$BASE" > "$STAGE/base/$f"
 gh api -H 'Accept: application/vnd.github.raw+json' "repos/$REPO/contents/$f?ref=$RELEASE" > "$STAGE/release/$f"
done
for f in "${new[@]}"; do
 mkdir -p "$STAGE/release/$(dirname "$f")"
 gh api -H 'Accept: application/vnd.github.raw+json' "repos/$REPO/contents/$f?ref=$RELEASE" > "$STAGE/release/$f"
done
for f in "${changed[@]}"; do
 case "$f" in
  routes/web.php|app/Providers/AppServiceProvider.php|bootstrap/app.php)
   # Do not apply a unified diff to production-customized files: its context
   # differs from the GitHub baseline. Instead, isolate ONLY the reviewed
   # insertions at unique semantic anchors. Refuse any ambiguity or conflict.
   python3 - "$STAGE/base/$f" "$STAGE/release/$f" "$STAGE/old/$f" "$STAGE/merged/$f" "$f" <<'PY'
from pathlib import Path
import re,sys
base=Path(sys.argv[1]).read_text()
reviewed=Path(sys.argv[2]).read_text()
live=Path(sys.argv[3]).read_text()
name=sys.argv[5]
ops={
 'routes/web.php': [
  ("use App\\Http\\Controllers\\Api\\McpOAuthDiscoveryController;\n",
   "// OAuth 2.1 discovery is fail-closed"),
 ],
 'app/Providers/AppServiceProvider.php': [
  ("use Laravel\\Fortify\\Fortify;\n", "class AppServiceProvider"),
  ("        Fortify::ignoreRoutes();\n", "    }\n\n    public function boot"),
  ("    public function boot(): void\n    {\n", "        View::composer("),
 ],
 'bootstrap/app.php': [
  ("use App\\Console\\Commands\\AssignAccountRole;\n",
   "use App\\Http\\Middleware\\EnsureDeveloperToken;"),
  ("use App\\Http\\Middleware\\SecurityHeaders;\n",
   "use Illuminate\\Foundation\\Application;"),
  ("use Illuminate\\Http\\Request;\n", "return Application::configure"),
  ("            'developer.token' => EnsureDeveloperToken::class,\n", "        ]);"),
  ("    ->withExceptions(function (Exceptions $exceptions): void {\n",
   "        $exceptions->shouldRenderJsonWhen("),
 ],
}
def between(s, a, z):
 if s.count(a)!=1: raise SystemExit("ANCHOR_NOT_UNIQUE="+name+":"+a[:32])
 start=s.index(a)+len(a)
 stop=s.find(z,start)
 if stop<0: raise SystemExit("STOP_ANCHOR_MISSING="+name+":"+z[:32])
 return s[start:stop]
def add_once(s,a,ins):
 if s.count(a)!=1: raise SystemExit("LIVE_ANCHOR_MISMATCH="+name+":"+a[:30])
 if ins.strip() in s: raise SystemExit("OAUTH_INSERTION_ALREADY_PRESENT="+name)
 return s.replace(a,a+ins,1)
old=base
patched=live
for anchor,stop in ops[name]:
 before=between(base,anchor,stop)
 after=between(reviewed,anchor,stop)
 if before:
  if not after.endswith(before):
   raise SystemExit("REVIEWED_CHANGE_NOT_INSERT_ONLY="+name)
  addition=after[:-len(before)]
 else:
  addition=after
 if not addition.strip(): raise SystemExit("REVIEWED_INSERTION_EMPTY="+name)
 old=add_once(old,anchor,addition)
 patched=add_once(patched,anchor,addition)
if name=='bootstrap/app.php':
 pattern=re.compile(r'(->withCommands\(\[)([^\]\r\n]+)(\]\))')
 def change(s):
  matches=list(pattern.finditer(s))
  if len(matches)!=1: raise SystemExit("WITH_COMMANDS_SHAPE_UNKNOWN")
  existing=matches[0].group(2)
  if 'AssignAccountRole::class' not in existing or 'RegisterMcpOAuthClient::class' in existing:
   raise SystemExit("WITH_COMMANDS_CONFLICT")
  return pattern.sub(lambda m: m.group(1)+m.group(2)+', RegisterMcpOAuthClient::class'+m.group(3),s,count=1)
 old=change(old)
 patched=change(patched)
if old!=reviewed:
 raise SystemExit("OVERLAY_DOES_NOT_REPRODUCE_REVIEWED_GITHUB_DIFF="+name)
if patched==live: raise SystemExit("LIVE_OVERLAY_EMPTY="+name)
Path(sys.argv[4]).write_text(patched)
print("MCP_PHASE2B_VERIFIED_ADDITIVE_OVERLAY="+name)
PY
   ;;
  *)
   cmp -s "$STAGE/base/$f" "$STAGE/old/$f" || {
    echo "RELEASE_BASE_MISMATCH=$f" >&2; exit 2;
   }
   cp "$STAGE/release/$f" "$STAGE/merged/$f"
   ;;
 esac
done
echo MCP_PHASE2B_EXACT_LIVE_OVERLAYS=PASS
for f in "${changed[@]}" "${new[@]}"; do
 case "$f" in *.php)
  if test -f "$STAGE/merged/$f"; then file="$STAGE/merged/$f"; else file="$STAGE/release/$f"; fi
  "$PHP" -l "$file" >/dev/null ;;
 esac
done
grep -Fq "'oauth_ready' => false" "$STAGE/merged/config/mcp.php"
grep -Fq "'oauth_provider_accepted' => false" "$STAGE/merged/config/mcp.php"
grep -Fq "auth:sanctum" "$STAGE/merged/routes/api.php"
echo MCP_PHASE2B_SYNTAX_AND_HARD_DISABLED=PASS

cp "$STAGE/merged/composer.json" "$STAGE/qa/composer.json"
cp "$STAGE/merged/composer.lock" "$STAGE/qa/composer.lock"
(
 cd "$STAGE/qa"
 umask 022
 export COMPOSER_ALLOW_SUPERUSER=1
 export PATH="/www/server/php/85/bin:$PATH"
 composer() { "$PHP" -d include_path=/usr/share/php:. /usr/bin/composer "$@"; }
 composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress --optimize-autoloader
)
test -f "$STAGE/qa/vendor/laravel/passport/src/Passport.php"
test -f "$STAGE/qa/vendor/laravel/sanctum/src/HasApiTokens.php"
echo MCP_PHASE2B_LOCKED_VENDOR_BUILT_IN_ISOLATION=PASS

cd "$SITE"
runuser -u www -- "$PHP" artisan route:list --json > "$STAGE/routes-before.json"
cached=0
for f in "$SITE"/bootstrap/cache/routes-*.php; do
 if test -f "$f"; then cached=1; fi
done
replace() {
 local src="$1" dst="$2" t
 t="$(mktemp "$(dirname "$dst")/.passport.XXXXXXXX")"
 cat "$src" > "$t"
 if test -f "$dst"; then chown --reference="$dst" "$t"; chmod --reference="$dst" "$t"
 else chown --reference="$SITE/routes/api.php" "$t"; chmod 0644 "$t"; fi
 mv -f "$t" "$dst"
}
installed=0
complete=0
rollback() {
 status=$?
 trap - EXIT ERR
 if test "$installed" -eq 1 && test "$complete" -eq 0; then
  set +e
  for f in "${changed[@]}"; do replace "$STAGE/old/$f" "$SITE/$f"; done
  for f in "${new[@]}"; do rm -f -- "$SITE/$f"; done
  if test -d "$STAGE/vendor-old"; then
   if test -d "$SITE/vendor"; then mv "$SITE/vendor" "$STAGE/vendor-rejected"; fi
   mv "$STAGE/vendor-old" "$SITE/vendor"
  fi
  find "$SITE/bootstrap/cache" -maxdepth 1 -name '*.php' -type f -delete
  cp -a "$STAGE/cache/." "$SITE/bootstrap/cache/"
  "$FPM" reload >/dev/null 2>&1
  rm -f "$MARKER"
  echo MCP_PHASE2B_ROLLBACK=RESTORED >&2
 fi
 exit "$status"
}
trap rollback EXIT ERR
trap 'exit 130' INT
trap 'exit 143' TERM

installed=1
for f in "${changed[@]}"; do replace "$STAGE/merged/$f" "$SITE/$f"; done
for f in "${new[@]}"; do replace "$STAGE/release/$f" "$SITE/$f"; done
mv "$SITE/vendor" "$STAGE/vendor-old"
mv "$STAGE/qa/vendor" "$SITE/vendor"
runuser -u www -- test -r "$SITE/vendor/autoload.php"
# Clear only local Laravel compiled/config/route files. optimize:clear
# would also clear the configured cache store, potentially in the database.
runuser -u www -- "$PHP" artisan config:clear >/dev/null
runuser -u www -- "$PHP" artisan route:clear >/dev/null
runuser -u www -- "$PHP" artisan event:clear >/dev/null
runuser -u www -- "$PHP" artisan package:discover --no-interaction >/dev/null
runuser -u www -- test -r "$SITE/bootstrap/cache/packages.php"
if test "$cached" -eq 1; then runuser -u www -- "$PHP" artisan route:cache >/dev/null; fi
runuser -u www -- "$PHP" artisan route:list --json > "$STAGE/routes-after.json"
python3 - "$STAGE/routes-before.json" "$STAGE/routes-after.json" <<'PY'
import json,sys
before=json.load(open(sys.argv[1]))
after=json.load(open(sys.argv[2]))
new={'mcp.oauth.authorize','mcp.oauth.token','mcp.oauth.rpc',
     'passport.authorizations.approve','passport.authorizations.deny',
     'mcp.oauth.tokens.index','mcp.oauth.tokens.revoke'}
def key(x):
 return (x.get('method'),x.get('uri'),x.get('name'),x.get('action'),
         json.dumps(x.get('middleware'),sort_keys=True))
if {key(x) for x in before}!={key(x) for x in after if x.get('name') not in new}:
 raise SystemExit('EXISTING_ROUTE_DRIFT_AFTER_INSTALL')
if not new.issubset({x.get('name') for x in after}):
 raise SystemExit('PASSPORT_ROUTES_NOT_REGISTERED')
print('MCP_PHASE2B_ALL_EXISTING_ROUTES_PRESERVED=PASS')
PY
"$FPM" reload
echo MCP_PHASE2B_PHP_FPM_RELOADED=PASS
probe() {
 local path="$1" expected="$2" method="$3" code
 if test "$method" = POST; then
  code="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
    --resolve learncambodia.com:443:127.0.0.1 \
    -H 'Accept: application/json' -H 'Content-Type: application/json' \
    --data '{"jsonrpc":"2.0","id":1,"method":"initialize"}' \
    -o /dev/null -w '%{http_code}' "https://learncambodia.com$path")"
 else
  code="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
    --resolve learncambodia.com:443:127.0.0.1 \
    -H 'Accept: application/json' \
    -o /dev/null -w '%{http_code}' "https://learncambodia.com$path")"
 fi
 test "$code" = "$expected" || { echo "HTTP_FAILED=$path:$code" >&2; return 2; }
}
probe /api/mcp/auth-guide 200 GET
probe /api/mcp 401 POST
probe /.well-known/oauth-protected-resource 404 GET
probe /.well-known/oauth-authorization-server 404 GET
probe /api/mcp/oauth 404 POST
probe /api/mcp/oauth/token 404 POST
echo MCP_PHASE2B_LIVE_MCP_SECURITY_AND_CLOSED_OAUTH=PASS

printf 'RELEASE_COMMIT=%s\nBACKUP_DIR=%s\nCODE_ONLY=1\n' "$RELEASE" "$STAGE" > "$STAGE/marker"
cp "$STAGE/marker" "$MARKER"
chown root:www "$MARKER"
chmod 0640 "$MARKER"
complete=1
echo MCP_PHASE2B_DISABLED_CODE_RELEASE=PASS
echo MCP_OAUTH_MIGRATIONS=NOT_RUN
echo MCP_OAUTH_KEYS=NOT_GENERATED
echo MCP_OAUTH_TOKEN_ISSUANCE=DISABLED
echo MCP_REMOTE_WRITES=UNCHANGED
echo MCP_DATABASE_MUTATIONS=NONE
echo MCP_CONTENT_MUTATIONS=NONE
echo "MCP_PHASE2B_BACKUP_DIR=$STAGE"
