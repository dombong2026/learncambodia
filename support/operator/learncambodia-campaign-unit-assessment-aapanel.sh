#!/usr/bin/env bash
# Surgical Unit actions + rich assessment upgrade. Seven UI/player/controller files; no DB writes or migrations.
set -Eeuo pipefail
umask 077
RELEASE="$1"
[[ "$RELEASE" =~ ^[a-f0-9]{40}$ ]] || { echo INVALID_RELEASE_SHA >&2; exit 2; }
test "$(id -u)" -eq 0 || { echo ROOT_REQUIRED >&2; exit 2; }
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
REPO=dombong2026/learncambodia
BRANCH=feature/campaign-unit-actions-assessment-elements-20261124
BASE=e19c56d43eb69af6b383fa56b6e46a8bb74590e6
SITE=/www/wwwroot/learncambodia.com
PHP=/www/server/php/85/bin/php
MARKER="$SITE/storage/app/private/campaign-module-media-hotfix.txt"
FIX_MARKER="$SITE/storage/app/private/campaign-unit-assessment-release.txt"
for cmd in gh python3 cmp flock curl runuser; do
  command -v "$cmd" >/dev/null || { echo "MISSING_TOOL=$cmd" >&2; exit 2; }
done
test -x "$PHP" && test "$("$PHP" -r 'echo PHP_VERSION_ID;')" -ge 80401 || {
  echo UNSUPPORTED_PHP >&2; exit 2;
}
test -f "$SITE/artisan" && test -f "$MARKER" || { echo PREVIOUS_UNIT_RELEASE_MARKER_MISSING >&2; exit 2; }
grep -Fxq "SOURCE_COMMIT=$BASE" "$MARKER" || { echo PREVIOUS_UNIT_RELEASE_DRIFT >&2; exit 2; }
test ! -f "$FIX_MARKER" || { echo UNIT_ASSESSMENT_ALREADY_APPLIED; exit 0; }
FILES=(
  resources/views/workspace/admin/campaign-studio.blade.php
  public/js/campaign-studio-premium.js
  public/js/campaign-course-workspace.js
  public/css/campaign-course-workspace.css
  app/Http/Controllers/Workspace/HomepageCampaignManagerController.php
  public/js/homepage-hero-campaigns.js
  resources/views/partials/homepage-hero-campaigns.blade.php
)
EXPECTED=(
  resources/views/workspace/admin/campaign-course-workspace.blade.php
  public/js/campaign-studio-premium.js
  public/js/campaign-course-workspace.js
  public/css/campaign-course-workspace.css
  app/Http/Controllers/Workspace/HomepageCampaignManagerController.php
  public/js/homepage-hero-campaigns.js
  resources/views/partials/homepage-hero-campaigns.blade.php
)
for file in "${FILES[@]}"; do
  test -f "$SITE/$file" && test ! -L "$SITE/$file" || {
    echo "UNSAFE_LIVE_FILE=$file" >&2; exit 2;
  }
done
mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/campaign-unit-assessment.lock
flock -n 9 || { echo RELEASE_LOCK_BUSY >&2; exit 2; }
HEAD="$(gh api "repos/$REPO/git/ref/heads/$BRANCH" --jq '.object.sha')"
test "$HEAD" = "$RELEASE" || { echo "RELEASE_HEAD_CHANGED=$HEAD" >&2; exit 2; }
gh api "repos/$REPO/actions/runs?head_sha=$RELEASE&per_page=100" | python3 -c '
import json,sys
runs=json.load(sys.stdin).get("workflow_runs",[])
name="Campaign Unit Assessment acceptance"
if not any(r.get("name")==name and r.get("status")=="completed" and r.get("conclusion")=="success" for r in runs):
    raise SystemExit("UNIT_ASSESSMENT_CI_NOT_PASSED")
print("PINNED_UNIT_ASSESSMENT_CI=PASS")
'

STAGE="$(mktemp -d /var/backups/learncambodia/campaign-unit-assessment.XXXXXXXX)"
mkdir -p "$STAGE/expected" "$STAGE/backup" "$STAGE/new"
echo "BACKUP_DIR=$STAGE"
fetch(){
  gh api -H 'Accept: application/vnd.github.raw+json' \
    "repos/$REPO/contents/$1?ref=$2" > "$3"
  test -s "$3"
}
for i in "${!FILES[@]}"; do
  file="${FILES[$i]}"
  fetch "${EXPECTED[$i]}" "$BASE" "$STAGE/expected/$i"
  cmp -s "$STAGE/expected/$i" "$SITE/$file" || {
    echo "LIVE_FILE_DRIFT=$file — no changes made" >&2; exit 2;
  }
  cp -p "$SITE/$file" "$STAGE/backup/$i"
done
echo EXACT_LIVE_BASELINE=PASS
for i in "${!FILES[@]}"; do
  fetch "${EXPECTED[$i]}" "$RELEASE" "$STAGE/new/$i"
