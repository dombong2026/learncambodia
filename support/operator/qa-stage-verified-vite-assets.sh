#!/usr/bin/env bash
# Stage a GitHub-CI-produced pinned Vite build into private Learn Cambodia QA source.
# Does not run Composer/npm, create .env/DB/vhost, or activate installer.
set -Eeuo pipefail
set +x
umask 077

REPO='dombong2026/learncambodia'
BRANCH='qa/mysql-aapanel-browser-security-acceptance'
APP='/www/wwwroot/learncambodia-qa-20261113'
OLD='d7d085ab1b0d1154c2b0d9123b8b6a828d42efc7'
EXPECTED='4acf556e468a54487a263596594c4a4d93942143'
RUN='43414508808'
ARTIFACT='learncambodia-pinned-qa-vite-assets'
ARTIFACT_ID='14031545804'
ASSET_SHA='fbee0d6b574a0d08a5614668e2def355268068f06d3997f6c3c0ceb80e041a9c'
PHP_LOCK_SHA='043948df6390f037947781244dcf2f39aa678a37fbdfc9879f0640ecd313b260'
NPM_LOCK_SHA='f4e670ae3cd90e0e6ab27be86adf5d09558bd5ccc904f784f7d595b13d7b52c7'

fail() { printf 'QA_ASSET_STAGING=BLOCKED: %s\n' "$*" >&2; exit 2; }
[[ "$(id -u)" == 0 ]] || fail 'root operator required for root-owned source'
[[ -d "$APP" && ! -L "$APP" ]] || fail 'dedicated QA source missing or symlink'
[[ -d /var/lib && ! -L /var/lib ]] || fail 'private temp area missing'
[[ -d "$APP/public" && ! -L "$APP/public" ]] || fail 'unexpected public directory'
[[ ! -e "$APP/.env" && ! -L "$APP/.env" ]] || fail 'runtime environment exists; manually review first'
[[ ! -e "$APP/vendor" && ! -e "$APP/node_modules" ]] || fail 'dependencies already installed; manually review first'
[[ ! -e "$APP/public/build" && ! -L "$APP/public/build" ]] || fail 'build directory already exists; refuse overwrite'
[[ ! -e "$APP/public/hot" && ! -L "$APP/public/hot" ]] || fail 'hot-file indicates development server'
[[ ! -e /www/server/panel/vhost/nginx/qa.learncambodia.com.conf ]] || fail 'unexpected QA vhost already exists'
for bin in git gh sha256sum python3 mktemp; do
  command -v "$bin" >/dev/null 2>&1 || fail "missing required command: $bin"
done

[[ "$(git -C "$APP" rev-parse --show-toplevel)" == "$APP" ]] || fail 'not isolated Git worktree'
[[ "$(git -C "$APP" branch --show-current)" == "$BRANCH" ]] || fail 'unexpected branch'
[[ -z "$(git -C "$APP" status --porcelain)" ]] || fail 'uncommitted QA source changes'
CURRENT="$(git -C "$APP" rev-parse HEAD)"
[[ "$CURRENT" == "$OLD" || "$CURRENT" == "$EXPECTED" ]] || fail 'unexpected local QA commit'
[[ "$(gh api "repos/$REPO/pulls/12" --jq '.draft')" == true ]] || fail 'PR #12 no longer draft'
[[ "$(gh api "repos/$REPO/pulls/12" --jq '.head.sha')" == "$EXPECTED" ]] || fail 'remote QA head mismatch'
[[ "$(gh api "repos/$REPO/actions/runs/$RUN" --jq '.head_sha')" == "$EXPECTED" ]] || fail 'asset run SHA mismatch'
[[ "$(gh api "repos/$REPO/actions/runs/$RUN" --jq '.conclusion')" == success ]] || fail 'asset workflow not successful'
[[ "$(gh api "repos/$REPO/actions/artifacts/$ARTIFACT_ID" --jq '.name')" == "$ARTIFACT" ]] || fail 'artifact identity mismatch'
[[ "$(gh api "repos/$REPO/actions/artifacts/$ARTIFACT_ID" --jq '.expired')" == false ]] || fail 'artifact expired'

echo '========== VERIFY PINNED QA SOURCE =========='
if [[ "$CURRENT" == "$OLD" ]]; then
  git -C "$APP" fetch --no-tags origin "$BRANCH"
  [[ "$(git -C "$APP" rev-parse FETCH_HEAD)" == "$EXPECTED" ]] || fail 'fetched branch moved'
  [[ "$(git -C "$APP" diff --name-only "$OLD" "$EXPECTED")" == '.github/workflows/qa-vite-assets.yml' ]] || fail 'unexpected source changes'
  git -C "$APP" merge --ff-only FETCH_HEAD
