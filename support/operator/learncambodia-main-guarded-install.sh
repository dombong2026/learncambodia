#!/usr/bin/env bash
# Learn Cambodia: guarded aaPanel installation for a NEW, password-protected HTTPS site.
# This is NOT an upgrade tool. No other vhosts, databases or system services are changed.
# Run modes: check | install | finalize
set -Eeuo pipefail
set +x
umask 077
MODE="$1"
REPO='dombong2026/learncambodia'
MAIN='db2a5cdd6fa3a3b6b2066fdb8c95084e4dbb387e'
TREE='588b4af180b446bec48189863bb2c84e322028ad'
TESTED='c2eec3680d2b69e5e5ee2bea30ccc66ac404e96e'
PHPLOCK='043948df6390f037947781244dcf2f39aa678a37fbdfc9879f0640ecd313b260'
NPMLOCK='f4e670ae3cd90e0e6ab27be86adf5d09558bd5ccc904f784f7d595b13d7b52c7'
ASSET_RUN='43428045424'
ASSET_ID='14038232950'
NAME='learncambodia-pinned-qa-vite-assets'
DOMAIN='learncambodia.com'
APP='/www/wwwroot/learncambodia.com'
VHOST='/www/server/panel/vhost/nginx/learncambodia.com.conf'
PHP='/www/server/php/85/bin/php'
TOKEN='/root/learncambodia-setup-token'
COMPOSER_PHAR='/root/learncambodia-tools/composer-2.11.2.phar'
COMPOSER_SHA='dd158c0afd41d15fac89e1b4e47b5d42911fe316c3687cd5edac98319b25d028'
fail(){ echo "INSTALL=BLOCKED: $*" >&2; exit 2; }
[[ "$(id -u)" == 0 ]] || fail 'requires root'
[[ "$MODE" == check || "$MODE" == install || "$MODE" == finalize ]] || fail 'mode must be check/install/finalize'
for cmd in gh git dig curl awk grep python3 sha256sum tar mktemp runuser find cp cmp; do
  command -v "$cmd" >/dev/null || fail "missing $cmd"
done
[[ -x "$PHP" && -d /www/wwwroot && ! -L /www/wwwroot ]] || fail 'aaPanel PHP or directory absent'
id -u www >/dev/null 2>&1 || fail 'aaPanel www account absent'
MYSQL="$(command -v mysql || :)"
if [[ -z "$MYSQL" && -x /www/server/mysql/bin/mysql ]]; then MYSQL='/www/server/mysql/bin/mysql'; fi
[[ -n "$MYSQL" ]] || fail 'MySQL CLI not found'

# Accept only the aaPanel-created placeholder: root .user.ini and an optional
# public/ folder containing at most its matching .user.ini. Never purge unknown data.
verify_site_placeholder() {
  [[ ! -L "$APP" ]] || fail 'website root symlink prohibited'
  if [[ ! -e "$APP" ]]; then echo 'AAPANEL_SITE_ROOT=ABSENT'; return 0; fi
  [[ -d "$APP" ]] || fail 'website root is not a directory'
  local unexpected
  unexpected="$(find "$APP" -mindepth 1 -maxdepth 1 ! -name '.user.ini' ! -name 'public' -print -quit)"
  [[ -z "$unexpected" ]] || fail 'site contains files other than aaPanel .user.ini and public/; do not overwrite'
  if [[ -e "$APP/.user.ini" || -L "$APP/.user.ini" ]]; then
    [[ -f "$APP/.user.ini" && ! -L "$APP/.user.ini" ]] || fail 'unsafe .user.ini'
    python3 - "$APP/.user.ini" "$APP" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1])
site=sys.argv[2]
assert p.stat().st_size < 4096, 'unexpectedly large .user.ini'
lines=[x.strip() for x in p.read_text(encoding='utf-8').splitlines() if x.strip() and not x.lstrip().startswith(';') and not x.lstrip().startswith('#')]
assert len(lines)==1 and lines[0].split('=',1)[0].strip()=='open_basedir','review custom PHP directives before installation'
paths=lines[0].split('=',1)[1].strip().split(':')
assert paths[0] == site+'/' and all(x in {site+'/', '/tmp/', '/tmp'} for x in paths), 'review aaPanel open_basedir paths before installation'
print('AAPANEL_OPEN_BASEDIR=REVIEWED_SITE_AND_TMP_ONLY')
PY
  fi
  if [[ -e "$APP/public" || -L "$APP/public" ]]; then
    [[ -d "$APP/public" && ! -L "$APP/public" ]] || fail 'public path is not a plain directory'
    unexpected="$(find "$APP/public" -mindepth 1 -maxdepth 1 ! -name '.user.ini' -print -quit)"
    [[ -z "$unexpected" ]] || fail 'public directory contains files; never overwrite'
    if [[ -e "$APP/public/.user.ini" || -L "$APP/public/.user.ini" ]]; then
      [[ -f "$APP/public/.user.ini" && ! -L "$APP/public/.user.ini" ]] || fail 'unsafe public .user.ini'
      [[ -f "$APP/.user.ini" ]] || fail 'public .user.ini exists without reviewed original'
      cmp -s "$APP/.user.ini" "$APP/public/.user.ini" || fail 'public .user.ini differs from original'
    fi
  fi
  echo 'AAPANEL_SITE_ROOT=ONLY_REVIEWED_PLACEHOLDER'
}

