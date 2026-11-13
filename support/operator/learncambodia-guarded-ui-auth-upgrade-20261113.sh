#!/usr/bin/env bash
# Learn Cambodia only: reviewed UI/auth source and CI-built Vite assets.
# Usage: bash this-script check | upgrade
set -Eeuo pipefail
set +x
umask 077
MODE="$1"
R="dombong2026/learncambodia"
OLD="db2a5cdd6fa3a3b6b2066fdb8c95084e4dbb387e"
NEW="9a86dec6694fafe01eaf2bd27295e71f7eb9db99"
TESTED="13bde35519a721062ecdeabedeab1a58037dc0f5"
TREE="17f03bf9689f110efe9a981f3f9db1bbc02571b3"
APP="/www/wwwroot/learncambodia.com"
PHP="/www/server/php/85/bin/php"
STAGE=""
BACKUP=""
FILES=""
ROLLBACK=0
OLD_BUILD=""
fail(){ echo "UPGRADE=BLOCKED:$*" >&2; exit 2; }
restore_site(){
  while IFS= read -r f; do
    [[ -n "$f" ]] || continue
    if [[ -f "$BACKUP/files/$f" ]]; then
      cp -p "$BACKUP/files/$f" "$APP/$f" || :
    else
      rm -f -- "$APP/$f"
    fi
  done <<< "$FILES"
  if [[ -n "$OLD_BUILD" && -d "$OLD_BUILD" && ! -L "$OLD_BUILD" ]]; then
    [[ ! -e "$APP/public/build" ]] || mv "$APP/public/build" "$BACKUP/failed-build"
    mv "$OLD_BUILD" "$APP/public/build" || :
  fi
  (cd "$APP"; runuser -u www -- "$PHP" artisan config:clear --no-interaction >/dev/null;
    runuser -u www -- "$PHP" artisan config:cache --no-interaction >/dev/null;
    runuser -u www -- "$PHP" artisan route:clear --no-interaction >/dev/null;
    runuser -u www -- "$PHP" artisan view:clear --no-interaction >/dev/null;
    runuser -u www -- "$PHP" artisan up --no-interaction >/dev/null) || :
  echo 'SITE_ROLLBACK=ATTEMPTED'
}
cleanup(){
  result=$?
  trap - EXIT
  set +e
  if [[ "$result" -ne 0 && "$ROLLBACK" -eq 1 && -n "$BACKUP" ]]; then restore_site; fi
  if [[ -n "$STAGE" && "$STAGE" == /root/learncambodia-ui-qa.* && -d "$STAGE" && ! -L "$STAGE" ]]; then
    rm -rf --one-file-system "$STAGE"
  fi
  [[ -z "$BACKUP" ]] || echo "PRIVATE_BACKUP=$BACKUP"
  exit "$result"
}
trap cleanup EXIT

echo '========== VERIFY FINALIZED LARAVEL SITE =========='
[[ "$(id -u)" == 0 && ( "$MODE" == check || "$MODE" == upgrade ) ]] || fail 'root and check/upgrade required'
for cmd in gh git python3 sha256sum curl runuser tar; do command -v "$cmd" >/dev/null || fail "missing $cmd"; done
[[ -d "$APP" && ! -L "$APP" && -x "$PHP" && -f "$APP/artisan" && -f "$APP/vendor/autoload.php" ]] || fail 'site not installed'
[[ -f "$APP/.env" && -f "$APP/storage/app/private/learncambodia.installed" ]] || fail 'installer not finalized'
grep -Fxq 'INSTALLER_ENABLED=false' "$APP/.env" || fail 'installer unexpectedly enabled'
[[ ! -e /root/learncambodia-setup-token ]] || fail 'setup token still present'
[[ ! -e "$APP/storage/framework/down" ]] || fail 'site already in maintenance'
[[ -d "$APP/public/build" && ! -L "$APP/public/build" ]] || fail 'existing assets unsafe'
grep -Fq "$APP/.private-nginx-auth/learncambodia.htpasswd" /www/server/panel/vhost/nginx/learncambodia.com.conf || fail 'site private Basic Auth differs'
/www/server/nginx/sbin/nginx -t >/dev/null 2>&1 || fail 'Nginx invalid'
code="$(curl -sS --noproxy '*' --resolve learncambodia.com:443:127.0.0.1 -o /dev/null -w '%{http_code}:%{ssl_verify_result}' https://learncambodia.com/login)" || fail 'origin TLS failed'
[[ "$code" == '401:0' ]] || fail 'website is not protected'

