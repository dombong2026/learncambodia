#!/usr/bin/env bash
# Learn Cambodia: prepare already-staged app for the protected browser installer.
# Changes ONLY dedicated Nginx HTTP Basic Auth credentials; no DB/app changes.
set -Eeuo pipefail
set +x
umask 077

DOMAIN="learncambodia.com"
APP="/www/wwwroot/$DOMAIN"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
AUTH="/www/server/panel/vhost/nginx/.learncambodia-setup.htpasswd"
USERNAME="learncambodia_setup"
TOKEN="/root/learncambodia-setup-token"
LOCK="$APP/storage/app/private/learncambodia.installed"
NGINX="/www/server/nginx/sbin/nginx"
BACKUP=""
TEMP_AUTH=""
NETRC=""
HEADERS=""
CHANGED=0

fail() { echo "BROWSER_WIZARD=BLOCKED: $*" >&2; exit 2; }
cleanup() {
  local code=$?
  trap - EXIT
  set +e
  if [[ "$code" -ne 0 && "$CHANGED" -eq 1 && -f "$BACKUP/previous.htpasswd" ]]; then
    local undo
    undo="$(mktemp "$(dirname "$AUTH")/.lcam-undo.XXXXXXXX")"
    cp -p -- "$BACKUP/previous.htpasswd" "$undo"
    mv -f -- "$undo" "$AUTH"
    echo "BASIC_AUTH=RESTORED_FROM_BACKUP"
  fi
  [[ -z "$TEMP_AUTH" ]] || rm -f -- "$TEMP_AUTH"
  [[ -z "$NETRC" ]] || rm -f -- "$NETRC"
  [[ -z "$HEADERS" ]] || rm -f -- "$HEADERS"
  unset NEWPASS AGAIN
  if [[ "$code" -eq 0 ]]; then echo "BROWSER_WIZARD_ACCESS=READY"; fi
  exit "$code"
}
trap cleanup EXIT

echo "========== VERIFY STAGED LARAVEL INSTALLER =========="
[[ "$(id -u)" -eq 0 ]] || fail 'root required'
for cmd in curl openssl grep awk install mktemp cp mv; do
  command -v "$cmd" >/dev/null || fail "missing $cmd"
done
[[ -d "$APP" && ! -L "$APP" ]] || fail 'expected plain application directory'
[[ -f "$APP/artisan" && -f "$APP/vendor/autoload.php" && -f "$APP/public/index.php" ]] || fail 'Laravel application is not staged'
[[ -f "$APP/public/build/manifest.json" ]] || fail 'compiled frontend assets missing'
[[ -f "$APP/.env" && ! -L "$APP/.env" ]] || fail 'private environment missing'
grep -Fxq 'INSTALLER_ENABLED=true' "$APP/.env" || fail 'installer not enabled in staged environment'
[[ -f "$TOKEN" && ! -L "$TOKEN" && -s "$TOKEN" ]] || fail 'root-only browser wizard token missing'
[[ ! -e "$LOCK" && ! -L "$LOCK" ]] || fail 'installer already completed or lock requires manual review'
[[ -f "$VHOST" && ! -L "$VHOST" ]] || fail 'vhost missing or symlink'
[[ -f "$AUTH" && ! -L "$AUTH" ]] || fail 'existing site password file missing or symlink'
[[ -x "$NGINX" ]] || fail 'aaPanel Nginx binary missing'

awk -v root="$APP/public;" '$1=="root" && $2==root {ok=1} END{exit !ok}' "$VHOST" || fail 'vhost document root is not Laravel public/'
grep -Eq '^[[:space:]]*auth_basic[[:space:]]+[^;]+;' "$VHOST" || fail 'site-wide Basic Auth is not configured'
grep -Fq "auth_basic_user_file $AUTH;" "$VHOST" || fail 'vhost does not refer to the expected dedicated auth file'
grep -q "^$USERNAME:" "$AUTH" || fail 'expected website-access username missing'
"$NGINX" -t >/dev/null 2>&1 || fail 'Nginx configuration invalid'

