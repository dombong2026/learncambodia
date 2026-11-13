#!/usr/bin/env bash
# Fix only Learn Cambodia Basic Auth path: leave shared aaPanel directories alone.
# Do not change Laravel files, .env, MySQL, composer, or other Nginx vhosts.
set -Eeuo pipefail
set +x
umask 077

DOMAIN="learncambodia.com"
APP="/www/wwwroot/$DOMAIN"
VHOST="/www/server/panel/vhost/nginx/$DOMAIN.conf"
OLD_AUTH="/www/server/panel/vhost/nginx/.learncambodia-setup.htpasswd"
PRIVATE_DIR="$APP/.private-nginx-auth"
NEW_AUTH="$PRIVATE_DIR/learncambodia.htpasswd"
NGINX="/www/server/nginx/sbin/nginx"
LOCK="$APP/storage/app/private/learncambodia.installed"
BACKUP=""
CANDIDATE=""
AUTH_TEMP=""
NETRC=""
HEADERS=""
NEW_DIR=0
CONFIG_CHANGED=0

fail() { echo "AUTH_PATH_RECOVERY=BLOCKED: $*" >&2; exit 2; }
cleanup() {
  local result=$?
  trap - EXIT
  set +e
  if [[ "$result" -ne 0 && "$CONFIG_CHANGED" -eq 1 && -f "$BACKUP/original.conf" ]]; then
    local restore
    restore="$(mktemp /www/server/panel/vhost/nginx/.learncambodia-restore.XXXXXXXX)"
    cp -p -- "$BACKUP/original.conf" "$restore"
    mv -f -- "$restore" "$VHOST"
    if "$NGINX" -t >/dev/null 2>&1; then
      "$NGINX" -s reload >/dev/null 2>&1
      echo "NGINX_VHOST=RESTORED_FROM_BACKUP"
    else
      echo "NGINX_ROLLBACK=MANUAL_REVIEW_REQUIRED"
    fi
  fi
  [[ -z "$CANDIDATE" ]] || rm -f -- "$CANDIDATE"
  [[ -z "$AUTH_TEMP" ]] || rm -f -- "$AUTH_TEMP"
  [[ -z "$NETRC" ]] || rm -f -- "$NETRC"
  [[ -z "$HEADERS" ]] || rm -f -- "$HEADERS"
  if [[ "$result" -ne 0 && "$NEW_DIR" -eq 1 ]]; then
    rm -f -- "$NEW_AUTH"
    rmdir -- "$PRIVATE_DIR" 2>/dev/null || :
  fi
  unset SECRET CONFIRM HASH
  if [[ -n "$BACKUP" ]]; then echo "PRIVATE_RECOVERY_BACKUP=$BACKUP"; fi
  exit "$result"
}
trap cleanup EXIT

echo "========== VERIFY EXACT WEBSITE AND BLOCKED AUTH FILE =========="
[[ "$(id -u)" -eq 0 ]] || fail 'must run as root'
for cmd in python3 curl openssl runuser install mktemp stat cp mv grep awk; do
  command -v "$cmd" >/dev/null || fail "missing $cmd"
done
[[ -d "$APP" && ! -L "$APP" && -f "$APP/public/index.php" ]] || fail 'Laravel not staged at target'
[[ -f "$APP/.env" && ! -L "$APP/.env" ]] || fail 'staged private app environment missing'
[[ ! -e "$LOCK" && ! -L "$LOCK" ]] || fail 'installation lock present; stop and review'
[[ -f "$VHOST" && ! -L "$VHOST" ]] || fail 'website vhost missing or symlink'
[[ -f "$OLD_AUTH" && ! -L "$OLD_AUTH" ]] || fail 'original Basic Auth file missing or symlink'
[[ -x "$NGINX" ]] || fail 'Nginx executable missing'
[[ ! -e "$PRIVATE_DIR" && ! -L "$PRIVATE_DIR" ]] || fail 'private auth directory already exists; review before rerunning'
[[ -z "$(find "$APP" -maxdepth 1 -name '.private-nginx-auth' -print -quit)" ]] || fail 'auth path already exists'
"$NGINX" -t >/dev/null 2>&1 || fail 'Nginx configuration invalid'

python3 - "$VHOST" "$DOMAIN" "$OLD_AUTH" <<'PY'
from pathlib import Path
import re,sys
p,domain,auth = sys.argv[1:]
t=Path(p).read_text()
names=re.findall(r'(?m)^\s*server_name\s+([^;]+);',t)
assert len(names)==1 and domain in names[0].split(), 'wrong vhost or multiple server_name directives'
lines=re.findall(r'(?m)^\s*auth_basic_user_file\s+([^;]+);',t)
assert len(lines)==1 and lines[0].strip()==auth, 'unexpected site Basic Auth source'
assert re.search(r'(?m)^\s*auth_basic\s+[^;]+;',t), 'HTTP Basic Auth not configured'
print('DEDICATED_VHOST=VERIFIED')
PY
if runuser -u www -- test -r "$OLD_AUTH"; then
  fail 'original auth file unexpectedly readable; investigate log evidence before changing'
fi
echo 'OLD_AUTH_FILE_INACCESSIBLE=CONFIRMED'

echo "========== PREPARE ROOT-OWNED PRIVATE AUTH DIRECTORY =========="
install -d -o root -g www -m 750 "$PRIVATE_DIR"
NEW_DIR=1
runuser -u www -- test -x "$PRIVATE_DIR" || fail 'Nginx www worker cannot traverse new private auth directory'
runuser -u www -- test -x "$APP" || fail 'Nginx www worker cannot traverse app root'
echo 'PRIVATE_AUTH_DIRECTORY=PASS'

