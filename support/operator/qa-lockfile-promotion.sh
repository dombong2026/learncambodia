#!/usr/bin/env bash
# Learn Cambodia: commit only previously checked, successful CI lockfiles to the QA branch.
# No application installation, migrations, database changes or production configuration.
set -Eeuo pipefail
set +x
umask 077

REPO='dombong2026/learncambodia'
BRANCH='qa/mysql-aapanel-browser-security-acceptance'
BASE='3e4a43b84d2360a8253460b3d5aaf6672d954b5'
QA='/www/wwwroot/learncambodia-qa-20261113'
RUN='43405041249'
ARTIFACT_ID='14026513358'
ARTIFACT='learncambodia-resolved-dependency-locks'
PHP_LOCK_SHA256='043948df6390f037947781244dcf2f39aa678a37fbdfc9879f0640ecd313b260'
NPM_LOCK_SHA256='f4e670ae3cd90e0e6ab27be86adf5d09558bd5ccc904f784f7d595b13d7b52c7'

fail() { echo "LOCKFILE_PROMOTION=BLOCKED: $*" >&2; exit 2; }

[[ "$(id -u)" -eq 0 ]] || fail 'root operator required for existing root-owned QA source'
[[ -d /var/lib && ! -L /var/lib ]] || fail 'private working root unavailable'
[[ -d /www/wwwroot && ! -L /www/wwwroot ]] || fail 'unsafe wwwroot'
[[ -d "$QA" && ! -L "$QA" && "$(realpath -e -- "$QA")" == "$QA" ]] || fail 'unexpected QA source path'
[[ ! -e "$QA/.env" && ! -L "$QA/.env" ]] || fail 'environment exists: stop and review isolation'
[[ ! -e "$QA/vendor" && ! -e "$QA/node_modules" ]] || fail 'dependencies already installed: stop and review isolation'
[[ ! -e "$QA/composer.lock" && ! -e "$QA/package-lock.json" ]] || fail 'lockfile path already exists'
for binary in gh git python3 composer sha256sum mktemp; do
  command -v "$binary" >/dev/null 2>&1 || fail "missing command: $binary"
done
[[ "$(git -C "$QA" rev-parse --show-toplevel)" == "$QA" ]] || fail 'QA is not an independent checkout'
[[ "$(git -C "$QA" branch --show-current)" == "$BRANCH" ]] || fail 'wrong checkout branch'
[[ "$(git -C "$QA" rev-parse HEAD)" == "$BASE" ]] || fail 'QA checkout commit changed'
[[ -z "$(git -C "$QA" status --porcelain)" ]] || fail 'QA checkout is not clean'

remote="$(git -C "$QA" remote get-url origin)"
case "$remote" in
  'https://github.com/dombong2026/learncambodia.git'|'git@github.com:dombong2026/learncambodia.git') ;;
  *) fail 'unexpected git remote; refusing push' ;;
esac

[[ "$(gh api "repos/$REPO/pulls/12" --jq '.draft')" == true ]] || fail 'PR is no longer draft'
[[ "$(gh api "repos/$REPO/pulls/12" --jq '.head.sha')" == "$BASE" ]] || fail 'PR head changed'
[[ "$(gh api "repos/$REPO/actions/runs/$RUN" --jq '.head_sha')" == "$BASE" ]] || fail 'artifact run belongs to a different commit'
[[ "$(gh api "repos/$REPO/actions/runs/$RUN" --jq '.conclusion')" == success ]] || fail 'artifact workflow is not successful'
[[ "$(gh api "repos/$REPO/actions/artifacts/$ARTIFACT_ID" --jq '.name')" == "$ARTIFACT" ]] || fail 'unexpected artifact name'
[[ "$(gh api "repos/$REPO/actions/artifacts/$ARTIFACT_ID" --jq '.expired')" == false ]] || fail 'artifact expired'

TMP="$(mktemp -d /var/lib/learncambodia-lock-review.XXXXXXXX)"
cleanup() {
  rm -f -- "$TMP/composer.json" "$TMP/package.json" "$TMP/composer.lock" "$TMP/package-lock.json"
  rmdir -- "$TMP" 2>/dev/null || true
}
trap cleanup EXIT