done
grep -Fq 'id="cs-campaign-title"' "$STAGE/new/0"
grep -Fq 'class="cs-card cs-course-campaign-metadata"' "$STAGE/new/0"
! grep -Fq '<header class="cs-course-header">' "$STAGE/new/0"
grep -Fq 'data-cs-type-hint' "$STAGE/new/2"
grep -Fq 'const editorActions' "$STAGE/new/1"
! grep -Fq 'Changes are saved to this campaign only.' "$STAGE/new/0"
! grep -Fq 'No additional approval is required.' "$STAGE/new/0"
! grep -Fq 'One title and type for the entire campaign.' "$STAGE/new/0"
! grep -Fq '<label class="cs-label">Lesson text editor</label>' "$STAGE/new/1"
grep -Fq 'id="cs-unit-dialog"' "$STAGE/new/0"
! grep -Fq 'id="cs-selected-module-title"' "$STAGE/new/0"
grep -Fq 'data-cs-lesson-media-preview' "$STAGE/new/1"
grep -Fq 'data-cs-unit-action="edit"' "$STAGE/new/2"
grep -Fq 'data-cs-question-media-preview' "$STAGE/new/2"
grep -Fq 'prompt_html' "$STAGE/new/1"
grep -Fq 'prompt_html' "$STAGE/new/4"
grep -Fq 'displayQuestionMedia' "$STAGE/new/5"
"$PHP" -l "$STAGE/new/4" >/dev/null
grep -Fq 'youtube-nocookie.com/embed/' "$STAGE/new/2"
grep -Fq 'html.dark #cs-builder.cs-course-workspace' "$STAGE/new/3"
echo RELEASE_SOURCE=PASS

cd "$SITE"
"$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows=\Illuminate\Support\Facades\DB::table("homepage_campaign_items")->get();
file_put_contents($argv[1],json_encode($rows,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
' "$STAGE/campaigns-before.json"
test -s "$STAGE/campaigns-before.json"
echo CAMPAIGN_RECORDS_SNAPSHOT=PASS
put(){
  local source="$1" dest="$2" tmp
  tmp="$(mktemp "$(dirname "$dest")/.course-ux-fix.XXXXXXXX")"
  cat "$source" > "$tmp"
  chown --reference="$dest" "$tmp"
  chmod --reference="$dest" "$tmp"
  mv -f "$tmp" "$dest"
}
applied=0
complete=0
rollback(){
  local result=$?
  trap - ERR EXIT
  if test "$applied" -eq 1 && test "$complete" -eq 0; then
    set +e
    echo UNIT_ASSESSMENT_ROLLBACK=STARTED >&2
    for i in "${!FILES[@]}"; do put "$STAGE/backup/$i" "$SITE/${FILES[$i]}"; done
    rm -f -- "$FIX_MARKER"
    cd "$SITE"
    chown www:www storage/framework/views
    chmod 0750 storage/framework/views
    runuser -u www -- "$PHP" artisan view:clear >/dev/null 2>&1
    runuser -u www -- "$PHP" artisan view:cache >/dev/null 2>&1
    echo UNIT_ASSESSMENT_ROLLBACK=RESTORED >&2
  fi
  exit "$result"
}
trap rollback ERR EXIT
trap 'exit 143' TERM
trap 'exit 130' INT
applied=1
for i in "${!FILES[@]}"; do put "$STAGE/new/$i" "$SITE/${FILES[$i]}"; done
test -d storage/framework/views && test ! -L storage/framework/views
chown www:www storage/framework/views
chmod 0750 storage/framework/views
runuser -u www -- "$PHP" artisan view:clear
runuser -u www -- "$PHP" artisan view:cache
BAD="$(find storage/framework/views -maxdepth 1 -type f -name '*.php' ! -user www -print -quit)"
test -z "$BAD" || { echo "COMPILED_VIEW_OWNER=$BAD" >&2; exit 2; }
HOME_HTTP="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
  --resolve learncambodia.com:443:127.0.0.1 -o /dev/null \
  -w '%{http_code}' https://learncambodia.com/)"
test "$HOME_HTTP" = 200 || { echo "HOMEPAGE_HTTP=$HOME_HTTP" >&2; exit 2; }
for asset in public/js/campaign-studio-premium.js public/js/campaign-course-workspace.js public/css/campaign-course-workspace.css public/js/homepage-hero-campaigns.js; do
  pathname="${asset#public/}"
  code="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
    --resolve learncambodia.com:443:127.0.0.1 -o /dev/null \
    -w '%{http_code}' "https://learncambodia.com/$pathname")"
  test "$code" = 200 || { echo "ASSET_HTTP=$pathname:$code" >&2; exit 2; }
done
printf 'SOURCE_COMMIT=%s\nBACKUP_DIR=%s\n' "$RELEASE" "$STAGE" > "$STAGE/release-marker"
cp "$STAGE/release-marker" "$FIX_MARKER"
chown root:www "$FIX_MARKER"
chmod 0640 "$FIX_MARKER"
complete=1
echo CAMPAIGN_UNIT_ASSESSMENT_DEPLOY=PASS
echo UNIT_NAMING_DIALOG=ENABLED
echo UNIT_THREE_DOT_ACTIONS=ENABLED
echo RICH_QUESTION_PROMPT=ENABLED
echo QUESTION_MEDIA=ENABLED
echo BLADE_PERMISSIONS=PASS
echo CAMPAIGN_DATA_MUTATIONS=NONE
echo HOMEPAGE_HTTP=200
echo STATIC_ASSETS_HTTP=200
echo "BACKUP_DIR=$STAGE"
echo "BUILDER_URL=https://learncambodia.com/dashboard/appearance/home/campaign-builder?lang=en"
