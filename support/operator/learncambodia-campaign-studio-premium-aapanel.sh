#!/usr/bin/env bash
# LearnCambodia Campaign Studio UI only: surgical, backed-up production release.
# Preserves existing homepage_campaigns rows, public player, draft/publish controller.
set -Eeuo pipefail
umask 077
test "$(id -u)" -eq 0 || { echo ROOT_REQUIRED; exit 2; }
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
REPO=dombong2026/learncambodia
SHA="$1"
[[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || { echo INVALID_SHA; exit 2; }
LIVE=/www/wwwroot/learncambodia.com
PHP=/www/server/php/85/bin/php
test -x "$PHP"
test "$("$PHP" -r 'echo PHP_VERSION_ID;')" -ge 80401
test -f "$LIVE/artisan"
test -f "$LIVE/routes/web.php"
test -f "$LIVE/app/Http/Controllers/Workspace/CampaignStudioController.php"
test -f "$LIVE/resources/views/workspace/admin/campaign-studio.blade.php"
id www >/dev/null
mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/campaign-studio-premium-ui.lock
flock -n 9

# Pin to the reviewed PR source, not a mutable branch.
test "$(gh api "repos/$REPO/git/ref/heads/feature/campaign-studio-premium-ui-20261123" --jq '.object.sha')" = "$SHA" || {
  echo RELEASE_SHA_NOT_BRANCH_HEAD; exit 2;
}
test "$(gh api "repos/$REPO/actions/runs?head_sha=$SHA&per_page=50" \
 --jq '[.workflow_runs[] | select(.name == "Campaign Studio premium UI preflight" and .conclusion == "success")]|length')" -gt 0 || {
   echo RELEASE_PREFLIGHT_NOT_PASSED; exit 2;
}
echo RELEASE_CHECKS=PASS

NEW_PATHS="resources/views/workspace/admin/campaign-studio-premium.blade.php
public/js/campaign-studio-premium.js
public/css/campaign-studio-premium.css
app/Http/Controllers/Workspace/CampaignCoverController.php
database/migrations/2026_11_23_235500_create_homepage_campaign_cover_table.php"
CHANGED_PATHS="routes/web.php
app/Http/Controllers/Workspace/CampaignStudioController.php
resources/views/workspace/admin/campaign-studio.blade.php"
MARKER="$LIVE/storage/app/private/campaign-studio-premium-release.txt"

test ! -f "$MARKER" || { echo CAMPAIGN_PREMIUM_ALREADY_INSTALLED; exit 2; }
while IFS= read -r path; do
  test ! -e "$LIVE/$path" && test ! -L "$LIVE/$path" || { echo "FILE_ALREADY_EXISTS=$path"; exit 2; }
done <<< "$NEW_PATHS"

cd "$LIVE"
"$PHP" artisan route:list --json | python3 -c '
import json,sys
x=json.load(sys.stdin)
names={a.get("name") for a in x}
required={"admin.appearance.campaign.edit","admin.appearance.campaign.save","admin.appearance.campaign.media","campaign.check","campaign.asset"}
if not required.issubset(names):
    raise SystemExit("REQUIRED_LIVE_ROUTES_MISSING="+str(sorted(required-names)))
if "admin.appearance.campaign.cover.save" in names:
    raise SystemExit("COVER_ROUTE_ALREADY_PRESENT")
print("EXISTING_CAMPAIGN_ROUTES=PASS")
'

STAGE="$(mktemp -d /var/backups/learncambodia/campaign-studio-premium.XXXXXXXX)"
mkdir -p "$STAGE/source" "$STAGE/backup" "$STAGE/next"
echo "BACKUP_DIR=$STAGE"

while IFS= read -r path; do
  mkdir -p "$STAGE/source/$(dirname "$path")"
  gh api -H 'Accept: application/vnd.github.raw+json' \
    "repos/$REPO/contents/$path?ref=$SHA" > "$STAGE/source/$path"
  test -s "$STAGE/source/$path"
  if [[ "$path" == *.php ]]; then "$PHP" -l "$STAGE/source/$path" >/dev/null; fi
done <<< "$NEW_PATHS"
command -v node >/dev/null && node --check "$STAGE/source/public/js/campaign-studio-premium.js"

while IFS= read -r path; do
  test ! -L "$LIVE/$path" || { echo "UNSAFE_SYMLINK=$path"; exit 2; }
  mkdir -p "$STAGE/backup/$(dirname "$path")" "$STAGE/next/$(dirname "$path")"
  cp -p "$LIVE/$path" "$STAGE/backup/$path"
done <<< "$CHANGED_PATHS"

python3 - "$STAGE" <<'PY'
from pathlib import Path
import sys
stage=Path(sys.argv[1])
source=stage/'source'
backup=stage/'backup'
nxt=stage/'next'
blade=(source/'resources/views/workspace/admin/campaign-studio-premium.blade.php').read_text()
required_in_view=["@section('workspace_compact', '1')","id=\"cs-builder\"","id=\"cs-cover-file\"","id=\"cs-modules\""]
for marker in required_in_view:
    if marker not in blade: raise SystemExit("INVALID_VIEW="+marker)
(nxt/'resources/views/workspace/admin/campaign-studio.blade.php').write_text(blade)

routes=(backup/'routes/web.php').read_text()
needle="Route::post('/home/campaign-builder/media', [\\App\\Http\\Controllers\\Workspace\\CampaignStudioController::class, 'media'])->middleware('throttle:20,1')->name('campaign.media');"
if routes.count(needle)!=1: raise SystemExit("LIVE_MEDIA_ROUTE_ANCHOR_MISMATCH")
addition="\n            Route::post('/home/campaign-builder/cover', [\\App\\Http\\Controllers\\Workspace\\CampaignCoverController::class, 'save'])->middleware('throttle:10,1')->name('campaign.cover.save');"
routes=routes.replace(needle,needle+addition,1)
(nxt/'routes/web.php').write_text(routes)

controller=(backup/'app/Http/Controllers/Workspace/CampaignStudioController.php').read_text()
needle="if (!in_array($type,['image','video'],true)) unset($row['asset']);"
if controller.count(needle)!=1: raise SystemExit("LIVE_CONTROLLER_MEDIA_ANCHOR_MISMATCH")
replacement="""if ($asset && !in_array($type,['image','video'],true)) {
                    if (!preg_match('/^[a-f0-9]{64}\\.(jpg|png|webp|mp4|webm)$/D',$asset)
                        || !is_file(storage_path('app/campaign-assets/'.$asset))) $fail('Choose verified uploaded lesson media.');
                    $row['url']=route('campaign.asset',$asset,false);
                }
                if (!$asset && !in_array($type,['image','video'],true)) unset($row['asset']);"""
controller=controller.replace(needle,replacement,1)
needle="if (in_array($type,['quiz','exam'],true)) {"
if controller.count(needle)!=1: raise SystemExit("LIVE_CONTROLLER_QUIZ_ANCHOR_MISMATCH")
controller=controller.replace(needle,needle+"\n                    $row['body']=\\App\\Support\\EditorHtml::clean($row['body'] ?? '', true) ?? '';",1)
(nxt/'app/Http/Controllers/Workspace/CampaignStudioController.php').write_text(controller)
print("SURGICAL_LIVE_OVERLAY=PASS")
PY

"$PHP" -l "$STAGE/next/routes/web.php" >/dev/null
"$PHP" -l "$STAGE/next/app/Http/Controllers/Workspace/CampaignStudioController.php" >/dev/null

# Preserve the existing published and draft JSON before any changes.
"$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows=\Illuminate\Support\Facades\DB::table("homepage_campaigns")->get();
file_put_contents($argv[1],json_encode($rows,JSON_THROW_ON_ERROR));
' "$STAGE/existing-campaign-records.json"
test -s "$STAGE/existing-campaign-records.json"
echo EXISTING_CAMPAIGN_DATA_BACKUP=PASS

applied=0
complete=0
replace_file() {
  local src="$1" dest="$2" temp
  mkdir -p "$(dirname "$dest")"
  temp="$(mktemp "$(dirname "$dest")/.campaign-premium.XXXXXXXX")"
  cat "$src" > "$temp"
  if test -f "$dest"; then
    chown --reference="$dest" "$temp"
    chmod --reference="$dest" "$temp"
  else
    chown root:www "$temp"
    chmod 0644 "$temp"
  fi
  mv -f "$temp" "$dest"
}
rollback() {
  local exit_code="$?"
  trap - ERR
  if test "$applied" = 1 && test "$complete" = 0; then
    set +e
    echo RELEASE_ROLLBACK=STARTED
    while IFS= read -r p; do replace_file "$STAGE/backup/$p" "$LIVE/$p"; done <<< "$CHANGED_PATHS"
    while IFS= read -r p; do rm -f -- "$LIVE/$p"; done <<< "$NEW_PATHS"
    rm -f -- "$MARKER"
    cd "$LIVE"
    "$PHP" artisan route:clear >/dev/null 2>&1
    chown www:www storage/framework/views
    chmod 0750 storage/framework/views
    runuser -u www -- "$PHP" artisan view:clear >/dev/null 2>&1
    echo RELEASE_ROLLBACK=RESTORED_PREVIOUS_UI
    echo "BACKUP_DIR=$STAGE"
  fi
  exit "$exit_code"
}
trap rollback ERR

applied=1
while IFS= read -r p; do replace_file "$STAGE/source/$p" "$LIVE/$p"; done <<< "$NEW_PATHS"
"$PHP" artisan migrate --path=database/migrations/2026_11_23_235500_create_homepage_campaign_cover_table.php --force --no-interaction
while IFS= read -r p; do replace_file "$STAGE/next/$p" "$LIVE/$p"; done <<< "$CHANGED_PATHS"
"$PHP" artisan route:clear

"$PHP" artisan route:list --json | python3 -c '
import sys,json
names={r.get("name") for r in json.load(sys.stdin)}
required={"admin.appearance.campaign.edit","admin.appearance.campaign.save",
"admin.appearance.campaign.media","admin.appearance.campaign.cover.save","campaign.check","campaign.asset"}
missing=required-names
if missing: raise SystemExit("CAMPAIGN_ROUTE_VALIDATION_FAILED="+str(sorted(missing)))
print("CAMPAIGN_ROUTES=PASS")
'

# FIX for prior 500: never build compiled Laravel views as root.
test -d storage/framework/views && test ! -L storage/framework/views
chown www:www storage/framework/views
chmod 0750 storage/framework/views
runuser -u www -- "$PHP" artisan view:clear
runuser -u www -- "$PHP" artisan view:cache
BAD_OWNER="$(find storage/framework/views -maxdepth 1 -type f -name '*.php' ! -user www -print -quit)"
test -z "$BAD_OWNER" || { echo "BLADE_PERMISSION_ERROR=$BAD_OWNER"; exit 2; }
echo BLADE_PERMISSIONS=PASS

HTTP="$(curl -sS -o /dev/null --connect-timeout 8 --max-time 20 \
  --resolve learncambodia.com:443:127.0.0.1 -w '%{http_code}' https://learncambodia.com/)"
test "$HTTP" = 200 || { echo "HOMEPAGE_HTTP=$HTTP"; exit 2; }

mkdir -p "$(dirname "$MARKER")"
printf 'SOURCE_COMMIT=%s\nBACKUP=%s\n' "$SHA" "$STAGE" > "$STAGE/release-marker"
replace_file "$STAGE/release-marker" "$MARKER"
complete=1
echo CAMPAIGN_STUDIO_PREMIUM_DEPLOY=PASS
echo LEGACY_CAMPAIGN_DATA=PRESERVED
echo EXISTING_PUBLISHING_AND_CHECK_ANSWER=PRESERVED
echo "BACKUP_DIR=$STAGE"
echo "BUILDER_URL=https://learncambodia.com/dashboard/appearance/home/campaign-builder?lang=en"