if [[ "$MODE" == finalize ]]; then
  [[ -f "$APP/storage/app/private/learncambodia.installed" && -f "$APP/.env" ]] || fail 'installer did not finish'
  printf '%s  %s\n' "$PHPLOCK" "$APP/composer.lock" | sha256sum -c --status || fail 'unexpected application source'
  python3 - "$APP/.env" <<'PY'
import pathlib,sys
p=pathlib.Path(sys.argv[1])
text=p.read_text()
for key,value in [('INSTALLER_ENABLED','false'),('INSTALLER_TOKEN','')]:
    lines=[line for line in text.splitlines() if not line.startswith(key+'=')]
    lines.append(key+'='+value)
    text='\n'.join(lines)+'\n'
p.write_text(text)
PY
  chmod 640 "$APP/.env"
  cd "$APP"
  runuser -u www -- "$PHP" artisan config:clear --no-interaction >/dev/null
  runuser -u www -- "$PHP" artisan config:cache --no-interaction >/dev/null
  rm -f -- "$TOKEN"
  echo 'INSTALLER_LOCK=VERIFIED'
  echo 'INSTALLER=DISABLED'
  echo 'PUBLIC_ACCESS_RESTRICTION=KEEP_UNTIL_INDEPENDENT_RELEASE_APPROVAL'
  exit 0
fi

echo '========== VERIFIED MERGED RELEASE =========='
[[ "$(gh api "repos/$REPO/branches/main" --jq '.commit.sha')" == "$MAIN" ]] || fail 'main changed; repin reviewed release'
[[ "$(gh api "repos/$REPO/git/commits/$MAIN" --jq '.tree.sha')" == "$TREE" ]] || fail 'unexpected release tree'
[[ "$(gh api "repos/$REPO/git/commits/$TESTED" --jq '.tree.sha')" == "$TREE" ]] || fail 'tested feature tree mismatch'
for run in 43428045507 43428045455 43428045424; do
  [[ "$(gh api "repos/$REPO/actions/runs/$run" --jq '.head_sha')" == "$TESTED" ]] || fail 'unexpected CI SHA'
  [[ "$(gh api "repos/$REPO/actions/runs/$run" --jq '.conclusion')" == success ]] || fail 'CI is not green'
done
[[ "$(gh api "repos/$REPO/actions/artifacts/$ASSET_ID" --jq '.name')" == "$NAME" ]] || fail 'asset identity mismatch'
[[ "$(gh api "repos/$REPO/actions/artifacts/$ASSET_ID" --jq '.expired')" == false ]] || fail 'asset expired; rebuild and repin'
echo 'RELEASE_AND_CI=PASS'

echo '========== DOMAIN AND HTTPS PROTECTION =========='
# gTLD servers return NS *referrals* in the AUTHORITY section; +short only reads ANSWER.
# Explicitly require the Cloudflare delegation recorded by the registry.
REGISTRY_NS="$(dig @a.gtld-servers.net "$DOMAIN" NS +norecurse +time=5 +tries=2 +noall +answer +authority \
  | awk -v domain="$DOMAIN." 'tolower($1)==domain && $3=="IN" && $4=="NS" {print tolower($5)}' | LC_ALL=C sort -u)"
