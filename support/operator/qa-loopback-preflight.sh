#!/usr/bin/env bash
# Read-only checks before a private loopback-only Learn Cambodia QA rehearsal.
# No user, database, .env, Composer, Nginx, permission or service changes.
set -Eeuo pipefail
set +x
umask 077

REPO="dombong2026/learncambodia"
APP="/www/wwwroot/learncambodia-qa-20261113"
BRANCH="qa/mysql-aapanel-browser-security-acceptance"
COMMIT="4acf556e468a54487a263596594c4a4d93942143"
COMPOSER_LOCK_SHA256="043948df6390f037947781244dcf2f39aa678a37fbdfc9879f0640ecd313b260"
NPM_LOCK_SHA256="f4e670ae3cd90e0e6ab27be86adf5d09558bd5ccc904f784f7d595b13d7b52c7"
LOCAL_PORT="18871"
QA_USER="learncambodiaqa"

fail() { echo "LOOPBACK_QA_PREFLIGHT=BLOCKED: $*" >&2; exit 2; }

echo "========== VERIFY SOURCE AND PRIVATE BOUNDARIES =========="
[[ "$(id -u)" -eq 0 ]] || fail "operator must be root"
[[ -d "$APP" && ! -L "$APP" ]] || fail "QA directory absent or symlinked"
[[ "$(realpath -e "$APP")" == "$APP" ]] || fail "unexpected QA directory path"
[[ "$(git -C "$APP" rev-parse --show-toplevel)" == "$APP" ]] || fail "not a standalone QA repository"
[[ "$(git -C "$APP" branch --show-current)" == "$BRANCH" ]] || fail "wrong QA branch"
[[ "$(git -C "$APP" rev-parse HEAD)" == "$COMMIT" ]] || fail "unexpected source commit"
[[ -z "$(git -C "$APP" status --porcelain)" ]] || fail "source worktree dirty"
[[ ! -e "$APP/.env" && ! -L "$APP/.env" ]] || fail "QA environment already exists"
[[ ! -e "$APP/vendor" && ! -L "$APP/vendor" ]] || fail "Composer vendor already staged"
[[ ! -e "$APP/node_modules" && ! -L "$APP/node_modules" ]] || fail "unexpected npm dependencies"
[[ -f "$APP/public/build/manifest.json" && ! -L "$APP/public/build/manifest.json" ]] || fail "Vite manifest missing"
[[ ! -e "$APP/public/hot" ]] || fail "Vite dev mode detected"
[[ ! -e "$APP/storage/app/private/learncambodia.installed" ]] || fail "installer lock exists; manual review needed"
[[ -f "$APP/composer.lock" && -f "$APP/package-lock.json" ]] || fail "dependency locks absent"
printf '%s  %s\n' "$COMPOSER_LOCK_SHA256" "$APP/composer.lock" | sha256sum --check --status || fail "Composer lock mismatch"
printf '%s  %s\n' "$NPM_LOCK_SHA256" "$APP/package-lock.json" | sha256sum --check --status || fail "npm lock mismatch"

echo "QA_SOURCE=PASS"
echo "QA_ASSET_MANIFEST=PASS"
echo "QA_DEPENDENCY_LOCKS=PASS"
echo "QA_ENV=NOT_CREATED"
echo "QA_INSTALLER=DISABLED"

echo "========== GITHUB DRAFT RELEASE BOUNDARY =========="
if command -v gh >/dev/null 2>&1; then
  DRAFT="$(gh api "repos/$REPO/pulls/12" --jq '.draft' 2>/dev/null || true)"
  HEAD="$(gh api "repos/$REPO/pulls/12" --jq '.head.sha' 2>/dev/null || true)"
  if [[ "$DRAFT" == true && "$HEAD" == "$COMMIT" ]]; then
    echo "PR12_QA_HEAD=PASS_DRAFT"
  else
    echo "PR12_QA_HEAD=REVIEW_REQUIRED"
  fi
else
  echo "PR12_QA_HEAD=NOT_CHECKED"
fi

