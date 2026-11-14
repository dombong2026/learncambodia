#!/usr/bin/env bash
# SHA-pinned approved logo/device icon upload to GitHub review branch only.
# Usage: bash script check|publish /root/LearnCambodia_Approved_Brand_All_Devices_2026-11-14.zip
set -Eeuo pipefail
set +x
umask 077

MODE="$1"
ARCHIVE="$2"
REPO="dombong2026/learncambodia"
BRANCH="feature/20261114-approved-responsive-brand"
MAIN="76a01b8a3dc68088d64af0a592d248f595d07d6b"
ZIP_SHA="c5c485cb6c886672be4575ab779b20923b60cc0b69dc80dc05d0ee36af06bfbb"
TMP=""

fail(){ echo "BRAND_ASSET_STAGING=BLOCKED: $*" >&2; exit 2; }
cleanup(){
  code=$?
  trap - EXIT
  if [[ -n "$TMP" && "$TMP" == /root/lcam-approved-brand.* && -d "$TMP" && ! -L "$TMP" ]]; then
    rm -rf --one-file-system -- "$TMP"
  fi
  exit "$code"
}
trap cleanup EXIT

echo "========== VERIFY OPERATOR AND EXACT APPROVED DESIGN =========="
[[ "$(id -u)" == 0 ]] || fail 'root required'
[[ "$MODE" == check || "$MODE" == publish ]] || fail 'mode is check or publish'
[[ ( "$ARCHIVE" == /root/* || "$ARCHIVE" == /www/backup/* ) && -f "$ARCHIVE" && ! -L "$ARCHIVE" ]] ||
  fail 'upload ZIP to /root or private /www/backup, without symlinks'
for cmd in python3 sha256sum git gh mktemp; do command -v "$cmd" >/dev/null || fail "missing $cmd"; done
printf '%s  %s\n' "$ZIP_SHA" "$ARCHIVE" | sha256sum -c --status ||
  fail 'ZIP SHA mismatch; wrong or modified logo package'
[[ "$(gh api "repos/$REPO/branches/main" --jq '.commit.sha')" == "$MAIN" ]] ||
  fail 'Main advanced; repin before asset publish'
echo "APPROVED_PACKAGE_SHA256=PASS"

python3 - "$ARCHIVE" <<'PY'
from pathlib import PurePosixPath
import hashlib,json,struct,sys,zipfile
names={'SHA256SUMS.json','android-chrome-192x192.png','android-chrome-512x512.png',
       'app-icon-512.png','app-icon-source.png','apple-touch-icon.png',
       'favicon-128.png','favicon-16.png','favicon-192.png','favicon-256.png',
       'favicon-32.png','favicon-48.png','favicon-512.png','favicon-96.png',
       'favicon.ico','favicon.png','logo-dark.png','logo-light.png',
       'mark-source.png','maskable-192x192.png','maskable-512x512.png',
       'mstile-144x144.png','site.webmanifest'}
with zipfile.ZipFile(sys.argv[1]) as z:
    members=z.infolist()
    paths={m.filename for m in members}
    assert len(members)==len(names) and paths=={'brand/'+name for name in names}
    assert sum(m.file_size for m in members)<8_000_000
    for m in members:
        p=PurePosixPath(m.filename)
        assert len(p.parts)==2 and not m.is_dir()
        assert ((m.external_attr>>16)&0o170000)!=0o120000, 'symlinks disallowed'
    checks=json.loads(z.read('brand/SHA256SUMS.json'))
    assert set(checks)==names-{'SHA256SUMS.json'}
    for name in names-{'SHA256SUMS.json'}:
        raw=z.read('brand/'+name)
        assert hashlib.sha256(raw).hexdigest()==checks[name]['sha256']
        assert len(raw)==checks[name]['bytes']
        if name.endswith('.png'):
            assert raw[:8]==b'\x89PNG\r\n\x1a\n'
            w,h=struct.unpack('>II',raw[16:24])
            assert 0<w<=4000 and 0<h<=4000
        if name.endswith('.ico'):
            assert raw[:4]==b'\0\0\1\0'
    app=json.loads(z.read('brand/site.webmanifest'))
    assert app['name']=='LearnCambodia'
    assert {i.get('purpose') for i in app['icons']}=={'any','maskable'}
print('FAVICON_LIGHT_DARK_IOS_ANDROID_WINDOWS_PWA=VALID')
print('EXACT_BRAND_FILES=23')
PY

if [[ "$MODE" == check ]]; then
  echo 'BRAND_PACKAGE_CHECK=PASS'
  exit 0
fi
printf 'Type PUBLISH APPROVED LEARN CAMBODIA BRAND: ' > /dev/tty
IFS= read -r AGREED < /dev/tty
[[ "$AGREED" == 'PUBLISH APPROVED LEARN CAMBODIA BRAND' ]] ||
  fail 'operator confirmation missing'
unset AGREED

TMP="$(mktemp -d /root/lcam-approved-brand.XXXXXXXX)"
gh repo clone "$REPO" "$TMP/repo" -- -b "$BRANCH" --depth 1 >/dev/null ||
  fail 'cannot clone reviewed GitHub branch'
[[ ! -e "$TMP/repo/public/brand" ]] ||
  fail 'brand assets already exist on review branch'
python3 - "$ARCHIVE" "$TMP/repo/public" <<'PY'
from pathlib import Path
import sys,zipfile
archive, root = sys.argv[1:]
root=Path(root)
with zipfile.ZipFile(archive) as z:
    for item in z.namelist():
        p=root/item
        p.parent.mkdir(parents=True,exist_ok=True)
        p.write_bytes(z.read(item))
(root/'favicon.ico').write_bytes((root/'brand/favicon.ico').read_bytes())
(root/'apple-touch-icon.png').write_bytes((root/'brand/apple-touch-icon.png').read_bytes())
print('EXACT_APPROVED_STATIC_FILES=STAGED')
PY
git -C "$TMP/repo" add -- public/brand public/favicon.ico public/apple-touch-icon.png
[[ "$(git -C "$TMP/repo" diff --cached --name-only | wc -l)" -eq 25 ]] ||
  fail 'unexpected number of staged assets'
git -C "$TMP/repo" -c user.name='LearnCambodia Brand Ops' \
  -c user.email='ops@learncambodia.com' commit -qm \
  'assets(brand): approved device favicons and light/dark logos'
git -C "$TMP/repo" -c credential.helper='!gh auth git-credential' push --quiet origin \
  "HEAD:refs/heads/$BRANCH" ||
  fail 'GitHub push failed; no live app was changed'
echo "BRAND_GITHUB_HEAD=$(git -C "$TMP/repo" rev-parse HEAD)"
echo 'BRAND_ASSETS=UPLOADED_TO_REVIEW_BRANCH'
echo 'OTHER_SITES=UNCHANGED'
echo 'PRODUCTION=UNCHANGED'
echo 'NEXT=CI_ACCEPTANCE_AND_GUARDED_RELEASE'