echo "========== SELECT NEW WEBSITE PASSWORD (NOT MYSQL OR ADMIN) =========="
printf 'New WEBSITE password (24-128 ASCII letters/digits/_/-; hidden): ' >/dev/tty
IFS= read -r -s SECRET </dev/tty
printf '\nConfirm website password (hidden): ' >/dev/tty
IFS= read -r -s CONFIRM </dev/tty
printf '\n' >/dev/tty
[[ "$SECRET" =~ ^[A-Za-z0-9_-]{24,128}$ && "$SECRET" == "$CONFIRM" ]] || fail 'website password failed length, character, or match policy'
unset CONFIRM

BACKUP="$(mktemp -d /root/learncambodia-auth-path-backup.XXXXXXXX)"
cp -p -- "$VHOST" "$BACKUP/original.conf"
cp -p -- "$OLD_AUTH" "$BACKUP/original.htpasswd"
chmod 600 "$BACKUP/original.conf" "$BACKUP/original.htpasswd"

AUTH_TEMP="$(mktemp "$PRIVATE_DIR/.password.XXXXXXXX")"
HASH="$(printf '%s\n' "$SECRET" | openssl passwd -6 -stdin)"
[[ "$HASH" == '$6$'* ]] || fail 'password hashing failed'
printf 'learncambodia_setup:%s\n' "$HASH" > "$AUTH_TEMP"
unset HASH
chown root:www "$AUTH_TEMP"
chmod 640 "$AUTH_TEMP"
mv -- "$AUTH_TEMP" "$NEW_AUTH"
AUTH_TEMP=""
runuser -u www -- test -r "$NEW_AUTH" || fail 'Nginx www worker cannot read new auth file'
echo 'NEW_AUTH_FILE_ACCESS=PASS'

NETRC="$(mktemp /root/.learncambodia-auth-netrc.XXXXXXXX)"
printf 'machine %s login learncambodia_setup password %s\n' "$DOMAIN" "$SECRET" > "$NETRC"
chmod 600 "$NETRC"
unset SECRET

echo "========== REPLACE EXACT VHOST AUTH PATH =========="
CANDIDATE="$(mktemp /www/server/panel/vhost/nginx/.lcam-vhost-fix.XXXXXXXX)"
python3 - "$VHOST" "$CANDIDATE" "$OLD_AUTH" "$NEW_AUTH" <<'PY'
from pathlib import Path
import re,sys
p,out,old,new=sys.argv[1:]
src=Path(p).read_text()
pat=r'(?m)^([ \t]*auth_basic_user_file[ \t]+)'+re.escape(old)+r'([ \t]*;[ \t]*)$'
updated,n=re.subn(pat,lambda m: m.group(1)+new+m.group(2),src)
assert n==1, 'auth directive not unique or changed'
Path(out).write_text(updated)
print('AUTH_PATH_DIRECTIVE=UPDATED_ONE_SITE_ONLY')
PY
chown --reference="$VHOST" "$CANDIDATE"
chmod --reference="$VHOST" "$CANDIDATE"
mv -- "$CANDIDATE" "$VHOST"
CANDIDATE=""
CONFIG_CHANGED=1
"$NGINX" -t >/dev/null 2>&1 || fail 'Nginx syntax failed; restoring'
"$NGINX" -s reload >/dev/null 2>&1 || fail 'Nginx reload failed; restoring'

echo "========== VERIFY ORIGIN HTTPS UNAUTHENTICATED AND AUTHENTICATED =========="
HEADERS="$(mktemp /root/.lcam-origin-headers.XXXXXXXX)"
passed=0
for n in 1 2 3 4 5; do
  public="$(curl --noproxy '*' -sS --connect-timeout 4 --max-time 10 \
      --resolve "$DOMAIN:443:127.0.0.1" -D "$HEADERS" -o /dev/null \
      -w '%{http_code}:%{ssl_verify_result}' "https://$DOMAIN/install")" || public='ERROR'
  verified="$(curl --noproxy '*' --basic --netrc-file "$NETRC" -sS --connect-timeout 4 --max-time 20 \
      --resolve "$DOMAIN:443:127.0.0.1" -o /dev/null \
      -w '%{http_code}:%{ssl_verify_result}' "https://$DOMAIN/install")" || verified='ERROR'
  if [[ "$public" == '401:0' && "$verified" == '200:0' ]] \
      && grep -Eiq '^WWW-Authenticate:[[:space:]]*Basic' "$HEADERS"; then
    passed=1
    break
  fi
  sleep 1
done
echo "UNAUTHENTICATED_HTTP=$public"
echo "AUTHENTICATED_HTTP=$verified"
[[ "$passed" -eq 1 ]] || fail 'browser /install not healthy after auth path correction; reverting Nginx site auth path'

CONFIG_CHANGED=0
echo 'AUTH_PATH_RECOVERY=PASS'
echo "BROWSER_INSTALL_URL=https://$DOMAIN/install"
echo 'BROWSER_WEBSITE_USERNAME=learncambodia_setup'
echo 'BROWSER_PASSWORD=THE_NEW_WEBSITE_PASSWORD_ENTERED_ABOVE'
echo 'PRIVATE_SETUP_TOKEN_COMMAND=cat /root/learncambodia-setup-token'
echo 'LARAVEL_DATABASE=UNCHANGED'
echo 'PUBLIC_AUTH=STILL_ENABLED'
echo 'NEXT=COMPLETE_PROTECTED_INSTALLER_IN_BROWSER'
