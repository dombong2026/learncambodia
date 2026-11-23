#!/usr/bin/env bash
# Learn Cambodia Course Player — reproducible, SHA-checked aaPanel release.
# Modes: verify (READ-ONLY production), apply (mutates ONLY the 11 listed files).
# Do not invoke apply until code reconciliation, QA and production backup policy are accepted.
set -Eeuo pipefail
set +x
umask 077
if (( $# != 2 )); then echo 'Usage: script verify|apply <40-char-release-commit>' >&2; exit 2; fi
MODE="$1"
REF="$2"
[[ "$MODE" == 'verify' || "$MODE" == 'apply' ]] || { echo 'Mode must be verify or apply' >&2; exit 2; }
[[ "$REF" =~ ^[a-f0-9]{40}$ ]] || { echo 'Pinned commit SHA required' >&2; exit 2; }
SITE=/www/wwwroot/learncambodia.com
REPO=dombong2026/learncambodia
SOURCE_PREFIX=support/operator/staged-20261123
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
test -f "$SITE/artisan"
test -d "$SITE/resources/views"
command -v gh >/dev/null
command -v git >/dev/null
command -v php >/dev/null
test "$(gh api user --jq '.login')" = 'dombong2026'
test -f "$SITE/app/Support/EditorHtml.php"
grep -q 'function courseText' "$SITE/app/Support/EditorHtml.php"
test -f "$SITE/app/Models/Course.php"
test -f "$SITE/app/Models/CourseLesson.php"
test -f "$SITE/public/css/home.css"

STAGE="$(mktemp -d /tmp/lc-course-hero-release.XXXXXXXX)"
BACKUP=''
APPLY_STARTED=0
MANIFEST=$(cat <<'RELEASE_FILES'
36627dc3aca25ebb127a9c330eac7bdb976b4632 fb7a5c90ca5cbf0d671e9b80c848feeb3b3f765c resources/views/home.blade.php
5195015d217e65e3e1386857658123eddbb6d399 affb5438c0c86702012b6ac2f87583e8176bb615 app/Http/Controllers/CatalogController.php
5808f5e528e709c1e71a1dc4e068bfc4633214f3 b136ea53974c4756aabf080a757e88053e594441 resources/views/workspace/admin/appearance-form.blade.php
c5e2acf374721b39f2af5b8c1538413fd3094f2f c079adb3203b7cfac3baed5a38244f4a61a946c5 app/Support/Appearance.php
d2aef2a812bdf41baab7f8c452192e4636ff709e 06fdc3d68347f1e90e8cacd75fd3523200b5d018 app/Http/Controllers/Workspace/AdminAppearanceController.php
- ce6e6f75bb1cf76d95d522243b29f58571e6ea2d resources/views/partials/home-course-player-hero.blade.php
- 6e6e53d7cd260fbf9367e743a11c8fc9a5137f8d public/css/home-course-hero.css
- 4ce14781defede2ecc95316f31c409a426452e83 public/js/home-course-hero.js
- eb735483d0583eba1bf8040ddc77caf40f832663 lang/en/home_hero.php
- c83703b4211f4062ea11a46e0b691d75bdba6a32 lang/km/home_hero.php
- 74d0b738463fa88321250452dfa3ea53f294cba5 lang/zh/home_hero.php
RELEASE_FILES
)
rollback() {
  (( APPLY_STARTED == 1 )) || return 0
  echo 'ERROR: installation interrupted; attempting file rollback' >&2
  while read -r old new path; do
    [[ -n "$path" ]] || continue
    if [[ "$old" == '-' ]]; then
      if [[ -f "$SITE/$path" && "$(git hash-object -- "$SITE/$path")" == "$new" ]]; then
        rm -f -- "$SITE/$path"
      fi
    elif [[ -f "$BACKUP/$path" ]]; then
      cp -p -- "$BACKUP/$path" "$SITE/$path"
    fi
  done <<< "$MANIFEST"
  (cd "$SITE" && php artisan view:clear) || true
  echo "Rollback files: $BACKUP" >&2
}
on_exit() {
  status=$?
  if (( status != 0 )); then rollback || true; fi
  rm -rf -- "$STAGE"
  exit "$status"
}
trap on_exit EXIT

echo '=== SHA-checked Course Player release preflight ==='
echo "Mode: $MODE"
echo "Release commit: $REF"
echo "Site: $SITE"
echo
while read -r old new path; do
  [[ -n "$path" ]] || continue
  if [[ -L "$SITE/$path" ]]; then echo "STOP: symlink not allowed: $path" >&2; exit 1; fi
  if [[ "$old" == '-' ]]; then
    if [[ -e "$SITE/$path" ]]; then
      echo "STOP: new asset target already exists: $path" >&2
      exit 1
    fi
  else
    [[ -f "$SITE/$path" ]] || { echo "STOP: missing existing $path" >&2; exit 1; }
    current="$(git hash-object -- "$SITE/$path")"
    if [[ "$current" != "$old" ]]; then
      echo "STOP: production diverged at $path (no overwrite)" >&2
      echo "Expected source hash: $old" >&2
      echo "Actual source hash:   $current" >&2
      exit 1
    fi
  fi
  mkdir -p -- "$STAGE/$(dirname "$path")"
  gh api -H 'Accept: application/vnd.github.raw+json' \
    "repos/$REPO/contents/$SOURCE_PREFIX/$path?ref=$REF" > "$STAGE/$path"
  staged_hash="$(git hash-object -- "$STAGE/$path")"
  if [[ "$staged_hash" != "$new" ]]; then
    echo "STOP: staged hash mismatch: $path" >&2
    exit 1
  fi
  echo "VERIFIED: $path"
done <<< "$MANIFEST"

for path in \
  app/Support/Appearance.php \
  app/Http/Controllers/CatalogController.php \
  app/Http/Controllers/Workspace/AdminAppearanceController.php \
  lang/en/home_hero.php lang/km/home_hero.php lang/zh/home_hero.php
do
  php -l "$STAGE/$path" >/dev/null
done
echo 'PHP lint: passed'
if [[ "$MODE" == 'verify' ]]; then
  echo 'PREVIEW VERIFIED. Production has not been modified.'
  exit 0
fi

BACKUP="/root/learncambodia-course-hero-backups/$(date -u +%Y%m%dT%H%M%SZ)-$REF"
mkdir -p -- "$BACKUP"
chmod 700 -- "$BACKUP"
while read -r old new path; do
  [[ -n "$path" ]] || continue
  [[ "$old" == '-' ]] && continue
  mkdir -p -- "$BACKUP/$(dirname "$path")"
  cp -p -- "$SITE/$path" "$BACKUP/$path"
done <<< "$MANIFEST"
printf '%s\n' "$MANIFEST" > "$BACKUP/manifest.txt"
echo "Backup stored at: $BACKUP"

# Verify no source file changed since the read-only preflight.
while read -r old new path; do
  [[ -n "$path" ]] || continue
  if [[ "$old" != '-' && "$(git hash-object -- "$SITE/$path")" != "$old" ]]; then
    echo "STOP: race detected at $path; nothing installed" >&2
    exit 1
  fi
done <<< "$MANIFEST"

APPLY_STARTED=1
while read -r old new path; do
  [[ -n "$path" ]] || continue
  target="$SITE/$path"
  mkdir -p -- "$(dirname "$target")"
  temp="$(mktemp "$target.XXXXXXXX")"
  cp -- "$STAGE/$path" "$temp"
  if [[ "$old" == '-' ]]; then
    chmod 644 -- "$temp"
    chown --reference="$SITE/resources/views/home.blade.php" "$temp"
  else
    chmod --reference="$BACKUP/$path" "$temp"
    chown --reference="$BACKUP/$path" "$temp"
  fi
  mv -f -- "$temp" "$target"
  if [[ "$(git hash-object -- "$target")" != "$new" ]]; then
    echo "ERROR: installed hash mismatch at $path" >&2
    exit 1
  fi
  echo "INSTALLED: $path"
done <<< "$MANIFEST"

(cd "$SITE" && php artisan view:clear >/dev/null && php artisan view:cache >/dev/null)
APPLY_STARTED=0
echo "INSTALLATION FINISHED: $REF"
echo "Backup: $BACKUP"
echo 'Course Player must still be selected through Home Appearance and independently approved.'
echo 'Perform browser QA (desktop, tablet, phone; EN/KM/ZH) before activating the new hero.'
