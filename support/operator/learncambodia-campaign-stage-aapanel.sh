#!/usr/bin/env bash
# Stage and diagnose the campaign release without changing the live website.
# Usage: bash support/operator/learncambodia-campaign-stage-aapanel.sh <40-char reviewed commit>
set -Eeuo pipefail
umask 077

repo='dombong2026/learncambodia'
release="${1:?Supply reviewed 40-character commit SHA}"
[[ "$release" =~ ^[a-f0-9]{40}$ ]] || { echo 'INVALID_COMMIT_SHA'; exit 2; }
test "$(id -u)" -eq 0 || { echo 'ROOT_REQUIRED'; exit 2; }
command -v gh >/dev/null
command -v php >/dev/null
command -v python3 >/dev/null
: "${GH_CONFIG_DIR:=/root/learncambodia-github-auth/gh}"
export GH_CONFIG_DIR

live='/www/wwwroot/learncambodia.com'
test -f "$live/artisan" || { echo 'LIVE_APP_NOT_FOUND'; exit 2; }
test -d "$live/resources/views" || { echo 'LIVE_VIEWS_NOT_FOUND'; exit 2; }

mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/home-campaign-stage.lock
flock -n 9 || { echo 'ANOTHER_CAMPAIGN_STAGE_IN_PROGRESS'; exit 2; }

actual="$(gh api "repos/$repo/pulls/120" --jq '.head.sha')"
test "$actual" = "$release" || { echo "PR_HEAD_MOVED actual=$actual"; exit 2; }
stage="$(mktemp -d /var/backups/learncambodia/campaign-stage.XXXXXXXX)"
echo "CAMPAIGN_STAGE_DIR=$stage"
echo "RELEASE_SHA=$release"

new_paths=(
  app/Models/HomeCampaign.php
  app/Http/Controllers/Workspace/HomeCampaignController.php
  database/migrations/2026_11_23_000051_create_home_campaigns_table.php
  resources/views/workspace/admin/home-campaign-builder.blade.php
  resources/views/learning/home-campaign.blade.php
  resources/js/home-campaign-builder.ts
  tests/Feature/HomeCampaignBuilderTest.php
)
existing_paths=(
  routes/web.php
  app/Http/Controllers/CatalogController.php
  resources/views/home.blade.php
  resources/js/workspace.ts
  resources/views/workspace/admin/appearance-form.blade.php
)

download_path() {
  local path="$1"
  mkdir -p "$stage/source/$(dirname "$path")"
  gh api -H 'Accept: application/vnd.github.raw+json' \
    "repos/$repo/contents/$path?ref=$release" > "$stage/source/$path"
  test -s "$stage/source/$path"
}

for path in "${new_paths[@]}" "${existing_paths[@]}"; do
  download_path "$path"
done
for path in "${new_paths[@]}"; do
  if [[ "$path" == *.php ]]; then php -l "$stage/source/$path" >/dev/null; fi
done
echo 'REVIEWED_CAMPAIGN_FILES_STAGED=PASS'

# Inspect the LIVE server, but never overwrite /www/wwwroot in this stage.
(
  cd "$live"
  php artisan route:list --json --path=dashboard/appearance/home/campaign-builder \
    > "$stage/live-campaign-routes.json" 2> "$stage/route-list-errors.txt" || true
  if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    git rev-parse HEAD > "$stage/live-git-head.txt"
    git status --porcelain --untracked-files=no > "$stage/live-git-status.txt"
  fi
)

python3 - "$stage/live-campaign-routes.json" <<'PY'
import json, sys
from pathlib import Path
path=Path(sys.argv[1])
try:
    rows=json.loads(path.read_text())
except (json.JSONDecodeError, OSError):
    rows=[]
if not isinstance(rows,list):
    rows=[]
targets=[{
  'uri':str(r.get('uri','')),
  'action':str(r.get('action','')),
  'name':str(r.get('name',''))
} for r in rows if isinstance(r,dict) and 'campaign-builder' in str(r.get('uri',''))]
print('LIVE_CAMPAIGN_ROUTE_COUNT='+str(len(targets)))
for r in targets:
    print('LIVE_CAMPAIGN_ROUTE='+r['uri']+' '+r['action'])
PY

{
  echo "RELEASE_SHA=$release"
  date -u '+INSPECTED_UTC=%Y-%m-%dT%H:%M:%SZ'
  for path in "${new_paths[@]}" "${existing_paths[@]}"; do
    if test -e "$live/$path"; then
      printf 'EXISTS\t%s\t' "$path"
      sha256sum "$live/$path" | awk '{print $1}'
    else
      printf 'ABSENT\t%s\n' "$path"
    fi
  done
} > "$stage/live-files.txt"

echo 'CAMPAIGN_STAGE_REPORT_BEGIN'
cat "$stage/live-files.txt"
echo 'CAMPAIGN_STAGE_REPORT_END'
echo "STAGED_SOURCE=$stage/source"
echo 'LIVE_SITE_MUTATIONS=NONE'
echo 'PRODUCTION_STATUS=HOLD_RECONCILE_LIVE_CAMPAIGN_BUILDER'
echo 'Review live-campaign-routes.json and existing files against staged source before any production merge.'