echo '========== VERIFY REVIEWED MAIN AND CI =========='
[[ "$(gh api "repos/$R/branches/main" --jq '.commit.sha')" == "$NEW" ]] || fail 'main moved'
[[ "$(gh api "repos/$R/git/commits/$NEW" --jq '.tree.sha')" == "$TREE" ]] || fail 'main tree drift'
[[ "$(gh api "repos/$R/git/commits/$TESTED" --jq '.tree.sha')" == "$TREE" ]] || fail 'CI tree mismatch'
for run in 43464088859 43464089125 43464093399; do
  [[ "$(gh api "repos/$R/actions/runs/$run" --jq '.head_sha')" == "$TESTED" ]] || fail 'CI source drift'
  [[ "$(gh api "repos/$R/actions/runs/$run" --jq '.conclusion')" == success ]] || fail 'CI failed'
done
[[ "$(gh api "repos/$R/actions/artifacts/14056544517" --jq '.expired')" == false ]] || fail 'reviewed Vite assets expired'

STAGE="$(mktemp -d /root/learncambodia-ui-qa.XXXXXXXX)"
git clone -q --single-branch --depth 1 -b main "https://github.com/$R.git" "$STAGE/src"
[[ "$(git -C "$STAGE/src" rev-parse HEAD)" == "$NEW" ]] || fail 'cloned main drift'
git -C "$STAGE/src" fetch -q --depth 1 origin "$OLD"
[[ "$(git -C "$STAGE/src" rev-parse HEAD^{tree})" == "$TREE" ]] || fail 'wrong release tree'
diff="$(git -C "$STAGE/src" diff --name-only "$OLD" "$NEW")"
[[ "$(printf '%s\n' "$diff" | grep -c .)" == 29 ]] || fail 'unreviewed source diff'
count=0
while IFS= read -r f; do
  [[ -n "$f" ]] || continue
  case "$f" in
    tests/*) continue ;;
    app/Http/Controllers/Auth/*.php|app/Http/Controllers/LocaleController.php|app/Http/Middleware/SetLocale.php|app/Models/User.php|lang/en/auth.php|lang/km/auth.php|lang/zh/auth.php|resources/css/app.css|resources/js/app.ts|resources/views/auth/*.blade.php|resources/views/components/auth-field.blade.php|resources/views/layouts/auth.blade.php|resources/views/layouts/workspace.blade.php|resources/views/partials/header.blade.php|routes/web.php) ;;
    *) fail "unapproved file $f" ;;
  esac
  [[ "$f" != /* && "$f" != *..* && -d "$(dirname "$APP/$f")" ]] || fail "unsafe file $f"
  [[ ! -L "$APP/$f" ]] || fail "live symlink $f"
  if git -C "$STAGE/src" cat-file -e "$OLD:$f" 2>/dev/null; then
    expected="$(git -C "$STAGE/src" rev-parse "$OLD:$f")"
    [[ -f "$APP/$f" && "$(git hash-object "$APP/$f")" == "$expected" ]] || fail "live custom changes: $f"
  else
    [[ ! -e "$APP/$f" ]] || fail "new file already exists: $f"
  fi
  case "$f" in *.blade.php) ;; *.php) "$PHP" -l "$STAGE/src/$f" >/dev/null || fail "PHP syntax: $f";; esac
  FILES="$FILES$f
"
  count=$((count+1))
done <<< "$diff"
[[ "$count" == 20 ]] || fail 'unexpected runtime source count'
echo 'LIVE_BASELINE_AND_SOURCE=PASS'
[[ "$MODE" == check ]] && exit 0

echo '========== CONFIRM UPGRADE (NO MIGRATIONS) =========='
printf 'Type UPGRADE LEARN CAMBODIA UI to continue: ' > /dev/tty
IFS= read -r answer < /dev/tty
[[ "$answer" == 'UPGRADE LEARN CAMBODIA UI' ]] || fail 'not confirmed'
unset answer

echo '========== DOWNLOAD PINNED FRONTEND BUILD =========='
mkdir -m 700 "$STAGE/assets" "$STAGE/extracted"
gh run download 43464089125 --repo "$R" --name learncambodia-pinned-qa-vite-assets --dir "$STAGE/assets" >/dev/null
grep -Fxq "SOURCE_COMMIT=$TESTED" "$STAGE/assets/provenance.txt" || fail 'Vite commit mismatch'
sha="$(sed -n 's/^ASSET_ARCHIVE_SHA256=//p' "$STAGE/assets/provenance.txt")"
[[ "$sha" =~ ^[a-f0-9]{64}$ ]] || fail 'invalid Vite digest'
printf '%s  %s\n' "$sha" "$STAGE/assets/learncambodia-vite-build.tar.gz" | sha256sum -c --status || fail 'Vite checksum mismatch'
python3 - "$STAGE/assets/learncambodia-vite-build.tar.gz" "$STAGE/extracted" <<'PY'
import json,sys,tarfile,pathlib
archive=pathlib.Path(sys.argv[1]); dest=pathlib.Path(sys.argv[2])
with tarfile.open(archive,'r:gz') as t:
    members=t.getmembers()
    assert 1<len(members)<1000 and sum(m.size for m in members)<50_000_000
    assert len({m.name for m in members})==len(members)
    for member in members:
        p=pathlib.PurePosixPath(member.name)
        assert p.parts[0]=='build' and '..' not in p.parts and not p.is_absolute()
        assert member.isdir() or member.isfile()
        path=dest.joinpath(*p.parts)
        if member.isdir(): path.mkdir(parents=True,exist_ok=True)
        else:
            path.parent.mkdir(parents=True,exist_ok=True)
            with path.open('xb') as fd: fd.write(t.extractfile(member).read())
base=dest/'build'
data=json.loads((base/'manifest.json').read_text())
assert isinstance(data,dict) and data
for record in data.values():
    for name in [record.get('file')]+record.get('css',[])+record.get('assets',[]):
        if name:
            path=(base/name).resolve(strict=True)
            assert path.is_relative_to(base.resolve()) and path.is_file()
PY
echo 'PINNED_ASSETS=PASS'

echo '========== PRIVATE FILE BACKUP =========='
BACKUP="$(mktemp -d /root/learncambodia-ui-backup.XXXXXXXX)"
mkdir -m 700 "$BACKUP/files"
while IFS= read -r f; do
  [[ -n "$f" && -f "$APP/$f" ]] || continue
  mkdir -p "$BACKUP/files/$(dirname "$f")"
  cp -p "$APP/$f" "$BACKUP/files/$f"
done <<< "$FILES"
cp -a "$APP/public/build" "$BACKUP/build"
cp -p "$APP/bootstrap/cache/config.php" "$BACKUP/config.php"
DUMP="$(command -v mysqldump || :)"
[[ -x "$DUMP" ]] || DUMP="/www/server/mysql/bin/mysqldump"
[[ -x "$DUMP" ]] || fail 'MySQL backup tool missing; abort before site changes'
python3 - "$APP/.env" "$STAGE/site-mysql.cnf" <<'PY'
from pathlib import Path
import re, sys
env, cnf = map(Path, sys.argv[1:])
settings = {}
for line in env.read_text().splitlines():
    if '=' in line and not line.startswith('#'):
        name, value = line.split('=', 1)
        settings[name] = value.strip().strip('"').strip("'")
assert settings.get('DB_CONNECTION') == 'mysql'
assert settings.get('DB_DATABASE') == 'sql_learncambodia_com'
assert settings.get('DB_USERNAME') == 'sql_learncambodia_com'
assert settings.get('DB_HOST') == '127.0.0.1'
assert re.fullmatch(r'[A-Za-z0-9_-]{24,128}', settings.get('DB_PASSWORD', ''))
cnf.write_text('[client]\nprotocol=tcp\nhost=127.0.0.1\nport=3306\nuser=' +
    settings['DB_USERNAME'] + '\npassword=' + settings['DB_PASSWORD'] + '\n')
cnf.chmod(0o600)
PY
"$DUMP" --defaults-extra-file="$STAGE/site-mysql.cnf" --single-transaction --quick --no-tablespaces   sql_learncambodia_com > "$BACKUP/database.sql" ||
  fail 'read-only database backup failed; no files changed'
rm -f -- "$STAGE/site-mysql.cnf"
[[ -s "$BACKUP/database.sql" ]] || fail 'database backup is empty'
chmod 600 "$BACKUP/database.sql"
echo 'PRIVATE_SOURCE_VITE_AND_DATABASE_BACKUP=PASS'

echo '========== APPLY MERGED SOURCE AND FRONTEND =========='
ROLLBACK=1
(cd "$APP"; runuser -u www -- "$PHP" artisan down --no-interaction >/dev/null) || fail 'maintenance failed'
OLD_BUILD="$APP/public/.learncambodia-previous-build.$$.private"
[[ ! -e "$OLD_BUILD" ]] || fail 'build path collision'
mv "$APP/public/build" "$OLD_BUILD"
while IFS= read -r f; do
  [[ -n "$f" ]] || continue
  tmp="$(mktemp "$(dirname "$APP/$f")/.learncambodia-new.XXXXXXXX")"
  cp "$STAGE/src/$f" "$tmp"
  chown root:www "$tmp"
  chmod 640 "$tmp"
  mv -f "$tmp" "$APP/$f"
done <<< "$FILES"
chown -R root:www "$STAGE/extracted/build"
find "$STAGE/extracted/build" -type d -exec chmod 750 '{}' +
find "$STAGE/extracted/build" -type f -exec chmod 640 '{}' +
mv "$STAGE/extracted/build" "$APP/public/build"
(
 cd "$APP"
 runuser -u www -- "$PHP" artisan route:clear --no-interaction >/dev/null &&
 runuser -u www -- "$PHP" artisan view:clear --no-interaction >/dev/null &&
 runuser -u www -- "$PHP" artisan config:clear --no-interaction >/dev/null &&
 runuser -u www -- "$PHP" artisan config:cache --no-interaction >/dev/null &&
 runuser -u www -- "$PHP" artisan view:cache --no-interaction >/dev/null &&
 runuser -u www -- "$PHP" artisan route:list --path=forgot-password --no-interaction >/dev/null &&
 runuser -u www -- "$PHP" artisan route:list --path=email/verify --no-interaction >/dev/null &&
 runuser -u www -- "$PHP" artisan up --no-interaction >/dev/null
) || fail 'Laravel post-upgrade acceptance failed'
[[ -f "$APP/storage/app/private/learncambodia.installed" ]] || fail 'installer lock changed'
code="$(curl -sS --noproxy '*' --resolve learncambodia.com:443:127.0.0.1 -o /dev/null -w '%{http_code}:%{ssl_verify_result}' https://learncambodia.com/login)" || fail 'origin TLS failed'
[[ "$code" == '401:0' ]] || fail 'private Basic Auth no longer enforced'
ROLLBACK=0
if mv -- "$OLD_BUILD" "$BACKUP/previous-live-build" 2>/dev/null; then
  echo 'OLD_COMPILED_ASSETS=BACKED_UP_OUTSIDE_WEB_ROOT'
else
  echo 'OLD_COMPILED_ASSETS=RETAINED_IN_PRIVATE_DOT_DIRECTORY'
fi
echo "DEPLOYED_MAIN=$NEW"
echo 'NO_DATABASE_MIGRATIONS=PASS'
echo 'OTHER_SITES=UNCHANGED'
echo 'BASIC_AUTH=STILL_ENABLED'
echo 'NEXT=PHYSICAL_BROWSER_AND_ROLE_ACCEPTANCE'
