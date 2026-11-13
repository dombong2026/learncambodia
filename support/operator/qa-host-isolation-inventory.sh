#!/usr/bin/env bash
# Learn Cambodia 13 Nov 2026: read-only host prerequisites for isolated QA acceptance.
# Never creates .env, DB, vhost, files, keys, or changes services.
set -Eeuo pipefail
set +x
umask 077

REPO="dombong2026/learncambodia"
QA="/www/wwwroot/learncambodia-qa-20261113"
COMMIT="d7d085ab1b0d1154c2b0d9123b8b6a828d42efc7"
BRANCH="qa/mysql-aapanel-browser-security-acceptance"
COMPOSER_SHA="043948df6390f037947781244dcf2f39aa678a37fbdfc9879f0640ecd313b260"
NPM_SHA="f4e670ae3cd90e0e6ab27be86adf5d09558bd5ccc904f784f7d595b13d7b52c7"

fail() { echo "HOST_QA_READINESS=BLOCKED: $*" >&2; exit 2; }
report_path() {
  local key="$1" target="$2"
  if [[ -L "$target" ]]; then echo "${key}=BLOCKED_SYMLINK"
  elif [[ -e "$target" ]]; then echo "${key}=PRESENT"
  else echo "${key}=ABSENT"; fi
}

echo "========== VERIFY OPERATOR / ISOLATED QA SOURCE =========="
[[ "$(id -u)" == 0 ]] || fail "requires root to inspect existing aaPanel config"
[[ -d /www/wwwroot && ! -L /www/wwwroot ]] || fail "aaPanel wwwroot is missing or symlink"
[[ -d "$QA" && ! -L "$QA" ]] || fail "isolated checkout missing or symlink"
[[ "$(git -C "$QA" rev-parse --show-toplevel)" == "$QA" ]] || fail "unexpected source root"
[[ "$(git -C "$QA" branch --show-current)" == "$BRANCH" ]] || fail "unexpected QA branch"
[[ "$(git -C "$QA" rev-parse HEAD)" == "$COMMIT" ]] || fail "unexpected QA commit"
[[ -z "$(git -C "$QA" status --porcelain)" ]] || fail "source has uncommitted changes"
[[ ! -e "$QA/.env" && ! -L "$QA/.env" ]] || fail "runtime .env exists; avoid examining credentials"
[[ ! -e "$QA/vendor" && ! -L "$QA/vendor" ]] || fail "vendor already installed"
[[ ! -e "$QA/node_modules" && ! -L "$QA/node_modules" ]] || fail "node_modules already installed"
[[ -f "$QA/composer.lock" && -f "$QA/package-lock.json" ]] || fail "missing pinned lockfiles"
printf '%s  %s\n' "$COMPOSER_SHA" "$QA/composer.lock" | sha256sum -c --status || fail "composer.lock differs"
printf '%s  %s\n' "$NPM_SHA" "$QA/package-lock.json" | sha256sum -c --status || fail "package-lock.json differs"
echo "QA_SOURCE_COMMIT=PASS"
echo "QA_DEPENDENCY_LOCK_HASHES=PASS"
echo "APP_ENV=ABSENT_NO_SECRETS_OPENED"
echo "PHP_DEPENDENCIES=NOT_INSTALLED"
echo "NODE_DEPENDENCIES=NOT_INSTALLED"
report_path "VITE_BUILD_MANIFEST" "$QA/public/build/manifest.json"
report_path "INSTALLER_LOCK_FILE" "$QA/storage/app/private/learncambodia.installed"

echo "========== VERIFY GITHUB RELEASE REVIEW =========="
if command -v gh >/dev/null 2>&1; then
  if [[ "$(gh api "repos/$REPO/pulls/12" --jq '.draft' 2>/dev/null || true)" == true ]] &&
     [[ "$(gh api "repos/$REPO/pulls/12" --jq '.head.sha' 2>/dev/null || true)" == "$COMMIT" ]]; then
    echo "GITHUB_DRAFT_HEAD=PASS"
  else
    echo "GITHUB_DRAFT_HEAD=REVIEW_REQUIRED"
  fi
else
  echo "GITHUB_DRAFT_HEAD=GH_NOT_AVAILABLE"
fi

