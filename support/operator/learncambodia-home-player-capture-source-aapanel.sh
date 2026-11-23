#!/usr/bin/env bash
# Learn Cambodia — capture ONLY reviewed public UI source for safe homepage reconciliation.
# READ-ONLY on aaPanel production; writes source snapshots ONLY to a GitHub feature branch.
set -Eeuo pipefail
set +x
umask 077

SITE=/www/wwwroot/learncambodia.com
REPO=dombong2026/learncambodia
BRANCH=feature/home-course-player-hero-20261123
PREFIX=support/operator/live-20261123
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh

test -d "$SITE"
command -v gh >/dev/null || { echo "ERROR: gh is unavailable"; exit 1; }
command -v git >/dev/null || { echo "ERROR: git hash-object is unavailable"; exit 1; }
LOGIN="$(gh api user --jq '.login')"
if [[ "$LOGIN" != "dombong2026" ]]; then
  echo "ERROR: authenticated GitHub user is not dombong2026; refusing to upload."
  exit 1
fi

echo "=== Read-only live-source capture: validation ==="
for file in \
  public/css/home.css \
  resources/views/components/home-section.blade.php \
  resources/views/promo-player/placement.blade.php \
  app/Support/Appearance.php \
  app/Http/Controllers/Workspace/AdminAppearanceController.php \
  resources/views/layouts/public.blade.php
do
  if [[ ! -f "$SITE/$file" || ! -r "$SITE/$file" ]]; then
    echo "ERROR: required source unavailable: $file"
    exit 1
  fi
  if [[ -L "$SITE/$file" ]]; then
    echo "ERROR: unexpected symlink: $file"
    exit 1
  fi
  printf '%s bytes : %s\n' "$(wc -c < "$SITE/$file")" "$file"
done
printf '\nGitHub target: %s @ %s\n' "$REPO" "$BRANCH"
echo "Creating whitelisted snapshots in GitHub only. Existing files cannot be overwritten."

for file in \
  public/css/home.css \
  resources/views/components/home-section.blade.php \
  resources/views/promo-player/placement.blade.php \
  app/Support/Appearance.php \
  app/Http/Controllers/Workspace/AdminAppearanceController.php \
  resources/views/layouts/public.blade.php
do
  target="$PREFIX/$file"
  # Create-only: deliberately omit the SHA field. GitHub rejects an overwrite
  # without the existing blob SHA, so this cannot replace an existing file.
  payload="$(base64 -w 0 < "$SITE/$file")"
  expected_bytes="$(wc -c < "$SITE/$file")"
  expected_blob="$(git hash-object -- "$SITE/$file")"
  echo "CREATE-ONLY: $target"
  created_info="$(gh api --method PUT "repos/$REPO/contents/$target" \
    -f message="chore(ops): capture current live UI dependency $file" \
    -f branch="$BRANCH" \
    -f content="$payload" \
    --jq '(.content.path // "") + "|" + (.content.sha // "") + "|" + ((.content.size // -1) | tostring)')"
  unset payload
  if [[ "$created_info" != "$target|$expected_blob|$expected_bytes" ]]; then
    echo "ERROR: GitHub create returned unexpected file metadata for $target" >&2
    echo "STOP: check the GitHub PR before running any other command." >&2
    exit 1
  fi
  echo "CAPTURED: $target ($expected_bytes bytes; blob verified)"
done

echo "=== Complete. Production files were NOT modified. ==="
echo "Review snapshots in PR #118 before preparing any deployment patch."
