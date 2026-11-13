#!/usr/bin/env bash
# Exact-commit source staging only. No install, database use, or service changes.
set -Eeuo pipefail
set +x
umask 077

REPO="dombong2026/learncambodia"
BRANCH="qa/mysql-aapanel-browser-security-acceptance"
QA="/www/wwwroot/learncambodia-qa-20261113"
EXPECTED_SHA="${1:-}"

fail() { printf 'QA_SOURCE_STAGING=BLOCKED: %s\n' "$*" >&2; exit 2; }
[[ "$(id -u)" -eq 0 ]] || fail "root operator required for isolated source staging"
[[ "$EXPECTED_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "expected full 40-character Git SHA argument"
[[ -d /www/wwwroot && ! -L /www/wwwroot ]] || fail "aaPanel wwwroot is absent or a symlink"
[[ -d /var/lib && ! -L /var/lib ]] || fail "private temporary source area unavailable"
[[ ! -e "$QA" && ! -L "$QA" ]] || fail "QA destination already exists; never overwrite"
for bin in git gh mktemp mv stat; do command -v "$bin" >/dev/null || fail "missing utility $bin"; done
gh auth status >/dev/null 2>&1 || fail "GitHub CLI account not authenticated"

TMP="$(mktemp -d /var/lib/learncambodia-source.XXXXXXXX)"
chmod 700 "$TMP"
printf 'TEMP_SOURCE=%s\n' "$TMP"
printf 'RELEASE_SOURCE_REF=%s\n' "$EXPECTED_SHA"

# Failure leaves a root-private temporary checkout for operator inspection.
gh repo clone "$REPO" "$TMP/source" -- --depth=1 --single-branch --branch "$BRANCH" \
  || fail "source clone failed; inspect private TEMP_SOURCE"
[[ -d "$TMP/source/.git" && ! -L "$TMP/source" ]] || fail "checkout is not a normal Git worktree"
ACTUAL_SHA="$(git -C "$TMP/source" rev-parse HEAD)"
[[ "$ACTUAL_SHA" == "$EXPECTED_SHA" ]] || fail "branch head changed; refuse unverified checkout"
[[ -z "$(git -C "$TMP/source" status --porcelain)" ]] || fail "checkout is not clean"
for required in artisan composer.json package.json .env.example \
  config/installer.php .github/workflows/release-acceptance.yml; do
  [[ -f "$TMP/source/$required" && ! -L "$TMP/source/$required" ]] \
    || fail "required source file missing or symlink: $required"
done
[[ ! -e "$TMP/source/.env" ]] || fail "checkout contains runtime environment"
[[ ! -d "$TMP/source/vendor" && ! -d "$TMP/source/node_modules" ]] || fail "unexpected installed dependencies"
[[ ! -e "$QA" && ! -L "$QA" ]] || fail "QA destination appeared during source verification"

chmod 700 "$TMP/source"
mv -- "$TMP/source" "$QA" || fail "failed to stage verified source"
rmdir -- "$TMP"
printf 'QA_SOURCE_STAGING=PASS\nQA_APP=%s\nQA_COMMIT=%s\n' "$QA" "$ACTUAL_SHA"
echo "QA_ENV=NOT_CREATED"
echo "QA_DATABASE=NOT_CREATED"
echo "NGINX_VHOST=NOT_CREATED"
echo "INSTALLER=DISABLED_NOT_INVOKED"
echo "DEPENDENCIES=NOT_INSTALLED"
if [[ -f "$QA/composer.lock" ]]; then echo "COMPOSER_LOCK=PRESENT"; else echo "COMPOSER_LOCK=MISSING_RELEASE_BLOCKER"; fi
if [[ -f "$QA/package-lock.json" ]]; then echo "NPM_LOCK=PRESENT"; else echo "NPM_LOCK=MISSING_RELEASE_BLOCKER"; fi
echo "PRODUCTION=UNCHANGED"
