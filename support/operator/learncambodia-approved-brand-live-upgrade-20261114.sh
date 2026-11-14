#!/usr/bin/env bash
# Reviewed LearnCambodia PR #18: 7 Blade files + 25 static image files +
# same-commit compiled Vite assets. NO migrations, Nginx or app secrets changes.
# Usage: bash <this-file> check | upgrade
set -Eeuo pipefail
set +x
umask 077

MODE="$1"
REPO="dombong2026/learncambodia"
MAIN="4c566c8f8a6e9e77190c9a916820764546e6e304"
TESTED="7107992739465b7f208ca8280a4e490c878426de"
TREE="b500b3d6791e4bdab2cfae8de9ec539496063bde"
ZIP="/www/backup/LearnCambodia_Branding_Ready.zip"
ZIP_SHA="c5c485cb6c886672be4575ab779b20923b60cc0b69dc80dc05d0ee36af06bfbb"
ASSET_RUN="43541117733"
ASSET_ID="14091332310"
PHPLOCK="043948df6390f037947781244dcf2f39aa678a37fbdfc9879f0640ecd313b260"
NPMLOCK="f4e670ae3cd90e0e6ab27be86adf5d09558bd5ccc904f784f7d595b13d7b52c7"
SITE="/www/wwwroot/learncambodia.com"
PHP="/www/server/php/85/bin/php"
VHOST="/www/server/panel/vhost/nginx/learncambodia.com.conf"
NGINX="/www/server/nginx/sbin/nginx"
BRAND_DIR="$SITE/public/brand"
GH_DIR="/root/learncambodia-github-auth/gh"
GIT_CFG="/root/learncambodia-github-auth/gitconfig"
STAGE=""
BACKUP=""
OLD_BUILD=""
ROLLBACK=0
BRAND_CREATED=0

SOURCES="$(cat <<'FILES'
resources/views/components/brand-logo.blade.php|-
resources/views/layouts/auth.blade.php|d42d9a020f6d0a58f5526119e8af226c274957e3
resources/views/layouts/public.blade.php|c150e79e7ea6c0424b88653558f7fe3b8a6931eb
resources/views/layouts/workspace.blade.php|0f8cb485e7460942970fe011fdb90f8f742f1e11
resources/views/partials/brand-head.blade.php|-
resources/views/partials/footer.blade.php|4364bd4c98b5582e20fb7cab803b6f4c8e2fc625
resources/views/partials/header.blade.php|bb1aacb93f2d422ae97ce064084e61f46d6194cb
FILES
)"

fail() { echo "BRAND_RELEASE=BLOCKED: $*" >&2; exit 2; }

rollback() {
  echo '========== ATTEMPT LEARN CAMBODIA ONLY ROLLBACK =========='
  while IFS='|' read -r rel old; do
    [[ -n "$rel" ]] || continue
    if [[ "$old" == "-" ]]; then
      rm -f -- "$SITE/$rel" || :
    else
      cp -p -- "$BACKUP/files/$rel" "$SITE/$rel" || echo "RESTORE_FAILED=$rel"
    fi
  done <<< "$SOURCES"
  if [[ "$BRAND_CREATED" -eq 1 && -d "$BRAND_DIR" && ! -L "$BRAND_DIR" ]]; then
    find "$BRAND_DIR" -maxdepth 1 -type f -delete
    rmdir -- "$BRAND_DIR" 2>/dev/null || :
  fi
  rm -f -- "$SITE/public/favicon.ico" "$SITE/public/apple-touch-icon.png"
  if [[ -n "$OLD_BUILD" && -d "$OLD_BUILD" && ! -L "$OLD_BUILD" ]]; then
    if [[ -d "$SITE/public/build" && ! -L "$SITE/public/build" ]]; then
      mv -- "$SITE/public/build" "$BACKUP/failed-build" || :
    fi
    mv -- "$OLD_BUILD" "$SITE/public/build" || :
  fi
  (
    cd "$SITE"
    runuser -u www -- "$PHP" artisan view:clear --no-interaction >/dev/null &&
    runuser -u www -- "$PHP" artisan view:cache --no-interaction >/dev/null &&
    runuser -u www -- "$PHP" artisan up --no-interaction >/dev/null
  ) || echo 'ROLLBACK_MAINTENANCE=MANUAL_REVIEW'
  echo 'SITE_ONLY_ROLLBACK=ATTEMPTED'
}
cleanup() {
  local status=$?
  trap - EXIT
  set +e
  if [[ "$status" -ne 0 && "$ROLLBACK" -eq 1 && -n "$BACKUP" ]]; then rollback; fi
  if [[ -n "$STAGE" && "$STAGE" == /root/learncambodia-brand-release.* && -d "$STAGE" && ! -L "$STAGE" ]]; then
    rm -rf --one-file-system -- "$STAGE"
  fi
  [[ -z "$BACKUP" ]] || echo "PRIVATE_RELEASE_BACKUP=$BACKUP"
  [[ "$status" -ne 0 ]] || echo "BRAND_MODE_$MODE=PASS"
  exit "$status"
}
trap cleanup EXIT