fi
[[ "$(git -C "$APP" rev-parse HEAD)" == "$EXPECTED" ]] || fail 'checkout did not reach expected SHA'
[[ -z "$(git -C "$APP" status --porcelain)" ]] || fail 'source is not clean after update'
printf '%s  %s\n' "$PHP_LOCK_SHA" "$APP/composer.lock" | sha256sum --check --status || fail 'Composer lock changed'
printf '%s  %s\n' "$NPM_LOCK_SHA" "$APP/package-lock.json" | sha256sum --check --status || fail 'npm lock changed'
echo 'QA_SOURCE_AND_LOCKS=PASS'

TMP="$(mktemp -d /var/lib/learncambodia-vite.XXXXXXXX)"
cleanup() {
  if [[ "$TMP" == /var/lib/learncambodia-vite.* && -d "$TMP" && ! -L "$TMP" ]]; then
    rm -rf --one-file-system -- "$TMP"
  fi
}
trap cleanup EXIT
echo '========== DOWNLOAD PROVENANCE-PINNED QA ASSETS =========='
gh run download "$RUN" --repo "$REPO" --name "$ARTIFACT" --dir "$TMP"
[[ -f "$TMP/learncambodia-vite-build.tar.gz" && ! -L "$TMP/learncambodia-vite-build.tar.gz" ]] || fail 'missing archive'
[[ -f "$TMP/provenance.txt" && ! -L "$TMP/provenance.txt" ]] || fail 'missing provenance'
grep -Fxq "SOURCE_COMMIT=$EXPECTED" "$TMP/provenance.txt" || fail 'incorrect provenance source'
grep -Fxq "COMPOSER_LOCK_SHA256=$PHP_LOCK_SHA" "$TMP/provenance.txt" || fail 'incorrect composer provenance'
grep -Fxq "NPM_LOCK_SHA256=$NPM_LOCK_SHA" "$TMP/provenance.txt" || fail 'incorrect npm provenance'
grep -Fxq "ASSET_ARCHIVE_SHA256=$ASSET_SHA" "$TMP/provenance.txt" || fail 'incorrect asset provenance'
printf '%s  %s\n' "$ASSET_SHA" "$TMP/learncambodia-vite-build.tar.gz" | sha256sum --check --status || fail 'archive SHA mismatch'
echo 'QA_ASSET_ARCHIVE_HASH=PASS'

python3 - "$TMP/learncambodia-vite-build.tar.gz" "$TMP" <<'PY'
import json, pathlib, shutil, sys, tarfile
archive=pathlib.Path(sys.argv[1])
target=pathlib.Path(sys.argv[2])/'unpack'
target.mkdir(mode=0o700)
with tarfile.open(archive,'r:gz') as tf:
    members=tf.getmembers()
    assert 1 < len(members) < 1000, 'unexpected tar member count'
    total=sum(m.size for m in members)
    assert total < 50000000, 'archive larger than QA staging limits'
    for member in members:
        name=pathlib.PurePosixPath(member.name)
        assert not name.is_absolute() and '..' not in name.parts, 'unsafe path'
        assert name.parts and name.parts[0]=='build', 'non-build member'
        assert member.isfile() or member.isdir(), 'links and special files prohibited'
        location=target.joinpath(*name.parts)
        if member.isdir():
            location.mkdir(parents=True,exist_ok=True,mode=0o755)
        else:
            location.parent.mkdir(parents=True,exist_ok=True)
            data=tf.extractfile(member)
            assert data is not None
            with location.open('xb') as dst:
                shutil.copyfileobj(data,dst)
            location.chmod(0o644)
root=target/'build'
manifest=root/'manifest.json'
parsed=json.loads(manifest.read_text(encoding='utf-8'))
assert isinstance(parsed,dict) and parsed, 'manifest is empty'
for entry in parsed.values():
    for rel in [entry.get('file')]+entry.get('css',[])+entry.get('assets',[]):
        if rel is None: continue
        assert isinstance(rel,str) and rel, 'invalid asset path'
        p=(root/rel).resolve()
        assert p.is_relative_to(root.resolve()) and p.is_file(), 'manifest target not present'
print('QA_VITE_ARCHIVE_CONTENT=PASS')
PY

[[ ! -e "$APP/public/build" && ! -L "$APP/public/build" ]] || fail 'public/build appeared unexpectedly'
mv -- "$TMP/unpack/build" "$APP/public/build"
[[ -f "$APP/public/build/manifest.json" ]] || fail 'staged manifest missing'
echo 'QA_PRIVATE_VITE_ASSETS=STAGED'
echo "QA_COMMIT=$EXPECTED"
echo 'QA_ENV=NOT_CREATED'
echo 'QA_DATABASE=NOT_CREATED'
echo 'QA_VHOST=NOT_CREATED'
echo 'INSTALLER=NOT_ENABLED'
echo 'PRODUCTION=UNCHANGED'
echo 'NEXT_GATE=QA_DNS_TLS_ISOLATED_DB_AND_PERMISSION_PLAN'