echo "========== PRIVATE PHP RUNTIME =========="
for v in 84 85; do
  php="/www/server/php/$v/bin/php"
  if [[ -x "$php" ]]; then
    "$php" -r '
      $required=["ctype","curl","dom","fileinfo","mbstring","openssl","pdo_mysql","tokenizer","xml"];
      $missing=array_values(array_filter($required,fn($ext)=>!extension_loaded($ext)));
      echo "PHP_".PHP_MAJOR_VERSION.PHP_MINOR_VERSION."_VERSION=".PHP_VERSION."\n";
      echo "PHP_".PHP_MAJOR_VERSION.PHP_MINOR_VERSION."_EXTENSIONS=".(count($missing) ? "MISSING_".implode("_",$missing) : "PASS")."\n";
    '
  else
    echo "PHP_$v=NOT_FOUND"
  fi
done
if command -v composer >/dev/null 2>&1; then
  composer --no-ansi --version 2>/dev/null | head -n 1 | sed 's/^/COMPOSER_HOST=/'
else
  echo "COMPOSER_HOST=NOT_FOUND"
fi

echo "========== LOCAL LOOPBACK PORT RESERVATION =========="
if command -v ss >/dev/null 2>&1; then
  if ss -ltnH | awk -v port=":$LOCAL_PORT" '$4 ~ (port "$") {occupied=1} END{exit !occupied}'; then
    echo "LOOPBACK_PORT_$LOCAL_PORT=IN_USE_DO_NOT_BIND"
  else
    echo "LOOPBACK_PORT_$LOCAL_PORT=AVAILABLE_AT_INSPECTION"
  fi
else
  echo "LOOPBACK_PORT_$LOCAL_PORT=NOT_CHECKED_NO_SS"
fi
echo "LOOPBACK_BIND_TARGET=127.0.0.1:$LOCAL_PORT"
echo "PUBLIC_LISTENER=NOT_AUTHORIZED"

echo "========== QA USER AND FILE ACCESS =========="
stat -c 'QA_DIR_OWNER=%U:%G MODE=%a' "$APP"
if id -u "$QA_USER" >/dev/null 2>&1; then
  echo "QA_SERVICE_ACCOUNT=ALREADY_PRESENT_REVIEW_REQUIRED"
else
  echo "QA_SERVICE_ACCOUNT=NOT_CREATED"
fi
if id -u www >/dev/null 2>&1 && command -v runuser >/dev/null 2>&1; then
  if runuser -u www -- test -r "$APP/artisan"; then
    echo "CURRENT_WWW_READ_ACCESS=YES_REVIEW_REQUIRED"
  else
    echo "CURRENT_WWW_READ_ACCESS=NO"
  fi
fi
if [[ -e /www/server/panel/vhost/nginx/qa.learncambodia.com.conf ||
      -L /www/server/panel/vhost/nginx/qa.learncambodia.com.conf ]]; then
  echo "PUBLIC_QA_VHOST=PRESENT_REVIEW_REQUIRED"
else
  echo "PUBLIC_QA_VHOST=NOT_CREATED"
fi
if command -v systemctl >/dev/null 2>&1; then
  if systemctl is-active --quiet mysqld || systemctl is-active --quiet mysql; then
    echo "MYSQL_SERVICE=ACTIVE"
  else
    echo "MYSQL_SERVICE=REVIEW_REQUIRED"
  fi
fi
df -Pk /www/wwwroot | awk 'NR==2 {printf "WWWROOT_FREE_GIB=%.2f\n",$4/1048576}'
echo "MYSQL_QA_DATABASE=NOT_CREATED_BY_SCRIPT"
echo "MYSQL_CREDENTIALS=NOT_REQUESTED_OR_READ"
echo "NGINX_CONFIG=UNMODIFIED"
echo "FILE_PERMISSIONS=UNMODIFIED"
echo "QA_RUNTIME=NOT_STARTED"
echo "PRODUCTION=UNCHANGED"
echo "LOOPBACK_QA_PREFLIGHT=COMPLETE_READ_ONLY"
echo "NEXT_GATE=REVIEW_DEDICATED_QA_ACCOUNT_VENDOR_INSTALL_AND_DISPOSABLE_DATABASE"