echo '========== VERIFY FINALIZED SITE AND APPROVED PACKAGE =========='
[[ "$(id -u)" == 0 && ( "$MODE" == check || "$MODE" == upgrade ) ]] || fail 'root and check/upgrade required'
for cmd in git gh python3 sha256sum runuser curl tar; do command -v "$cmd" >/dev/null || fail "missing tool $cmd"; done
[[ -d "$SITE" && ! -L "$SITE" && -f "$SITE/artisan" && -f "$SITE/vendor/autoload.php" && -x "$PHP" ]] || fail 'installed app missing'
[[ -f "$SITE/.env" && ! -L "$SITE/.env" && -f "$SITE/storage/app/private/learncambodia.installed" ]] || fail 'installer not finalized'
grep -Fxq 'INSTALLER_ENABLED=false' "$SITE/.env" || fail 'installer enabled'
[[ ! -e /root/learncambodia-setup-token && ! -e "$SITE/storage/framework/down" ]] || fail 'token remains or Laravel is in maintenance'
[[ -f "$VHOST" && ! -L "$VHOST" && -x "$NGINX" ]] || fail 'Nginx site unavailable'
grep -Fq "root $SITE/public;" "$VHOST" || fail 'unexpected public root'
grep -Fq "$SITE/.private-nginx-auth/learncambodia.htpasswd" "$VHOST" || fail 'site Basic Auth configuration differs'
"$NGINX" -t >/dev/null 2>&1 || fail 'Nginx syntax'
[[ ! -e "$BRAND_DIR" && ! -L "$BRAND_DIR" && ! -e "$SITE/public/favicon.ico" && ! -e "$SITE/public/apple-touch-icon.png" ]] || fail 'brand already exists or unknown existing file'
[[ -f "$ZIP" && ! -L "$ZIP" && "$(sha256sum "$ZIP" | awk '{print $1}')" == "$ZIP_SHA" ]] || fail 'approved uploaded ZIP missing or altered'
# The old asset *publisher* is pinned to the pre-merge Main and must NOT be
# reused as a production validator after PR #18 is merged. Instead validate the
# immutable, operator-approved archive offline against its exact SHA-256 above.
# GitHub blob-by-blob comparison against the merged reviewed tree runs later.
python3 - "$ZIP" <<'PY'
from pathlib import PurePosixPath
import hashlib
import json
import struct
import sys
import zipfile

