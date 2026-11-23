#!/usr/bin/env bash
# Learn Cambodia Home Course Player — read-only aaPanel production preflight.
# Supports both Git worktrees and artifact/copy deployments without .git.
# Does not modify files, pull, run migrations, restart services, or clear caches.
set -Eeuo pipefail

SITE="${1:-/www/wwwroot/learncambodia.com}"
if [[ ! -d "$SITE" ]]; then
  echo "STOP: Site directory missing: $SITE" >&2
  exit 1
fi
cd "$SITE"
printf '\n=== Learn Cambodia: Course Player deployment preflight (READ ONLY) ===\n'
printf 'Site directory: %s\n' "$PWD"
printf 'Resolved directory: %s\n' "$(readlink -f .)"
if [[ ! -f artisan || ! -d resources/views ]]; then
  echo 'STOP: Expected Laravel files not found in this directory.' >&2
  exit 1
fi

printf '\n1) Deployment type\n'
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo 'Mode: Git checkout'
  printf 'Branch: '; git branch --show-current
  printf 'Commit: '; git rev-parse HEAD
  echo 'Changed paths (if any):'
  git status --short
  echo 'Recent commit:'
  git log -1 --format='%cI %s'
  echo 'Homepage file changes:'
  git diff --numstat -- resources/views/home.blade.php app/Support/Appearance.php app/Http/Controllers/CatalogController.php resources/views/workspace/admin/appearance-form.blade.php || true
else
  echo 'Mode: Deployed copy / release without Git metadata'
  echo 'Git commit cannot be read from this directory.'
  echo 'Do NOT initialize Git in production or pull directly into the live site.'
fi

printf '\n2) Homepage template candidates\n'
find resources/views -maxdepth 3 -type f \( -iname '*home*.blade.php' -o -iname '*landing*.blade.php' \) -print | head -40 || true

printf '\n3) Current homepage and appearance signatures (paths and line numbers)\n'
for file in \
  resources/views/home.blade.php \
  resources/views/pages/home.blade.php \
  resources/views/public/home.blade.php \
  resources/views/workspace/admin/appearance-form.blade.php \
  app/Support/Appearance.php \
  app/Http/Controllers/CatalogController.php
do
  if [[ -f "$file" ]]; then
    stat -c '%y %s bytes %n' "$file"
    grep -nEi 'hero_title|hero_intro|Browse exams|Browse courses|hero-course-search|data-home-course-hero|hero_mode|hero_course_id|Course Player' "$file" | head -24 || true
  fi
done

printf '\n4) Exact files containing relevant hero references\n'
grep -RilE --include='*.php' --include='*.blade.php' \
  'English courses and exam practice|hero_mode|hero_course_id|data-home-course-hero|Course Player' \
  resources/views app/Support app/Http/Controllers 2>/dev/null | head -60 || true

printf '\n5) Build/runtime availability\n'
for cmd in php composer node npm; do
  if command -v "$cmd" >/dev/null 2>&1; then
    printf '%-10s %s\n' "$cmd" "$(command -v "$cmd")"
  else
    printf '%-10s MISSING\n' "$cmd"
  fi
done
if [[ -f public/build/manifest.json ]]; then
  stat -c 'Vite manifest: present, %y, %s bytes' public/build/manifest.json
else
  echo 'Vite manifest: MISSING'
fi
[[ ! -f public/index.php ]] || stat -c 'Public entrypoint: %y %n' public/index.php

printf '\n6) Free disk space\n'
df -h .
echo
echo 'Preflight complete — NO FILES MODIFIED.'
echo 'HOLD deployment until deployed files have been compared to the PR and rollback has been verified.'
echo 'Never run git reset --hard, git clean -fd, or overwrite the live tree blindly.'