printf '%s\n' "$REGISTRY_NS" | grep -Fxq 'june.ns.cloudflare.com.' || fail 'registry delegation missing june.ns.cloudflare.com'
printf '%s\n' "$REGISTRY_NS" | grep -Fxq 'watson.ns.cloudflare.com.' || fail 'registry delegation missing watson.ns.cloudflare.com'
echo 'REGISTRY_DELEGATION=PASS'
[[ -n "$(dig @1.1.1.1 "$DOMAIN" A +short +time=4 +tries=1 || :)" ]] || fail 'learncambodia.com has no A record; set correct DNS first'
[[ -f "$VHOST" && ! -L "$VHOST" ]] || fail 'create a separate HTTPS website using aaPanel first'
awk -v root="$APP/public;" '$1=="root" && $2==root {ok=1} END{exit !ok}' "$VHOST" || fail 'Nginx root must be new application public/'
grep -Eq '^[[:space:]]*auth_basic[[:space:]]+[^;]+;' "$VHOST" || fail 'enable aaPanel Nginx site password protection (auth_basic)'
[[ -x /www/server/nginx/sbin/nginx ]] || fail 'aaPanel Nginx binary absent'
/www/server/nginx/sbin/nginx -t >/dev/null 2>&1 || fail 'Nginx configuration invalid'
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --connect-timeout 5 --max-time 12 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/")" || fail 'local HTTPS certificate invalid'
[[ "$STATUS" == 401 ]] || fail 'require direct-origin HTTP 401: 403 can be a missing index, not valid access protection'
verify_site_placeholder
[[ ! -e "$TOKEN" && ! -L "$TOKEN" ]] || fail 'installer token already exists'
echo 'DOMAIN_TLS_ACCESS_GATES=PASS'

echo '========== PHP / DATABASE SAFETY =========='
"$PHP" -r '$ext=["ctype","curl","dom","fileinfo","mbstring","openssl","pdo_mysql","tokenizer","xml"];foreach($ext as $x){if(!extension_loaded($x)) exit(1);}' || fail 'PHP extensions missing'
[[ -S /tmp/php-cgi-85.sock || -S /www/server/php/85/var/run/php-cgi-85.sock || -S /www/server/php/85/var/run/php-fpm.sock ]] || fail 'PHP 8.5-FPM socket missing'
[[ -f "$COMPOSER_PHAR" && ! -L "$COMPOSER_PHAR" ]] || fail 'install pinned standalone Composer 2.11.2 in /root/learncambodia-tools/; do not replace global composer'
printf '%s  %s\n' "$COMPOSER_SHA" "$COMPOSER_PHAR" | sha256sum -c --status || fail 'isolated Composer SHA256 mismatch'
"$PHP" "$COMPOSER_PHAR" --no-ansi --version | grep -Fq 'Composer version 2.11.2' || fail 'unexpected isolated Composer version'
echo 'ISOLATED_COMPOSER=2.11.2_SHA256_PASS'
if [[ "$MODE" == check ]]; then
  echo 'READ_ONLY_PREFLIGHT=PASS'
  echo 'NEXT=CREATE_NEW_EMPTY_aaPANEL_MYSQL_DATABASE_AND_RUN_INSTALL'
  exit 0
fi
[[ -r /dev/tty && -w /dev/tty ]] || fail 'interactive terminal required'
printf 'Fresh database name (sql_learncambodia_com or learncambodia_...): ' > /dev/tty
IFS= read -r DB < /dev/tty
[[ "$DB" == 'sql_learncambodia_com' || "$DB" =~ ^learncambodia_[a-zA-Z0-9_]{2,40}$ ]] || fail 'database must be dedicated to Learn Cambodia'
printf 'Fresh dedicated database user (sql_learncambodia_com or lcam_...): ' > /dev/tty
IFS= read -r USERNAME < /dev/tty
[[ "$USERNAME" == 'sql_learncambodia_com' || "$USERNAME" =~ ^lcam_[a-zA-Z0-9_]{3,20}$ ]] || fail 'use dedicated Learn Cambodia database account'
printf 'Database password (hidden, 24+ characters, letters/digits/-/_ only): ' > /dev/tty
IFS= read -r -s PASSWORD < /dev/tty
printf '\n' > /dev/tty
[[ "$PASSWORD" =~ ^[a-zA-Z0-9_-]{24,128}$ ]] || fail 'password format invalid'

STAGE="$(mktemp -d /www/wwwroot/.learncambodia-install.XXXXXXXX)"
cleanup(){ [[ "$STAGE" == /www/wwwroot/.learncambodia-install.* && -d "$STAGE" && ! -L "$STAGE" ]] && rm -rf --one-file-system -- "$STAGE"; :; }
trap cleanup EXIT
chmod 700 "$STAGE"
cat > "$STAGE/mysql.cnf" <<EOF
[client]
user=$USERNAME
password=$PASSWORD
host=127.0.0.1
port=3306
protocol=tcp
EOF
chmod 600 "$STAGE/mysql.cnf"
COUNT="$("$MYSQL" --defaults-extra-file="$STAGE/mysql.cnf" --batch --skip-column-names --database="$DB" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB';" 2>/dev/null || :)"
[[ "$COUNT" == 0 ]] || fail 'dedicated database login failed or it contains tables'