names = {
    'SHA256SUMS.json', 'android-chrome-192x192.png',
    'android-chrome-512x512.png', 'app-icon-512.png',
    'app-icon-source.png', 'apple-touch-icon.png',
    'favicon-128.png', 'favicon-16.png', 'favicon-192.png',
    'favicon-256.png', 'favicon-32.png', 'favicon-48.png',
    'favicon-512.png', 'favicon-96.png', 'favicon.ico',
    'favicon.png', 'logo-dark.png', 'logo-light.png',
    'mark-source.png', 'maskable-192x192.png',
    'maskable-512x512.png', 'mstile-144x144.png',
    'site.webmanifest',
}
approved = {
    'logo-dark.png': '5ca0b912923decf9975e77cb36b437ea9b93ebeb883b8c752cb04159df5d2d80',
    'logo-light.png': '343b91b564a3a8d8bb6cc8980f5806f67d96295d8feac42f1c6c039b2a51e26c',
    'favicon.ico': '488ebc01d36686d48e1c932dc6a1f91b566841a218e8c0e7217a19ac48c22b51',
}
with zipfile.ZipFile(sys.argv[1]) as archive:
    members = archive.infolist()
    paths = {m.filename for m in members}
    assert len(members) == len(names) == len(paths)
    assert paths == {'brand/' + name for name in names}
    assert sum(m.file_size for m in members) < 8_000_000
    for member in members:
        path = PurePosixPath(member.filename)
        assert len(path.parts) == 2 and path.parts[0] == 'brand'
        assert not member.is_dir()
        assert ((member.external_attr >> 16) & 0o170000) != 0o120000
    checks = json.loads(archive.read('brand/SHA256SUMS.json'))
    assert set(checks) == names - {'SHA256SUMS.json'}
    for name in names - {'SHA256SUMS.json'}:
        data = archive.read('brand/' + name)
        actual = hashlib.sha256(data).hexdigest()
        assert actual == checks[name]['sha256'] and len(data) == checks[name]['bytes']
        if name in approved:
            assert actual == approved[name], 'Approved identity mismatch: ' + name
        if name.endswith('.png'):
            assert data[:8] == b'\x89PNG\r\n\x1a\n'
            width, height = struct.unpack('>II', data[16:24])
            assert 0 < width <= 4000 and 0 < height <= 4000
        elif name.endswith('.ico'):
            assert data[:4] == b'\0\0\x1\0'
    manifest = json.loads(archive.read('brand/site.webmanifest'))
    assert manifest['name'] == 'LearnCambodia'
    assert {i.get('purpose') for i in manifest['icons']} == {'any','maskable'}
print('APPROVED_PACKAGE_SHA256_AND_PER_ASSET_HASHES=PASS')
print('EXACT_APPROVED_BRAND_FILES=23')
PY
printf '%s  %s\n' "$PHPLOCK" "$SITE/composer.lock" | sha256sum -c --status || fail 'Composer dependency lock drift'
printf '%s  %s\n' "$NPMLOCK" "$SITE/package-lock.json" | sha256sum -c --status || fail 'npm dependency lock drift'
CODE="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 12 \
  --resolve learncambodia.com:443:127.0.0.1 -o /dev/null \
  -w '%{http_code}:%{ssl_verify_result}' https://learncambodia.com/login)" || fail 'origin HTTPS unreachable'
[[ "$CODE" == '401:0' ]] || fail "Basic Auth protection missing: $CODE"
echo 'STAGED_SITE_PRIVATE_HTTPS_AND_APPROVED_ZIP=PASS'

echo '========== SAME-SOURCE GITHUB MAIN AND CI =========='
[[ -d "$GH_DIR" && ! -L "$GH_DIR" && -f "$GIT_CFG" && ! -L "$GIT_CFG" ]] || fail 'isolated GitHub access missing'
export GH_CONFIG_DIR="$GH_DIR"
export GIT_CONFIG_GLOBAL="$GIT_CFG"
export GIT_CONFIG_NOSYSTEM=1
[[ "$(gh api user --jq .login)" == dombong2026 ]] || fail 'wrong GitHub account'
[[ "$(gh api "repos/$REPO/branches/main" --jq '.commit.sha')" == "$MAIN" ]] || fail 'Main changed, review new release'
[[ "$(gh api "repos/$REPO/git/commits/$MAIN" --jq '.tree.sha')" == "$TREE" ]] || fail 'merged tree changed'
[[ "$(gh api "repos/$REPO/git/commits/$TESTED" --jq '.tree.sha')" == "$TREE" ]] || fail 'tested tree differs'
for run in 43541117655 43541117733 43541117797; do
  [[ "$(gh api "repos/$REPO/actions/runs/$run" --jq '.head_sha')" == "$TESTED" ]] || fail "CI SHA drift $run"
  [[ "$(gh api "repos/$REPO/actions/runs/$run" --jq '.conclusion')" == success ]] || fail "CI incomplete $run"
done
[[ "$(gh api "repos/$REPO/actions/artifacts/$ASSET_ID" --jq '.name')" == learncambodia-pinned-qa-vite-assets ]] || fail 'unexpected Vite asset'
[[ "$(gh api "repos/$REPO/actions/artifacts/$ASSET_ID" --jq '.expired')" == false ]] || fail 'CI assets expired'
echo 'MERGED_MAIN_AND_ALL_ACCEPTANCE=PASS'