echo "========== HOST READ-ONLY INVENTORY =========="
df -Pk /www/wwwroot | awk 'NR==2 { printf "WWWROOT_AVAILABLE_GIB=%.2f\n", $4/1048576 }'
echo "QA_DIRECTORY_OWNER_MODE=$(stat -c '%U:%G %a' "$QA")"
if command -v runuser >/dev/null 2>&1 && id www >/dev/null 2>&1; then
  if runuser -u www -- test -r "$QA/artisan"; then
    echo "QA_SOURCE_READABLE_AS_WWW=YES"
  else
    echo "QA_SOURCE_READABLE_AS_WWW=NO_NEEDS_ISOLATED_PERMISSION_PLAN"
  fi
else
  echo "QA_SOURCE_READABLE_AS_WWW=NOT_CHECKED"
fi

for v in 84 85; do
  binary="/www/server/php/$v/bin/php"
  if [[ -x "$binary" ]]; then
    "$binary" -r 'echo "PHP_RUNTIME=".PHP_VERSION." REQUIRED_EXTENSIONS="; foreach (["pdo_mysql","openssl","mbstring","fileinfo","tokenizer","xml","ctype","dom","curl"] as $e) { if (!extension_loaded($e)) { echo "MISSING_".$e.","; } } echo PHP_EOL;'
    echo "PHP_$v=AVAILABLE"
  else
    echo "PHP_$v=NOT_FOUND"
  fi
  if [[ -S "/tmp/php-cgi-$v.sock" ]]; then echo "PHP_FPM_SOCKET_$v=PRESENT"
  else echo "PHP_FPM_SOCKET_$v=NOT_DETECTED_IN_DEFAULT_AAPANEL_PATH"; fi
done
if command -v composer >/dev/null 2>&1; then
  composer --no-ansi --version 2>/dev/null | sed 's/^/COMPOSER_VERSION=/'
fi
for tool in node npm; do
  if command -v "$tool" >/dev/null 2>&1; then
    echo "COMMAND_$tool=FOUND"
  else
    echo "COMMAND_$tool=NOT_FOUND_IN_PATH"
  fi
done
nginx="/www/server/nginx/sbin/nginx"
if [[ ! -x "$nginx" ]]; then nginx="$(command -v nginx || true)"; fi
if [[ -n "$nginx" ]] && "$nginx" -t >/dev/null 2>&1; then
  echo "NGINX_CONFIG_SYNTAX=PASS"
else
  echo "NGINX_CONFIG_SYNTAX=REVIEW_REQUIRED"
fi
for host in qa.learncambodia.com staging.learncambodia.com; do
  key="${host//./_}"
  report_path "VHOST_${key}" "/www/server/panel/vhost/nginx/$host.conf"
  if command -v getent >/dev/null 2>&1 && timeout 8 getent ahosts "$host" >/dev/null 2>&1; then
    echo "DNS_${key}=RESOLVES_TARGET_UNVERIFIED"
    if command -v curl >/dev/null 2>&1 &&
       timeout 12 curl -sSI --connect-timeout 5 --max-time 10 -o /dev/null "https://$host/"; then
      echo "TLS_${key}=VALID_FOR_HOSTNAME_UNVERIFIED_ORIGIN"
    else
      echo "TLS_${key}=NOT_VERIFIED"
    fi
  else
    echo "DNS_${key}=NOT_RESOLVED"
    echo "TLS_${key}=NOT_VERIFIED"
  fi
done
if [[ -x /www/server/mysql/bin/mysql ]]; then
  /www/server/mysql/bin/mysql --version | sed 's/^/MYSQL_CLIENT=/'
elif command -v mysql >/dev/null 2>&1; then
  mysql --version | sed 's/^/MYSQL_CLIENT=/'
fi
echo "QA_DATABASE_ISOLATION=NOT_VERIFIED_NO_CREDENTIALS_USED"
echo "QA_DATABASE_MIGRATIONS=NOT_RUN"
echo "INSTALLER=NOT_ENABLED"
echo "PRODUCTION_SERVICES=NOT_CHANGED"
echo "HOST_INVENTORY=COMPLETE_READ_ONLY"
echo "NEXT_GATE=REVIEW_QA_HOSTNAME_TLS_PHP_FPM_DATABASE_PERMISSIONS_AND_ASSET_BUILD"
