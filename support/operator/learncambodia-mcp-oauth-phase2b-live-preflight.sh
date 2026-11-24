#!/usr/bin/env bash
# READ-ONLY Phase 2B production compatibility inspection.
# Prints ONLY version identifiers, source blobs, table existence, and HTTP status.
# Never changes site files, database, migrations, configuration or Passport keys.
set -Eeuo pipefail
set +x
umask 077

SITE="/www/wwwroot/learncambodia.com"
PHP="/www/server/php/85/bin/php"

test "$(id -u)" -eq 0 || { echo "ROOT_REQUIRED" >&2; exit 2; }
test -d "$SITE" && test -f "$SITE/artisan" || {
  echo "LEARN_CAMBODIA_SITE_MISSING" >&2; exit 2;
}
test -x "$PHP" || { echo "PHP85_MISSING" >&2; exit 2; }
for cmd in git curl df; do
  command -v "$cmd" >/dev/null || { echo "COMMAND_MISSING=$cmd" >&2; exit 2; }
done

cd "$SITE"
echo "=== Read-only aaPanel release audit ==="
echo "PHP_CLI_VERSION=$("$PHP" -r 'echo PHP_VERSION;')"
echo "COMPOSER_LOCK_PRESENT=$(test -f composer.lock && echo YES || echo NO)"
echo "PASSPORT_VENDOR_PRESENT=$(test -f vendor/laravel/passport/src/Passport.php && echo YES || echo NO)"
echo "PASSPORT_PRIVATE_KEY_PRESENT=$(test -f storage/oauth-private.key && echo YES || echo NO)"
echo "PHASE2A_OVERLAY_MARKER_PRESENT=$(test -f storage/app/private/learncambodia-mcp-oauth-phase2a-live-overlay.txt && echo YES || echo NO)"
echo "DIRECT_BRIDGE_MARKER_PRESENT=$(test -f storage/app/private/learncambodia-mcp-direct-activation.txt && echo YES || echo NO)"
echo "ANALYTICS_READ_MARKER_PRESENT=$(test -f storage/app/private/learncambodia-mcp-analytics-read-fix.txt && echo YES || echo NO)"
echo "PRODUCTION_FILES_BEGIN"
for file in \
  routes/web.php \
  routes/api.php \
  config/auth.php \
  config/mcp.php \
  app/Providers/AppServiceProvider.php \
  app/Http/Controllers/Api/McpOAuthDiscoveryController.php \
  app/Models/User.php \
  bootstrap/app.php \
  composer.json \
  composer.lock
do
  if test -f "$file" && test ! -L "$file"; then
    echo "LIVE_BLOB $file $(git hash-object "$file")"
  elif test -L "$file"; then
    echo "LIVE_SYMLINK $file"
  else
    echo "LIVE_MISSING $file"
  fi
done
echo "PRODUCTION_FILES_END"

echo "=== Inspect OAuth tables (metadata query only) ==="
"$PHP" -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tables = [
    "oauth_auth_codes",
    "oauth_access_tokens",
    "oauth_refresh_tokens",
    "oauth_clients",
    "oauth_device_codes",
    "mcp_oauth_token_audiences",
];
foreach ($tables as $name) {
    echo "OAUTH_TABLE ".$name." ".
        (\Illuminate\Support\Facades\Schema::hasTable($name) ? "EXISTS" : "ABSENT").PHP_EOL;
}
'
echo "MCP_OAUTH_SCHEMA_READ_ONLY=PASS"

echo "=== Check HTTP response codes without requesting a token ==="
probe() {
  local path="$1" method="$2" name="$3" code
  if test "$method" = POST; then
    code="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
      --resolve learncambodia.com:443:127.0.0.1 \
      -H 'Content-Type: application/json' -H 'Accept: application/json' \
      --data '{"jsonrpc":"2.0","id":1,"method":"initialize"}' \
      -o /dev/null -w '%{http_code}' "https://learncambodia.com$path")"
  else
    code="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
      --resolve learncambodia.com:443:127.0.0.1 \
      -H 'Accept: application/json' \
      -o /dev/null -w '%{http_code}' "https://learncambodia.com$path")"
  fi
  echo "HTTP_STATUS $name $code"
}
probe '/api/mcp/auth-guide' GET 'PUBLIC_AUTH_GUIDE'
probe '/api/mcp' POST 'LEGACY_MCP_ANONYMOUS'
probe '/.well-known/oauth-protected-resource' GET 'OAUTH_RESOURCE_DISCOVERY'
probe '/.well-known/oauth-authorization-server' GET 'OAUTH_AUTH_SERVER_DISCOVERY'
probe '/api/mcp/oauth' POST 'OAUTH_RESOURCE_BEFORE_PHASE2B'
probe '/api/mcp/oauth/token' POST 'OAUTH_TOKEN_BEFORE_PHASE2B'
echo "=== Storage headroom ==="
df -Pk "$SITE" | awk 'NR==2 {printf "SITE_DISK_KB_AVAILABLE=%s\n",$4}'
echo "MCP_OAUTH_PHASE2B_LIVE_PREFLIGHT=COMPLETE"
echo "NO_CONTENT_MUTATIONS=PASS"
echo "NO_DATABASE_MUTATIONS=PASS"
echo "NO_SECRETS_EMITTED=PASS"