echo '========== VERIFY EXACT EXISTING BLADE FILES =========='
while IFS='|' read -r rel old; do
  [[ -n "$rel" ]] || continue
  [[ "$rel" != /* && "$rel" != *..* && -d "$(dirname "$SITE/$rel")" && ! -L "$(dirname "$SITE/$rel")" ]] || fail "unsafe destination $rel"
  if [[ "$old" == "-" ]]; then
    [[ ! -e "$SITE/$rel" && ! -L "$SITE/$rel" ]] || fail "unexpected new component exists: $rel"
  else
    [[ -f "$SITE/$rel" && ! -L "$SITE/$rel" && "$(git hash-object "$SITE/$rel")" == "$old" ]] || fail "site source diverged: $rel"
  fi
done <<< "$SOURCES"
echo 'LIVE_BASELINE_BLADE_FILES=PASS'
[[ "$MODE" == check ]] && exit 0

echo '========== OPERATOR CONFIRMATION =========='
printf 'Type DEPLOY APPROVED LEARN CAMBODIA BRAND to continue: ' >/dev/tty
IFS= read -r ACK </dev/tty
[[ "$ACK" == 'DEPLOY APPROVED LEARN CAMBODIA BRAND' ]] || fail 'not authorized'
unset ACK

STAGE="$(mktemp -d /root/learncambodia-brand-release.XXXXXXXX)"
mkdir -m 700 "$STAGE/vite" "$STAGE/extracted" "$STAGE/approved"
echo '========== DOWNLOAD AND VERIFY PINNED MAIN SOURCE =========='
gh repo clone "$REPO" "$STAGE/repo" -- -b main --depth 1 --quiet ||
  fail 'repository clone failed'
[[ "$(git -C "$STAGE/repo" rev-parse HEAD)" == "$MAIN" && "$(git -C "$STAGE/repo" rev-parse HEAD^{tree})" == "$TREE" ]] || fail 'source checkout mismatch'
while IFS='|' read -r rel old; do
  [[ -n "$rel" ]] || continue
  [[ -f "$STAGE/repo/$rel" && ! -L "$STAGE/repo/$rel" ]] || fail "reviewed Blade missing: $rel"
  [[ "$(git hash-object "$STAGE/repo/$rel")" == "$(git -C "$STAGE/repo" rev-parse "HEAD:$rel")" ]] || fail "reviewed Blade mismatch: $rel"
done <<< "$SOURCES"

echo '========== VERIFY EXACT GITHUB ASSETS MATCH OPERATOR ZIP =========='
python3 - "$ZIP" "$STAGE/repo/public/brand" "$STAGE/approved" <<'PY'
from pathlib import Path
import hashlib,sys,zipfile
zip_name, git_brand, out_dir=sys.argv[1:]
git_brand=Path(git_brand)
out_dir=Path(out_dir)
with zipfile.ZipFile(zip_name) as z:
    names=z.namelist()
    assert len(names)==23 and len(set(names))==23
    for member in names:
        assert member.startswith('brand/') and '/' not in member[6:]
        name=member[6:]
        raw=z.read(member)
        assert hashlib.sha256(raw).digest() == hashlib.sha256((git_brand/name).read_bytes()).digest(), 'GitHub asset mismatch: '+name
        target=out_dir/name
        target.write_bytes(raw)
print('GITHUB_ASSETS_MATCH_EXACT_APPROVED_ZIP=PASS')
PY
[[ "$(git hash-object "$STAGE/repo/public/favicon.ico")" == "$(git hash-object "$STAGE/approved/favicon.ico")" ]] || fail 'root favicon differs'
[[ "$(git hash-object "$STAGE/repo/public/apple-touch-icon.png")" == "$(git hash-object "$STAGE/approved/apple-touch-icon.png")" ]] || fail 'root Apple icon differs'

echo '========== VERIFY CI COMPILED VITE ASSETS =========='
gh run download "$ASSET_RUN" --repo "$REPO" --name learncambodia-pinned-qa-vite-assets --dir "$STAGE/vite" >/dev/null ||
  fail 'CI Vite asset download failed'
for item in "SOURCE_COMMIT=$TESTED" "COMPOSER_LOCK_SHA256=$PHPLOCK" "NPM_LOCK_SHA256=$NPMLOCK"; do
  grep -Fxq "$item" "$STAGE/vite/provenance.txt" || fail 'Vite artifact provenance mismatch'
done
ART_SHA="$(sed -n 's/^ASSET_ARCHIVE_SHA256=//p' "$STAGE/vite/provenance.txt")"
[[ "$ART_SHA" =~ ^[a-f0-9]{64}$ ]] || fail 'Vite artifact digest malformed'
printf '%s  %s\n' "$ART_SHA" "$STAGE/vite/learncambodia-vite-build.tar.gz" | sha256sum -c --status || fail 'Vite archive checksum mismatch'
python3 - "$STAGE/vite/learncambodia-vite-build.tar.gz" "$STAGE/extracted" <<'PY'
import json,sys,tarfile
from pathlib import Path,PurePosixPath
archive,dest=map(Path,sys.argv[1:])
with tarfile.open(archive,'r:gz') as t:
    items=t.getmembers()
    assert 1<len(items)<1000 and sum(m.size for m in items)<50_000_000
    assert len({m.name for m in items})==len(items)
    for m in items:
        p=PurePosixPath(m.name)
        assert p.parts[0]=='build' and '..' not in p.parts and not p.is_absolute()
        assert m.isdir() or m.isfile()
        out=dest.joinpath(*p.parts)
        if m.isdir():out.mkdir(parents=True,exist_ok=True)
        else:
            out.parent.mkdir(parents=True,exist_ok=True)
            with out.open('xb') as f:f.write(t.extractfile(m).read())
root=dest/'build'
data=json.loads((root/'manifest.json').read_text())
assert isinstance(data,dict) and data
for item in data.values():
    for name in [item.get('file')]+item.get('css',[])+item.get('assets',[]):
        if name:
            f=(root/name).resolve(strict=True)
            assert f.is_relative_to(root.resolve()) and f.is_file()
print('CI_VITE_ASSET_TREE=PASS')
PY

echo '========== BACKUP APPLICATION SOURCE, ASSETS AND DATABASE =========='
BACKUP="$(mktemp -d /root/learncambodia-brand-backup.XXXXXXXX)"
mkdir -m 700 "$BACKUP/files"
while IFS='|' read -r rel old; do
  [[ -n "$rel" && "$old" != "-" ]] || continue
  mkdir -p "$BACKUP/files/$(dirname "$rel")"
  cp -p -- "$SITE/$rel" "$BACKUP/files/$rel"
done <<< "$SOURCES"
cp -a -- "$SITE/public/build" "$BACKUP/compiled-build"
DUMP="$(command -v mysqldump || :)"
[[ -x "$DUMP" ]] || DUMP="/www/server/mysql/bin/mysqldump"
[[ -x "$DUMP" ]] || fail 'MySQL backup unavailable'
python3 - "$SITE/.env" "$STAGE/sql.cnf" <<'PY'
from pathlib import Path
import re,sys
env,cnf=map(Path,sys.argv[1:]); cfg={}
for line in env.read_text().splitlines():
    if '=' in line and not line.startswith('#'):
        k,v=line.split('=',1); cfg[k]=v.strip().strip('"').strip("'")
assert cfg.get('DB_CONNECTION')=='mysql'
assert cfg.get('DB_DATABASE')=='sql_learncambodia_com'
assert cfg.get('DB_USERNAME')=='sql_learncambodia_com'
assert cfg.get('DB_HOST')=='127.0.0.1'
assert re.fullmatch(r'[a-zA-Z0-9_-]{24,128}',cfg.get('DB_PASSWORD',''))
cnf.write_text('[client]\nprotocol=tcp\nhost=127.0.0.1\nport=3306\nuser='+cfg['DB_USERNAME']+'\npassword='+cfg['DB_PASSWORD']+'\n')
cnf.chmod(0o600)
PY
"$DUMP" --defaults-extra-file="$STAGE/sql.cnf" --single-transaction --quick --no-tablespaces \
  --default-character-set=utf8mb4 sql_learncambodia_com >"$BACKUP/database.sql" || fail 'DB backup failed'
rm -f -- "$STAGE/sql.cnf"
[[ -s "$BACKUP/database.sql" ]] || fail 'DB backup empty'
chmod 600 "$BACKUP/database.sql"
echo 'PRIVATE_SOURCE_FRONTEND_AND_DB_BACKUP=PASS'

echo '========== GUARDED BRAND INSTALLATION =========='
ROLLBACK=1
(cd "$SITE" && runuser -u www -- "$PHP" artisan down --no-interaction >/dev/null) || fail 'could not enter maintenance'
OLD_BUILD="$SITE/public/.learncambodia-prebrand-build.$$.private"
[[ ! -e "$OLD_BUILD" && ! -L "$OLD_BUILD" ]] || fail 'old build path collision'
mv -- "$SITE/public/build" "$OLD_BUILD"
install -d -o root -g www -m 755 "$BRAND_DIR"
BRAND_CREATED=1
while IFS='|' read -r rel old; do
  [[ -n "$rel" ]] || continue
  tmp="$(mktemp "$(dirname "$SITE/$rel")/.lcam-brand.XXXXXXXX")"
  cp -- "$STAGE/repo/$rel" "$tmp"
  chown root:www "$tmp"
  chmod 640 "$tmp"
  mv -f -- "$tmp" "$SITE/$rel"
  [[ "$(git hash-object "$SITE/$rel")" == "$(git -C "$STAGE/repo" rev-parse "HEAD:$rel")" ]] || fail "Blade hash mismatch: $rel"
done <<< "$SOURCES"
for f in "$STAGE/approved/"*; do
  name="$(basename "$f")"
  tmp="$(mktemp "$BRAND_DIR/.asset.XXXXXXXX")"
  cp -- "$f" "$tmp"
  chown root:www "$tmp"
  chmod 644 "$tmp"
  mv -- "$tmp" "$BRAND_DIR/$name"
done
for name in favicon.ico apple-touch-icon.png; do
  tmp="$(mktemp "$SITE/public/.lcam-root-icon.XXXXXXXX")"
  cp -- "$STAGE/approved/$name" "$tmp"
  chown root:www "$tmp"
  chmod 644 "$tmp"
  mv -- "$tmp" "$SITE/public/$name"
done
chown -R root:www "$STAGE/extracted/build"
find "$STAGE/extracted/build" -type d -exec chmod 750 '{}' +
find "$STAGE/extracted/build" -type f -exec chmod 640 '{}' +
mv -- "$STAGE/extracted/build" "$SITE/public/build"
runuser -u www -- test -r "$SITE/public/brand/logo-light.png" || fail 'light logo unreadable'
runuser -u www -- test -r "$SITE/public/brand/logo-dark.png" || fail 'dark logo unreadable'
runuser -u www -- test -r "$SITE/public/brand/favicon.ico" || fail 'favicon unreadable'
(
  cd "$SITE"
  runuser -u www -- "$PHP" artisan view:clear --no-interaction >/dev/null &&
  runuser -u www -- "$PHP" artisan view:cache --no-interaction >/dev/null &&
  runuser -u www -- "$PHP" artisan up --no-interaction >/dev/null
) || fail 'Blade compilation or leaving maintenance failed'
CODE="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 12 \
  --resolve learncambodia.com:443:127.0.0.1 -o /dev/null \
  -w '%{http_code}:%{ssl_verify_result}' https://learncambodia.com/login)" || fail 'origin SSL failed after installation'
[[ "$CODE" == '401:0' ]] || fail 'origin access gate was changed'
[[ -f "$SITE/storage/app/private/learncambodia.installed" ]] || fail 'installer lock lost'
ROLLBACK=0
mv -- "$OLD_BUILD" "$BACKUP/old-live-build" || echo 'PREVIOUS_BUILD=RETAINED_PRIVATELY_IN_PUBLIC_DOT_PATH'
echo "BRANDING_RELEASE_COMMIT=$MAIN"
echo 'LIGHT_DARK_LOGOS_AND_ALL_DEVICES=DEPLOYED'
echo 'CI_SOURCE_AND_VITE_ASSETS=PASS'
echo 'DATABASE_MIGRATIONS=NOT_RUN'
echo 'OTHER_SITES=UNCHANGED'
echo 'SITE_BASIC_AUTH=STILL_ENABLED'
echo 'NEXT=PHYSICAL_DEVICE_BRANDING_ACCEPTANCE'
