#!/usr/bin/env bash
# Pinned, additive LearnCambodia Campaign Course Workspace release.
# Change only editor view, editor JS, validation controller and two new assets.
# Preserve all existing campaigns, routes, course players and databases.
set -Eeuo pipefail
umask 077
RELEASE="$1"
[[ "$RELEASE" =~ ^[a-f0-9]{40}$ ]] || { echo INVALID_COMMIT_SHA; exit 2; }
test "$(id -u)" -eq 0 || { echo ROOT_REQUIRED; exit 2; }
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
REPO=dombong2026/learncambodia
BRANCH=feature/campaign-course-workspace-20261124
LIVE=/www/wwwroot/learncambodia.com
PHP=/www/server/php/85/bin/php
PREVIOUS=44d34af620a36792b11bf184a5b040bcf7a2eef9
VIEW=resources/views/workspace/admin/campaign-studio.blade.php
SCRIPT=public/js/campaign-studio-premium.js
CONTROLLER=app/Http/Controllers/Workspace/HomepageCampaignManagerController.php
NEW_CSS=public/css/campaign-course-workspace.css
NEW_JS=public/js/campaign-course-workspace.js
MARKER="$LIVE/storage/app/private/campaign-course-workspace-release.txt"
for cmd in gh python3 cmp flock curl runuser; do
    command -v "$cmd" >/dev/null || { echo "MISSING_TOOL=$cmd"; exit 2; }
done
test -x "$PHP" && test "$("$PHP" -r 'echo PHP_VERSION_ID;')" -ge 80401 || { echo PHP_UNSUPPORTED; exit 2; }
test -f "$LIVE/artisan"
grep -Fxq "SOURCE_COMMIT=$PREVIOUS" "$LIVE/storage/app/private/home-multicampaign-release.txt" || { echo LIVE_MULTICAMPAIGN_BASE_MISMATCH; exit 2; }
test ! -e "$MARKER" || { echo ALREADY_INSTALLED; exit 0; }
for p in "$VIEW" "$SCRIPT" "$CONTROLLER"; do
  test -f "$LIVE/$p" && test ! -L "$LIVE/$p" || { echo "UNSAFE_FILE=$p"; exit 2; }
done
for p in "$NEW_CSS" "$NEW_JS"; do
  test ! -e "$LIVE/$p" && test ! -L "$LIVE/$p" || { echo "NEW_FILE_CONFLICT=$p"; exit 2; }
done
mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/campaign-course-workspace.lock
flock -n 9 || { echo DEPLOYMENT_LOCK_BUSY; exit 2; }
test "$(gh api "repos/$REPO/git/ref/heads/$BRANCH" --jq '.object.sha')" = "$RELEASE" || { echo REVIEWED_BRANCH_SHA_CHANGED; exit 2; }
gh api "repos/$REPO/actions/runs?head_sha=$RELEASE&per_page=100" | python3 -c '
import json,sys
runs=json.load(sys.stdin).get("workflow_runs",[])
if not any(r.get("name")=="Campaign Course Workspace acceptance" and
           r.get("status")=="completed" and r.get("conclusion")=="success" for r in runs):
    raise SystemExit("PINNED_CI_NOT_PASSED")
print("PINNED_CI=PASS")
'
STAGE="$(mktemp -d /var/backups/learncambodia/campaign-course-workspace.XXXXXXXX)"
mkdir -p "$STAGE/backup" "$STAGE/new" "$STAGE/baseline"
echo "BACKUP_DIR=$STAGE"
download(){
  gh api -H 'Accept: application/vnd.github.raw+json' \
    "repos/$REPO/contents/$1?ref=$2" > "$3"
  test -s "$3"
}
download resources/views/workspace/admin/campaign-studio-premium.blade.php "$PREVIOUS" "$STAGE/baseline/view"
download "$SCRIPT" "$PREVIOUS" "$STAGE/baseline/js"
download "$CONTROLLER" "$PREVIOUS" "$STAGE/baseline/controller"
cmp -s "$STAGE/baseline/view" "$LIVE/$VIEW" || { echo LIVE_VIEW_DRIFT; exit 2; }
cmp -s "$STAGE/baseline/js" "$LIVE/$SCRIPT" || { echo LIVE_EDITOR_JS_DRIFT; exit 2; }
cmp -s "$STAGE/baseline/controller" "$LIVE/$CONTROLLER" || { echo LIVE_CAMPAIGN_CONTROLLER_DRIFT; exit 2; }
echo "EXISTING_EDITOR_BASELINE=PASS"
for p in "$VIEW" "$SCRIPT" "$CONTROLLER"; do
  mkdir -p "$STAGE/backup/$(dirname "$p")"
  cp -p "$LIVE/$p" "$STAGE/backup/$p"
