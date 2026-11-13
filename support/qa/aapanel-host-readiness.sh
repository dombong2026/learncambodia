#!/usr/bin/env bash
# Read-only aaPanel host readiness. Safe before the application exists.
set -Eeuo pipefail
set +x
umask 077
QA="/www/wwwroot/learncambodia-qa-20261113"
echo "========== LEARN CAMBODIA AAPANEL READ-ONLY HOST INVENTORY =========="
if [[ "$(id -u)" -eq 0 ]]; then echo "OPERATOR=ROOT"; else echo "OPERATOR=NON_ROOT"; fi
if [[ -d /www/wwwroot && ! -L /www/wwwroot ]]; then
  echo "AAPANEL_ROOT=FOUND"
  df -Pk /www/wwwroot | awk 'NR==2 { printf "WWWROOT_AVAILABLE_GIB=%.2f\n", $4/1048576 }'
else echo "AAPANEL_ROOT=NOT_FOUND_OR_UNSAFE"; fi
if [[ -L "$QA" ]]; then echo "QA_APP=BLOCKED_SYMLINK"
elif [[ -d "$QA" ]]; then
  echo "QA_APP=EXISTS"
  stat -c 'QA_APP_OWNER=%U:%G QA_APP_MODE=%a' "$QA"
else echo "QA_APP=NOT_INSTALLED"; fi
for bin in git gh composer node npm curl openssl mysql mysqldump; do
  if command -v "$bin" >/dev/null 2>&1; then echo "COMMAND_$bin=FOUND"
  else echo "COMMAND_$bin=NOT_FOUND"; fi
done
if command -v node >/dev/null 2>&1; then node --version | sed 's/^/NODE_VERSION=/'; fi
if command -v npm >/dev/null 2>&1; then npm --version | sed 's/^/NPM_VERSION=/'; fi
if command -v composer >/dev/null 2>&1; then composer --no-ansi --version 2>/dev/null | sed 's/^/COMPOSER_VERSION=/'; fi

for php in /www/server/php/84/bin/php /www/server/php/85/bin/php; do
  if [[ ! -x "$php" ]]; then echo "PHP_BINARY_$php=ABSENT"; continue; fi
  "$php" -r '
    echo "PHP_BINARY=".PHP_BINARY.PHP_EOL;
    echo "PHP_VERSION=".PHP_VERSION.PHP_EOL;
    foreach (["pdo_mysql","openssl","mbstring","fileinfo","tokenizer","xml","ctype","dom","curl"] as $ext) {
      echo "PHP_EXTENSION_".strtoupper($ext)."=".(extension_loaded($ext)?"PASS":"MISSING").PHP_EOL;
    }
  '
done

if [[ -x /www/server/nginx/sbin/nginx ]]; then nginx_bin=/www/server/nginx/sbin/nginx
elif command -v nginx >/dev/null 2>&1; then nginx_bin="$(command -v nginx)"
else nginx_bin=""; fi
if [[ -z "$nginx_bin" ]]; then echo "NGINX_BINARY=ABSENT"
elif "$nginx_bin" -t >/dev/null 2>&1; then echo "NGINX_SYNTAX=PASS"
else echo "NGINX_SYNTAX=FAIL"; fi
for conf in \
  /www/server/panel/vhost/nginx/learncambodia.com.conf \
  /www/server/panel/vhost/nginx/qa.learncambodia.com.conf \
  /www/server/panel/vhost/nginx/staging.learncambodia.com.conf; do
  if [[ -f "$conf" && ! -L "$conf" ]]; then echo "VHOST_$(basename "$conf")=PRESENT"
  else echo "VHOST_$(basename "$conf")=ABSENT"; fi
done
if [[ -x /www/server/mysql/bin/mysql ]]; then
  /www/server/mysql/bin/mysql --version 2>/dev/null | sed 's/^/AAPANEL_MYSQL_CLIENT=/'
elif command -v mysql >/dev/null 2>&1; then
  mysql --version 2>/dev/null | sed 's/^/MYSQL_CLIENT=/'
fi
echo "QA_DATABASE=NOT_CHECKED_NO_CREDENTIALS"
echo "QA_HTTPS=NOT_CHECKED"
echo "DEPENDENCY_LOCKS=REVIEW_REQUIRED"
echo "INSTALLER=NOT_ENABLED"
echo "SERVER_MUTATION=NONE"
echo "HOST_READINESS_INVENTORY=COMPLETE"