# The database name alone is not evidence of least privilege. Inspect grants
# using ONLY the dedicated account; reject global privileges, other schemas,
# grant options and accounts with no database grants. Do not echo credentials.
GRANTS="$("$MYSQL" --defaults-extra-file="$STAGE/mysql.cnf" --batch --skip-column-names \
    --database="$DB" -e 'SHOW GRANTS FOR CURRENT_USER();' 2>/dev/null)" || fail 'unable to inspect dedicated database grants'
LEARN_DB="$DB" LEARN_GRANTS="$GRANTS" python3 - <<'PY'
import os
import re

database = os.environ['LEARN_DB']
lines = os.environ['LEARN_GRANTS'].strip().splitlines()
assert lines, 'no MySQL privileges returned'
schema_grants = 0
for line in lines:
    assert line.startswith('GRANT ') and ' ON ' in line and ' TO ' in line, 'unrecognized grant'
    privileges, rest = line.split(' ON ', 1)
    target = rest.split(' TO ', 1)[0].strip().replace('`', '')
    assert 'GRANT OPTION' not in line.upper(), 'user can grant privileges'
    if target == '*.*':
        assert privileges == 'GRANT USAGE', 'global MySQL privileges prohibited'
    else:
        assert target == database + '.*', 'MySQL privileges extend to another database'
        schema_grants += 1
assert schema_grants > 0, 'no site-specific database grant'
print('MYSQL_LEAST_PRIVILEGE=PASS')
PY
printf 'Type INSTALL NEW LEARNCAMBODIA to continue: ' > /dev/tty
IFS= read -r ACK < /dev/tty
[[ "$ACK" == 'INSTALL NEW LEARNCAMBODIA' ]] || fail 'not confirmed'
echo 'EMPTY_DATABASE=PASS'

echo '========== FETCH PINNED GITHUB SOURCE =========='
git clone -q --depth 1 --single-branch -b main "https://github.com/$REPO.git" "$STAGE/repo"
[[ "$(git -C "$STAGE/repo" rev-parse HEAD)" == "$MAIN" ]] || fail 'main moved during clone'
[[ "$(git -C "$STAGE/repo" rev-parse HEAD^{tree})" == "$TREE" ]] || fail 'source tree mismatch'
mkdir -m 700 "$STAGE/app"
git -C "$STAGE/repo" archive --format=tar "$MAIN" | tar -xf - -C "$STAGE/app"
printf '%s  %s\n' "$PHPLOCK" "$STAGE/app/composer.lock" | sha256sum -c --status || fail 'composer.lock changed'
printf '%s  %s\n' "$NPMLOCK" "$STAGE/app/package-lock.json" | sha256sum -c --status || fail 'package-lock.json changed'

echo '========== PINNED CI ASSETS =========='
mkdir -m 700 "$STAGE/assets"
gh run download "$ASSET_RUN" --repo "$REPO" --name "$NAME" --dir "$STAGE/assets" >/dev/null
for expected in "SOURCE_COMMIT=$TESTED" "COMPOSER_LOCK_SHA256=$PHPLOCK" "NPM_LOCK_SHA256=$NPMLOCK"; do
  grep -Fxq "$expected" "$STAGE/assets/provenance.txt" || fail 'CI artifact provenance incorrect'
done
ASSET_SHA="$(sed -n 's/^ASSET_ARCHIVE_SHA256=//p' "$STAGE/assets/provenance.txt")"
[[ "$ASSET_SHA" =~ ^[a-f0-9]{64}$ ]] || fail 'invalid archive digest'
printf '%s  %s\n' "$ASSET_SHA" "$STAGE/assets/learncambodia-vite-build.tar.gz" | sha256sum -c --status || fail 'CI asset digest mismatch'
python3 - "$STAGE/assets/learncambodia-vite-build.tar.gz" "$STAGE/app/public" <<'PY'
import json,sys,tarfile,pathlib,shutil
source=pathlib.Path(sys.argv[1]); target=pathlib.Path(sys.argv[2]); dst=target/'build'
assert not dst.exists()
with tarfile.open(source,'r:gz') as tar:
    members=tar.getmembers()
    assert 1<len(members)<1000 and sum(m.size for m in members)<50_000_000
    assert len({m.name for m in members})==len(members)
    for m in members:
        p=pathlib.PurePosixPath(m.name)
        assert not p.is_absolute() and p.parts[0]=='build' and '..' not in p.parts
        assert m.isfile() or m.isdir()
        out=target.joinpath(*p.parts)
        if m.isdir(): out.mkdir(parents=True,exist_ok=True)
        else:
            out.parent.mkdir(parents=True,exist_ok=True)
            with out.open('xb') as f: shutil.copyfileobj(tar.extractfile(m),f)
