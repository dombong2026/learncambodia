#!/usr/bin/env bash
# Learn Cambodia Home Course Player — read-only aaPanel production preflight.
# No checkout, pull, migration, cache clear, restart, chmod, or deployment occurs.
# Run from aaPanel Terminal as a user allowed to read the site directory.
set -Eeuo pipefail

SITE="${1:-/www/wwwroot/learncambodia.com}"
cd "$SITE"
printf '\n=== Learn Cambodia: course hero deployment preflight (READ ONLY) ===\n'
printf 'Path: %s\n' "$PWD"
test -f artisan || { echo 'STOP: Not a Laravel project.' >&2; exit 1; }
test -d .git || { echo 'STOP: This is not a Git checkout. Compare deployed files to the PR manually.' >&2; exit 1; }

echo
echo '1) Working tree / branch / deployed commit'
git status --short
git branch --show-current
git rev-parse HEAD
git log -1 --format='%cI %s'
echo
echo '2) Git origin (hide any embedded credentials before sharing)'
git remote get-url origin | sed -E 's#(https?://)[^/@]+@#\1[REDACTED]@#'
echo
echo '3) Known homepage markers and local changes'
grep -n -E 'English courses and exam practice|Browse exams|hero-course-search|data-home-course-hero|data-home-featured-section' resources/views/home.blade.php || true
git diff --numstat -- resources/views/home.blade.php app/Support/Appearance.php app/Http/Controllers/CatalogController.php resources/views/workspace/admin/appearance-form.blade.php
echo
echo '4) Existing player and homepage controller'
grep -n -E 'home\(|hero_mode|hero_course_id|Hero Default|Course Player' app/Http/Controllers/CatalogController.php app/Support/Appearance.php resources/views/workspace/admin/appearance-form.blade.php 2>/dev/null || true
echo
echo '5) Frontend build availability'
for cmd in php composer node npm; do
  if command -v "$cmd" >/dev/null 2>&1; then
    printf '%-10s %s\n' "$cmd" "$(command -v "$cmd")"
  else
    printf '%-10s MISSING\n' "$cmd"
  fi
done
if test -f public/build/manifest.json; then
  echo 'Vite manifest: present'
else
  echo 'Vite manifest: MISSING'
fi
echo
echo '6) Disk headroom'
df -h .
echo
echo 'Preflight complete. NO FILES WERE MODIFIED.'
echo 'HOLD deployment if the working tree is dirty, the live hero is newer than GitHub main, or no rollback exists.'
echo 'Do not run git reset --hard, git clean -fd, or an automatic pull in production.'
