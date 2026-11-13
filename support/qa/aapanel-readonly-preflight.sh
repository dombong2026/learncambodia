#!/usr/bin/env bash
# Read-only aaPanel inventory. No database write, install, restart or secret exposure.
set -Eeuo pipefail
set +x
app="$1"
echo "LEARN_CAMBODIA_AAPANEL_QA_READONLY"
[[ -n "$app" && "$app" == /www/wwwroot/learncambodia-qa-* ]] || { echo "BLOCKED: dedicated QA path required"; exit 2; }
[[ ! -L "$app" ]] || { echo "BLOCKED: QA path is symlink"; exit 2; }
[[ -d "$app" && -f "$app/artisan" && -f "$app/.env" ]] || { echo "QA_APP=NOT_INSTALLED"; exit 2; }
echo "QA_APP=PRESENT"
echo "QA_DIR_OWNER_AND_MODE=$(stat -c '%U:%G %a' "$app")"
for php in /www/server/php/84/bin/php /www/server/php/85/bin/php; do
  if [[ -x "$php" ]]; then
    "$php" -r 'echo "PHP_VERSION=".PHP_VERSION." PDO_MYSQL=".(extension_loaded("pdo_mysql")?"YES":"NO")." OPENSSL=".(extension_loaded("openssl")?"YES":"NO")." MBSTRING=".(extension_loaded("mbstring")?"YES":"NO").PHP_EOL;'
  fi
done
if command -v nginx >/dev/null 2>&1; then
  if nginx -t >/dev/null 2>&1; then echo "NGINX_CONFIGURATION=PASS"; else echo "NGINX_CONFIGURATION=FAIL"; fi
fi
echo "INSTALL_LOCK=$([[ -f "$app/storage/app/private/learncambodia.installed" ]] && echo PRESENT || echo ABSENT)"
echo "BUILD_MANIFEST=$([[ -f "$app/public/build/manifest.json" ]] && echo PRESENT || echo ABSENT)"
for var in APP_ENV APP_DEBUG DB_CONNECTION INSTALLER_ENABLED MAIL_MAILER PUBLIC_STUDENT_PROFILES_ENABLED; do
  value="$(grep -E "^$var=" "$app/.env" | tail -n 1 | cut -d= -f2- || true)"
  echo "$var=$value"
done
url="$(grep -E '^APP_URL=' "$app/.env" | tail -n 1 | cut -d= -f2- || true)"
if [[ "$url" == https://* ]]; then echo "APP_URL=HTTPS"; else echo "APP_URL=REVIEW_REQUIRED"; fi
echo "NO_HOST_MUTATION=CONFIRMED"
echo "PHYSICAL_AAPANEL_INSTALLATION_NOT_CERTIFIED_BY_THIS_READONLY_TOOL"