echo '========== DOWNLOAD PINNED SUCCESSFUL CI ARTIFACT =========='
gh run download "$RUN" --repo "$REPO" --name "$ARTIFACT" --dir "$TMP"
[[ -f "$TMP/composer.lock" && -f "$TMP/package-lock.json" ]] || fail 'expected artifact members missing'
[[ ! -L "$TMP/composer.lock" && ! -L "$TMP/package-lock.json" ]] || fail 'artifact member is symlink'
printf '%s  %s\n' "$PHP_LOCK_SHA256" "$TMP/composer.lock" | sha256sum --check --status || fail 'PHP lock hash mismatch'
printf '%s  %s\n' "$NPM_LOCK_SHA256" "$TMP/package-lock.json" | sha256sum --check --status || fail 'npm lock hash mismatch'
echo 'LOCKFILE_HASHES=PASS'

cp -- "$QA/composer.json" "$QA/package.json" "$TMP/"
python3 - "$TMP" <<'PY'
import json, pathlib, sys
p = pathlib.Path(sys.argv[1])
cj = json.loads((p/'composer.json').read_text())
cl = json.loads((p/'composer.lock').read_text())
nj = json.loads((p/'package.json').read_text())
nl = json.loads((p/'package-lock.json').read_text())
assert cl.get('content-hash') == 'bdb44d6e808e354492af6bb0aa837a03', 'unexpected Composer content hash'
assert cl.get('platform', {}).get('php') == cj['require']['php'], 'PHP platform differs'
for key, section in [('require','packages'), ('require-dev','packages-dev')]:
    names = {x['name'] for x in cl[section]}
    assert all(x == 'php' or x in names for x in cj[key]), 'missing root Composer requirement'
assert len(cl['packages']) == 78 and len(cl['packages-dev']) == 33, 'Composer package count changed'
allowed = {'MIT', 'BSD-3-Clause', 'Apache-2.0'}
assert all(set(x.get('license', [])) & allowed for x in cl['packages'] + cl['packages-dev']), 'license requires manual review'
assert nl['lockfileVersion'] == 3, 'unexpected npm lock version'
assert len(nl['packages']) == 84, 'npm dependency count changed'
assert nl['packages']['']['devDependencies'] == nj['devDependencies'], 'npm root dependency drift'
for name, entry in nl['packages'].items():
    if name and entry.get('resolved', '').startswith('https://registry.npmjs.org/'):
        assert entry.get('integrity', '').startswith(('sha512-', 'sha1-')), f'missing npm integrity: {name}'
print('JSON_AND_DECLARED_DEPENDENCIES=PASS')
print('LICENSE_METADATA_REVIEW=PASS_NOT_INDEPENDENT_LEGAL_APPROVAL')
PY

echo '========== COMPOSER METADATA VALIDATION (NO INSTALL) =========='
(cd "$TMP" && COMPOSER_ALLOW_SUPERUSER=0 composer --no-plugins validate --no-check-publish --no-interaction) \
  || fail 'Composer metadata validation failed; QA checkout not modified'
echo 'COMPOSER_VALIDATE=PASS'

echo '========== COMMIT LOCKFILES ONLY TO DRAFT QA BRANCH =========='
cp -- "$TMP/composer.lock" "$TMP/package-lock.json" "$QA/"
chmod 644 -- "$QA/composer.lock" "$QA/package-lock.json"
git -C "$QA" add -- composer.lock package-lock.json
[[ "$(git -C "$QA" diff --cached --name-only | sort)" == $'composer.lock\npackage-lock.json' ]] \
  || fail 'staged changes beyond two lockfiles; do not push'
ACTOR="$(gh api user --jq '.login')"
[[ -n "$ACTOR" ]] || fail 'GitHub author unavailable'
git -C "$QA" -c "user.name=$ACTOR" -c "user.email=$ACTOR@users.noreply.github.com" \
  commit -m 'build(qa): pin reviewed CI Composer and npm dependency locks'
NEW_SHA="$(git -C "$QA" rev-parse HEAD)"
[[ "$(gh api "repos/$REPO/pulls/12" --jq '.head.sha')" == "$BASE" ]] || fail 'remote PR branch changed; local QA commit retained, push refused'
git -C "$QA" push origin "HEAD:refs/heads/$BRANCH" || fail 'push unsuccessful; local QA commit retained'
echo 'QA_LOCKFILES_PUSH=PASS'
echo "QA_NEW_COMMIT=$NEW_SHA"
echo 'PRODUCTION=UNCHANGED'
echo 'INSTALLER=NOT_ENABLED'
echo 'NEXT_GATE=PINNED_CI_REVIEW'