manifest=json.loads((dst/'manifest.json').read_text())
assert isinstance(manifest,dict) and manifest
for item in manifest.values():
    for p in [item.get('file')]+item.get('css',[])+item.get('assets',[]):
        if p is not None:
            a=(dst/p).resolve()
            assert a.is_relative_to(dst.resolve()) and a.is_file()
PY
echo 'GITHUB_SOURCE_AND_CI_ASSETS=PASS'

echo '========== LOCAL COMPOSER VENDOR =========='
export PATH="/www/server/php/85/bin:$PATH"
export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_HOME="$STAGE/composer-home"
mkdir -m 700 "$COMPOSER_HOME"
(cd "$STAGE/app" && "$PHP" "$COMPOSER_PHAR" install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-plugins --no-scripts) || fail 'Composer failed; no site changed'
[[ -f "$STAGE/app/vendor/autoload.php" ]] || fail 'vendor autoload missing'

echo '========== PRIVATE LARAVEL CONFIG / GUARDED WEB INSTALLER =========='
APPKEY="base64:$("$PHP" -r 'echo base64_encode(random_bytes(32));')"
SETUP="$("$PHP" -r 'echo bin2hex(random_bytes(32));')"
cat > "$STAGE/app/.env" <<EOF
APP_NAME="Learn Cambodia"
APP_ENV=production
APP_KEY=$APPKEY
APP_DEBUG=false
APP_URL=https://$DOMAIN
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stack
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB
DB_USERNAME=$USERNAME
DB_PASSWORD=$PASSWORD
SESSION_DRIVER=file
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
MAIL_FROM_ADDRESS=info@$DOMAIN
INSTALLER_ENABLED=true
INSTALLER_TOKEN=$SETUP
PUBLIC_STUDENT_PROFILES_ENABLED=false
EOF
mkdir -p "$STAGE/app/storage/app/private" "$STAGE/app/storage/framework/cache/data" "$STAGE/app/storage/framework/sessions" "$STAGE/app/storage/framework/views" "$STAGE/app/storage/logs" "$STAGE/app/bootstrap/cache"
(cd "$STAGE/app" && "$PHP" artisan package:discover --no-interaction >/dev/null && "$PHP" artisan config:cache --no-interaction >/dev/null) || fail 'Laravel package/config readiness failed'
chown -R root:www "$STAGE/app"
find "$STAGE/app" -type d -exec chmod 750 '{}' +
find "$STAGE/app" -type f -exec chmod 640 '{}' +
chown -R www:www "$STAGE/app/storage" "$STAGE/app/bootstrap/cache"
find "$STAGE/app/storage" "$STAGE/app/bootstrap/cache" -type d -exec chmod 770 '{}' +
find "$STAGE/app/storage" "$STAGE/app/bootstrap/cache" -type f -exec chmod 660 '{}' +
verify_site_placeholder
if [[ -d "$APP" ]]; then
  cp -a -- "$STAGE/app/." "$APP/"
  chown root:www "$APP"
  chmod 750 "$APP"
  # aaPanel's original .user.ini lives above the new public document root.
  # Copy its reviewed open_basedir safety net into public/, never overwrite it.
  if [[ -f "$APP/.user.ini" ]]; then
    if [[ ! -e "$APP/public/.user.ini" ]]; then
      cp -p -- "$APP/.user.ini" "$APP/public/.user.ini"
    fi
    chown root:www "$APP/public/.user.ini"
    chmod 640 "$APP/public/.user.ini"
  fi
elif [[ ! -e "$APP" ]]; then
  mv -- "$STAGE/app" "$APP"
else
  fail 'destination changed type'
fi
printf '%s\n' "$SETUP" > "$TOKEN"
chmod 600 "$TOKEN"
echo 'NEW_SITE=STAGED_WITH_PROTECTED_INSTALLER'
echo "SETUP_TOKEN_FILE=$TOKEN"
echo "INSTALL_URL=https://$DOMAIN/install"
echo 'NEXT=COMPLETE_ADMIN_WIZARD_THEN_RUN_FINALIZE'
echo 'KEEP_PRIVATE_NGINX_ACCESS_UNTIL_INDEPENDENT_SECURITY_AND_LEGAL_ACCEPTANCE'