done
download resources/views/workspace/admin/campaign-course-workspace.blade.php "$RELEASE" "$STAGE/new/view"
download "$SCRIPT" "$RELEASE" "$STAGE/new/js"
download "$CONTROLLER" "$RELEASE" "$STAGE/new/controller"
download "$NEW_CSS" "$RELEASE" "$STAGE/new/css"
download "$NEW_JS" "$RELEASE" "$STAGE/new/course-js"
"$PHP" -l "$STAGE/new/controller" >/dev/null
grep -Fq 'id="cs-course-tree"' "$STAGE/new/view"
grep -Fq 'data-cs-tab="translation"' "$STAGE/new/view"
grep -Fq 'title_km' "$STAGE/new/js"
grep -Fq 'Khmer lesson text' "$STAGE/new/controller"
grep -Fq 'html.dark #cs-builder.cs-course-workspace' "$STAGE/new/css"
echo RELEASE_SOURCE_CHECKS=PASS

cd "$LIVE"
"$PHP" artisan route:list --json | python3 -c '
import json,sys
names={r.get("name") for r in json.load(sys.stdin)}
required={"admin.appearance.campaign.edit","admin.appearance.campaign.save","admin.appearance.campaign.media",
          "campaign-manager.play","campaign-manager.check","campaign-manager.cover"}
if required-names: raise SystemExit("ROUTES_MISSING="+str(required-names))
print("CAMPAIGN_ROUTES=PASS")
'
"$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows=\Illuminate\Support\Facades\DB::table("homepage_campaign_items")->get();
file_put_contents($argv[1],json_encode($rows,JSON_THROW_ON_ERROR));
' "$STAGE/saved-campaigns.json"
test -s "$STAGE/saved-campaigns.json"
echo CAMPAIGN_DATA_SNAPSHOT=PASS

put(){
  local tmp
  mkdir -p "$(dirname "$2")"
  tmp="$(mktemp "$(dirname "$2")/.course-ui.XXXXXXXX")"
  cat "$1" > "$tmp"
  if test -e "$2"; then chown --reference="$2" "$tmp"; chmod --reference="$2" "$tmp"
  else chown root:www "$tmp"; chmod 0644 "$tmp"; fi
  mv -f "$tmp" "$2"
}
applied=0
completed=0
rollback(){
  local status=$?
  trap - ERR EXIT
  if test "$applied" -eq 1 && test "$completed" -eq 0; then
    set +e
    echo COURSE_UI_ROLLBACK=STARTED
    for p in "$VIEW" "$SCRIPT" "$CONTROLLER"; do put "$STAGE/backup/$p" "$LIVE/$p"; done
    rm -f -- "$LIVE/$NEW_CSS" "$LIVE/$NEW_JS" "$MARKER"
    cd "$LIVE"
    chown www:www storage/framework/views
    chmod 0750 storage/framework/views
    runuser -u www -- "$PHP" artisan view:clear >/dev/null 2>&1
    runuser -u www -- "$PHP" artisan view:cache >/dev/null 2>&1
    echo COURSE_UI_ROLLBACK=PREVIOUS_EDITOR_RESTORED
  fi
  exit "$status"
}
trap rollback ERR EXIT
applied=1
put "$STAGE/new/css" "$LIVE/$NEW_CSS"
put "$STAGE/new/course-js" "$LIVE/$NEW_JS"
put "$STAGE/new/js" "$LIVE/$SCRIPT"
put "$STAGE/new/controller" "$LIVE/$CONTROLLER"
put "$STAGE/new/view" "$LIVE/$VIEW"
test -d storage/framework/views && test ! -L storage/framework/views
chown www:www storage/framework/views
chmod 0750 storage/framework/views
runuser -u www -- "$PHP" artisan view:clear
runuser -u www -- "$PHP" artisan view:cache
WRONG="$(find storage/framework/views -maxdepth 1 -type f -name '*.php' ! -user www -print -quit)"
test -z "$WRONG" || { echo "BLADE_OWNER_ERROR=$WRONG"; exit 2; }
echo BLADE_PERMISSIONS=PASS

for asset in css/campaign-course-workspace.css js/campaign-course-workspace.js; do
  CODE="$(curl -4 -sS -o /dev/null --connect-timeout 8 --max-time 20 \
   --resolve learncambodia.com:443:127.0.0.1 -w '%{http_code}' "https://learncambodia.com/$asset")"
  test "$CODE" = 200 || { echo "STATIC_ASSET_HTTP=$asset:$CODE"; exit 2; }
done
HOME="$(curl -4 -sS -o /dev/null --connect-timeout 8 --max-time 20 \
  --resolve learncambodia.com:443:127.0.0.1 -w '%{http_code}' https://learncambodia.com/)"
test "$HOME" = 200 || { echo "HOMEPAGE_HTTP=$HOME"; exit 2; }

printf 'SOURCE_COMMIT=%s\nBACKUP_DIR=%s\n' "$RELEASE" "$STAGE" > "$STAGE/marker"
put "$STAGE/marker" "$MARKER"
completed=1
echo COURSE_WORKSPACE_DEPLOY=PASS
echo CAMPAIGN_DATA_CHANGES=NONE
echo EXISTING_CAMPAIGNS=PRESERVED
echo STATIC_ASSETS_HTTP=200
echo HOMEPAGE_HTTP=200
echo "BACKUP_DIR=$STAGE"
echo "EDITOR_URL=https://learncambodia.com/dashboard/appearance/home/campaign-builder?lang=en"