HEADERS="$(mktemp /root/.lcam-wizard-headers.XXXXXXXX)"
CHECK="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 12 --resolve "$DOMAIN:443:127.0.0.1" \
  -D "$HEADERS" -o /dev/null -w '%{http_code}:%{ssl_verify_result}' "https://$DOMAIN/install")" || fail 'origin HTTPS not reachable'
[[ "$CHECK" == '401:0' ]] || fail "unauthenticated origin must return 401 with valid TLS (got $CHECK)"
grep -Eiq '^WWW-Authenticate:[[:space:]]*Basic' "$HEADERS" || fail 'HTTP Basic Auth challenge missing'
echo 'STAGED_SOURCE_AND_PROTECTED_HTTPS=PASS'

echo "========== SET WEBSITE-ACCESS PASSWORD (NOT DATABASE PASSWORD) =========="
printf 'New WEBSITE ACCESS password (24-128 ASCII letters/digits/_/-; hidden): ' >/dev/tty
IFS= read -r -s NEWPASS </dev/tty
printf '\nConfirm website password (hidden): ' >/dev/tty
IFS= read -r -s AGAIN </dev/tty
printf '\n' >/dev/tty
[[ "$NEWPASS" =~ ^[A-Za-z0-9_-]{24,128}$ && "$NEWPASS" == "$AGAIN" ]] || fail 'password does not match the website-access policy'
unset AGAIN

echo "========== REPLACE ONLY LEARN CAMBODIA BASIC AUTH =========="
BACKUP="$(mktemp -d /root/learncambodia-browser-auth-backup.XXXXXXXX)"
cp -p -- "$AUTH" "$BACKUP/previous.htpasswd"
chmod 600 "$BACKUP/previous.htpasswd"
TEMP_AUTH="$(mktemp "$(dirname "$AUTH")/.lcam-auth.XXXXXXXX")"
HASH="$(printf '%s\n' "$NEWPASS" | openssl passwd -6 -stdin)" || fail 'password hashing failed'
[[ "$HASH" == '$6$'* ]] || fail 'unsupported password hash'
printf '%s:%s\n' "$USERNAME" "$HASH" >"$TEMP_AUTH"
unset HASH
chown root:www "$TEMP_AUTH"
chmod 640 "$TEMP_AUTH"
mv -f -- "$TEMP_AUTH" "$AUTH"
TEMP_AUTH=""
CHANGED=1

# Keep credential out of curl arguments, shell history, terminal, and app files.
NETRC="$(mktemp /root/.lcam-netrc.XXXXXXXX)"
printf 'machine %s login %s password %s\n' "$DOMAIN" "$USERNAME" "$NEWPASS" >"$NETRC"
chmod 600 "$NETRC"
unset NEWPASS

echo "========== VERIFY BROWSER WIZARD BEHIND AUTH =========="
CHECK="$(curl --noproxy '*' --basic --netrc-file "$NETRC" -sS --connect-timeout 5 --max-time 20 \
  --resolve "$DOMAIN:443:127.0.0.1" -o /dev/null -w '%{http_code}:%{ssl_verify_result}' \
  "https://$DOMAIN/install")" || fail 'authenticated origin request failed'
echo "AUTHENTICATED_INSTALL_STATUS=$CHECK"
[[ "$CHECK" == '200:0' ]] || fail 'install wizard failed to render after website authentication'
echo 'WEB_INSTALLER_RENDER=PASS'
echo "WEBSITE_USERNAME=$USERNAME"
echo "BROWSER_INSTALL_URL=https://$DOMAIN/install"
echo 'SETUP_TOKEN_LOCATION=/root/learncambodia-setup-token'
echo 'SETUP_TOKEN_READ_COMMAND=cat /root/learncambodia-setup-token'
echo 'NEXT=ENTER_WEBSITE_CREDENTIALS_THEN_PRIVATE_SETUP_TOKEN_AND_CREATE_ADMIN'
echo 'KEEP_HTTP_BASIC_AUTH=ENABLED'
echo "PRIVATE_AUTH_BACKUP=$BACKUP"
CHANGED=0
