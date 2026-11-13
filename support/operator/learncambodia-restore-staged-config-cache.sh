#!/usr/bin/env bash
# Repair one-time Learn Cambodia staging cache in its FINAL deployment directory.
# No database migrations, credentials changes or changes to other sites.
# After successful recovery, run the existing integrity-pinned browser helper,
# which offers a private password reset and verifies HTTP 200 from /install.
set -Eeuo pipefail
set +x
umask 077

APP='/www/wwwroot/learncambodia.com'
PHP='/www/server/php/85/bin/php'
NGINX='/www/server/nginx/sbin/nginx'
CACHE="$APP/bootstrap/cache/config.php"
LOCK="$APP/storage/app/private/learncambodia.installed"
TOKEN='/root/learncambodia-setup-token'
HELPER='/root/learncambodia-browser-wizard-access-v1.sh'
HELPER_BLOB='746d3c730daafb6ab238f68a62fd96302828d696'
BACKUP=''

fail(){ echo "RECOVERY=BLOCKED: $*" >&2; exit 2; }

echo '========== VERIFY OPERATOR AND EXISTING STAGED APPLICATION =========='
[[ "$(id -u)" -eq 0 ]] || fail 'root required'
for cmd in git grep runuser cp stat; do command -v "$cmd" >/dev/null || fail "missing $cmd"; done
[[ -d "$APP" && ! -L "$APP" && -x "$PHP" && -x "$NGINX" ]] || fail 'application location, PHP or Nginx not ready'
[[ -f "$APP/artisan" && -f "$APP/public/index.php" && -f "$APP/vendor/autoload.php" ]] || fail 'Laravel source not staged'
[[ -f "$APP/.env" && ! -L "$APP/.env" ]] || fail 'private .env missing or unsafe'
[[ -d "$APP/bootstrap/cache" && ! -L "$APP/bootstrap/cache" ]] || fail 'bootstrap cache directory unsafe'
[[ -f "$CACHE" && ! -L "$CACHE" ]] || fail 'expected cached config not found or unsafe'
[[ -f "$TOKEN" && ! -L "$TOKEN" && -s "$TOKEN" ]] || fail 'browser setup token missing'
[[ ! -e "$LOCK" && ! -L "$LOCK" ]] || fail 'installation lock exists; do not change a completed installation'
[[ -f "$HELPER" && ! -L "$HELPER" ]] || fail 'previous browser helper missing'
[[ "$(git hash-object "$HELPER")" == "$HELPER_BLOB" ]] || fail 'browser helper SHA mismatch'
bash -n "$HELPER" || fail 'browser helper shell syntax invalid'
"$NGINX" -t >/dev/null 2>&1 || fail 'Nginx config invalid'

echo '========== CHECK ABSOLUTE PATHS IN OLD CACHE =========='
if ! grep -Fq '/www/wwwroot/.learncambodia-install.' "$CACHE"; then
  echo 'STAGING_PATHS=NOT_FOUND'
  fail 'expected staging-cache signature not present; investigate logs instead of guessing'
fi
echo 'STAGING_PATHS=CONFIRMED_IN_CONFIG_CACHE'
echo 'STAGING_PATH_RECOVERY=AUTHORIZED'

BACKUP="$(mktemp -d /root/learncambodia-config-cache-backup.XXXXXXXX)"
cp -p -- "$CACHE" "$BACKUP/config.php"
chmod 600 "$BACKUP/config.php"
echo "PRIVATE_CONFIG_BACKUP=$BACKUP"

echo '========== REBUILD CACHE IN FINAL SITE DIRECTORY =========='
cd "$APP"
runuser -u www -- "$PHP" artisan config:clear --no-interaction >/dev/null \
  || fail 'artisan config:clear failed; source and database unchanged'
runuser -u www -- "$PHP" artisan config:cache --no-interaction >/dev/null \
  || fail 'artisan config:cache failed; check PHP logs (no DB changes)'
[[ -f "$CACHE" && ! -L "$CACHE" ]] || fail 'new cache missing or unsafe'
if grep -Fq '/www/wwwroot/.learncambodia-install.' "$CACHE"; then
  fail 'new cache still references temporary staging directory'
fi
grep -Fq "$APP/storage" "$CACHE" || fail 'new cache lacks expected final storage paths'
runuser -u www -- "$PHP" artisan route:list --path=install --no-interaction >/dev/null \
  || fail 'Laravel install routes failed to load'
echo 'LARAVEL_CONFIG_CACHE=REBUILT_AT_FINAL_PATH'
echo 'INSTALL_ROUTES=PASS'
echo 'DATABASE_MIGRATIONS=NOT_RUN'
echo 'OTHER_SITES=UNCHANGED'

echo '========== RESTORE BROWSER WIZARD ACCESS =========='
# This separate verified helper modifies only Learn Cambodia's Basic Auth file
# and restores that file on failure. It will ask for a NEW website password.
bash "$HELPER"
echo 'STAGING_CACHE_RECOVERY=COMPLETE'
echo 'NEXT=OPEN_HTTPS_LEARNCAMBODIA_COM_INSTALL'
