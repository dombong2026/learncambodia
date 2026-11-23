#!/usr/bin/env bash
# Additive, pinned multi-campaign upgrade. Previous singleton tables remain intact.
set -Eeuo pipefail
umask 077
# Print the failing line for any early, read-only prerequisite failure.
trap 'code=$?; printf "PREDEPLOY_CHECK_FAILED line=%s exit=%s\n" "$LINENO" "$code" >&2' ERR
REPO=dombong2026/learncambodia
RELEASE="$1"
[[ "$RELEASE" =~ ^[a-f0-9]{40}$ ]] || { echo INVALID_RELEASE_SHA; exit 2; }
test "$(id -u)" -eq 0 || { echo ROOT_REQUIRED; exit 2; }
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
LIVE=/www/wwwroot/learncambodia.com
PHP=/www/server/php/85/bin/php
BASE=68feceeb9c3e40c2cfef9c6a678189a2a5fd2385
test -x "$PHP" && test "$("$PHP" -r 'echo PHP_VERSION_ID;')" -ge 80401
for binary in gh python3 curl flock runuser; do
  command -v "$binary" >/dev/null || { echo "MISSING_REQUIRED_TOOL=$binary" >&2; exit 2; }
done
if command -v node >/dev/null 2>&1; then
  echo "NODE_ON_SERVER=AVAILABLE"
else
  echo "NODE_ON_SERVER=ABSENT_USING_GITHUB_CI_VALIDATED_JS"
fi
test -f "$LIVE/artisan"
test -f "$LIVE/resources/views/workspace/admin/home-studio-settings.blade.php"
test -f "$LIVE/resources/views/workspace/admin/campaign-studio.blade.php"
grep -Fxq "SOURCE_COMMIT=$BASE" "$LIVE/storage/app/private/campaign-studio-premium-release.txt" || { echo EXISTING_RELEASE_NOT_VERIFIED; exit 2; }
mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/home-multicampaign.lock
flock -n 9
test "$(gh api "repos/$REPO/git/ref/heads/feature/home-campaign-multicampaign-20261123" --jq '.object.sha')" = "$RELEASE" || { echo REVIEWED_HEAD_CHANGED; exit 2; }
test "$(gh api "repos/$REPO/actions/runs?head_sha=$RELEASE&per_page=100" --jq '[.workflow_runs[]|select(.name == "Multi-Campaign homepage preflight" and .conclusion == "success")]|length')" -gt 0 || { echo CI_PREFLIGHT_MISSING; exit 2; }
echo RELEASE_CHECKS=PASS

NEW="app/Models/HomepageCampaignItem.php
app/Http/Controllers/Workspace/HomepageCampaignManagerController.php
database/migrations/2026_11_23_235700_create_homepage_campaign_items_table.php
resources/views/workspace/admin/homepage-campaigns-table.blade.php
resources/views/partials/homepage-hero-campaigns.blade.php
public/js/homepage-hero-campaigns.js"
EXISTING="routes/web.php
resources/views/home.blade.php
resources/views/workspace/admin/home-studio-settings.blade.php
resources/views/workspace/admin/campaign-studio.blade.php
public/js/campaign-studio-premium.js"
MARKER="$LIVE/storage/app/private/home-multicampaign-release.txt"
test ! -e "$MARKER" || { echo RELEASE_ALREADY_INSTALLED; exit 2; }
while IFS= read -r p; do test ! -e "$LIVE/$p" && test ! -L "$LIVE/$p" || { echo "CONFLICT=$p"; exit 2; }; done <<< "$NEW"
cd "$LIVE"
"$PHP" artisan route:list --json | python3 -c '
import json,sys
names={r.get("name") for r in json.load(sys.stdin)}
required={"admin.appearance.campaign.edit","admin.appearance.campaign.save","admin.appearance.campaign.media","campaign.check","campaign.asset"}
if required-names: raise SystemExit("LEGACY_ROUTES_MISSING="+str(required-names))
if "campaign-manager.play" in names: raise SystemExit("NEW_ROUTES_ALREADY_PRESENT")
'
STAGE="$(mktemp -d /var/backups/learncambodia/home-multicampaign.XXXXXXXX)"
mkdir -p "$STAGE/source" "$STAGE/backup" "$STAGE/next" "$STAGE/base"
echo "BACKUP_DIR=$STAGE"
download() {
  mkdir -p "$(dirname "$3")"
  gh api -H 'Accept: application/vnd.github.raw+json' "repos/$REPO/contents/$1?ref=$2" > "$3"
  test -s "$3"
}
while IFS= read -r p; do
  download "$p" "$RELEASE" "$STAGE/source/$p"
  if [[ "$p" == *.php ]]; then "$PHP" -l "$STAGE/source/$p" >/dev/null; fi
done <<< "$NEW"
download resources/views/workspace/admin/campaign-studio-premium.blade.php "$RELEASE" "$STAGE/source/new-studio.blade.php"
download public/js/campaign-studio-premium.js "$RELEASE" "$STAGE/source/new-studio.js"
if command -v node >/dev/null 2>&1; then
  node --check "$STAGE/source/new-studio.js"
  node --check "$STAGE/source/public/js/homepage-hero-campaigns.js"
  echo "LOCAL_JAVASCRIPT_SYNTAX=PASS"
else
  echo "LOCAL_JAVASCRIPT_SYNTAX=SKIPPED_GITHUB_CI_REQUIRED"
fi
download resources/views/workspace/admin/campaign-studio-premium.blade.php "$BASE" "$STAGE/base/studio.blade.php"
download public/js/campaign-studio-premium.js "$BASE" "$STAGE/base/studio.js"
cmp -s "$STAGE/base/studio.blade.php" "$LIVE/resources/views/workspace/admin/campaign-studio.blade.php" || { echo LIVE_STUDIO_DRIFT; exit 2; }
cmp -s "$STAGE/base/studio.js" "$LIVE/public/js/campaign-studio-premium.js" || { echo LIVE_EDITOR_DRIFT; exit 2; }
echo EXISTING_CAMPAIGN_STUDIO_BASELINE=PASS
while IFS= read -r p; do
  test -f "$LIVE/$p" && test ! -L "$LIVE/$p" || { echo "UNSAFE_PATH=$p"; exit 2; }
  mkdir -p "$STAGE/backup/$(dirname "$p")" "$STAGE/next/$(dirname "$p")"
  cp -p "$LIVE/$p" "$STAGE/backup/$p"
done <<< "$EXISTING"

python3 - "$STAGE" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1])
b=p/'backup';n=p/'next'
routes=(b/'routes/web.php').read_text()
oldget=r"Route::get('/home/campaign-builder', [\App\Http\Controllers\Workspace\CampaignStudioController::class, 'edit'])->name('campaign.edit');"
oldpost=r"Route::post('/home/campaign-builder', [\App\Http\Controllers\Workspace\CampaignStudioController::class, 'save'])->name('campaign.save');"
if routes.count(oldget)!=1 or routes.count(oldpost)!=1: raise SystemExit('EXISTING_STUDIO_ROUTES_CHANGED')
newget=oldget.replace('CampaignStudioController','HomepageCampaignManagerController')
newpost=oldpost.replace('CampaignStudioController','HomepageCampaignManagerController')
routes=routes.replace(oldget,newget,1).replace(oldpost,newpost,1)
extra_admin=r"""
            Route::post('/home/campaign-builder/items/{campaign}/status', [\App\Http\Controllers\Workspace\HomepageCampaignManagerController::class, 'status'])->name('campaign-manager.status');
"""
routes=routes.replace(newpost,newpost+extra_admin,1)
routes=routes.rstrip()+r"""
// Public Quiz and Exam endpoints, independent of the legacy singleton player.
Route::get('/home-campaigns/{campaign}/play', [\App\Http\Controllers\Workspace\HomepageCampaignManagerController::class, 'play'])->name('campaign-manager.play');
Route::post('/home-campaigns/{campaign}/check', [\App\Http\Controllers\Workspace\HomepageCampaignManagerController::class, 'check'])->middleware('throttle:60,1')->name('campaign-manager.check');
Route::get('/home-campaigns/{campaign}/cover', [\App\Http\Controllers\Workspace\HomepageCampaignManagerController::class, 'cover'])->name('campaign-manager.cover');
"""+"\n"
(n/'routes/web.php').write_text(routes)

appearance=(b/'resources/views/workspace/admin/home-studio-settings.blade.php').read_text()
if appearance.count('Open campaign builder')!=1 or not appearance.rstrip().endswith('@endsection'):
    raise SystemExit('LIVE_APPEARANCE_STRUCTURE_CHANGED')
appearance=appearance.replace('Open campaign builder','Create a new campaign')
appearance=appearance.rstrip()[:-len('@endsection')]+"\n@include('workspace.admin.homepage-campaigns-table')\n@endsection\n"
(n/'resources/views/workspace/admin/home-studio-settings.blade.php').write_text(appearance)

home=(b/'resources/views/home.blade.php').read_text()
begin=home.find('@section(')
start=home.find('<section',begin)
end=home.find('</section>',start)
if start<0 or end<0 or home[start:end].count('<section')!=1:
    raise SystemExit('LIVE_HERO_STRUCTURE_CHANGED')
home=home[:end]+"@include('partials.homepage-hero-campaigns')\n"+home[end:]
(n/'resources/views/home.blade.php').write_text(home)
(n/'resources/views/workspace/admin/campaign-studio.blade.php').write_bytes((p/'source/new-studio.blade.php').read_bytes())
(n/'public/js/campaign-studio-premium.js').write_bytes((p/'source/new-studio.js').read_bytes())
print('SAFE_LIVE_OVERLAY=PASS')
PY
"$PHP" -l "$STAGE/next/routes/web.php" >/dev/null
"$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=\Illuminate\Support\Facades\DB::connection();
$records=["campaigns"=>$db->table("homepage_campaigns")->get(),"cover"=>$db->table("homepage_campaign_cover")->get()];
file_put_contents($argv[1],json_encode($records,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
' "$STAGE/previous-campaign-records.json"
test -s "$STAGE/previous-campaign-records.json"
echo LEGACY_CAMPAIGN_DATA_BACKUP=PASS

put() {
  local tmp
  mkdir -p "$(dirname "$2")"
  tmp="$(mktemp "$(dirname "$2")/.lc-multicampaign.XXXXXXXX")"
  cat "$1" > "$tmp"
  if test -e "$2"; then
    chown --reference="$2" "$tmp"
    chmod --reference="$2" "$tmp"
  else
    chown root:www "$tmp"
    chmod 0644 "$tmp"
  fi
  mv -f "$tmp" "$2"
}
applied=0
completed=0
rollback() {
  local code=$?
  trap - ERR
  if test "$applied" -eq 1 && test "$completed" -eq 0; then
    set +e
    echo MULTICAMPAIGN_ROLLBACK=STARTED
    while IFS= read -r p; do put "$STAGE/backup/$p" "$LIVE/$p"; done <<< "$EXISTING"
    while IFS= read -r p; do rm -f "$LIVE/$p"; done <<< "$NEW"
    cd "$LIVE"
    "$PHP" artisan route:clear >/dev/null 2>&1
    chown www:www storage/framework/views
    chmod 0750 storage/framework/views
    runuser -u www -- "$PHP" artisan view:clear >/dev/null 2>&1
    echo MULTICAMPAIGN_ROLLBACK=FILES_RESTORED
    echo ADDITIVE_DATABASE_TABLE=RETAINED
  fi
  exit "$code"
}
trap rollback ERR
applied=1
while IFS= read -r p; do put "$STAGE/source/$p" "$LIVE/$p"; done <<< "$NEW"
"$PHP" artisan migrate --path=database/migrations/2026_11_23_235700_create_homepage_campaign_items_table.php --force --no-interaction
"$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=\Illuminate\Support\Facades\DB::connection();
$db->transaction(function()use($db){
 if($db->table("homepage_campaign_items")->count())return;
 $legacy=$db->table("homepage_campaigns")->where("id",1)->first();
 if(!$legacy)return;
 $pub=json_decode($legacy->published??"[]",true)?:[];
 $draft=json_decode($legacy->draft??"[]",true)?:[];
 if(!$pub&&!$draft)return;
 $rows=$draft?:$pub;
 $cover=$db->table("homepage_campaign_cover")->where("id",1)->first();
 $admin=$legacy->updated_by??$db->table("users")->orderBy("id")->value("id");
 if(!$admin)throw new RuntimeException("Missing campaign owner");
 $kind=$cover->kind??"quiz";if(!in_array($kind,["quiz","exam"],true))$kind="quiz";
 $status=$pub&&!empty($legacy->published_at)?"published":"draft";
 $now=now();
 $db->table("homepage_campaign_items")->insert([
  "title"=>mb_substr((string)($rows[0]["course"]??"Imported campaign"),0,180),
  "slug"=>"imported-homepage-campaign","kind"=>$kind,"status"=>$status,
  "draft"=>$draft?json_encode($draft,JSON_UNESCAPED_UNICODE):null,
  "published"=>$pub?json_encode($pub,JSON_UNESCAPED_UNICODE):null,
  "cover_asset"=>$cover->asset??null,
  "version"=>1,"published_at"=>$status==="published"?($legacy->published_at??$now):null,
  "created_by"=>$admin,"updated_by"=>$admin,"created_at"=>$now,"updated_at"=>$now
 ]);
});
'
echo LEGACY_CAMPAIGN_IMPORTED=PASS
while IFS= read -r p; do put "$STAGE/next/$p" "$LIVE/$p"; done <<< "$EXISTING"
"$PHP" artisan route:clear
"$PHP" artisan route:list --json | python3 -c '
import json,sys
names={r.get("name") for r in json.load(sys.stdin)}
required={"admin.appearance.campaign.edit","admin.appearance.campaign.save",
"admin.appearance.campaign-manager.status","campaign.check","campaign.asset",
"campaign-manager.play","campaign-manager.check","campaign-manager.cover"}
if required-names: raise SystemExit("MISSING_ROUTE="+str(required-names))
print("CAMPAIGN_ROUTE_VERIFICATION=PASS")
'
test -d storage/framework/views && test ! -L storage/framework/views
chown www:www storage/framework/views
chmod 0750 storage/framework/views
runuser -u www -- "$PHP" artisan view:clear
runuser -u www -- "$PHP" artisan view:cache
BAD="$(find storage/framework/views -maxdepth 1 -type f -name '*.php' ! -user www -print -quit)"
test -z "$BAD" || { echo "BLADE_OWNER_ERROR=$BAD"; exit 2; }
echo BLADE_PERMISSIONS=PASS
HTTP="$(curl -4 -sS -o /dev/null --connect-timeout 8 --max-time 25 \
 --resolve learncambodia.com:443:127.0.0.1 -w '%{http_code}' https://learncambodia.com/)"
test "$HTTP" = 200 || { echo "HOMEPAGE_HTTP=$HTTP"; exit 2; }
echo HOMEPAGE_HTTP=200
mkdir -p "$(dirname "$MARKER")"
printf 'SOURCE_COMMIT=%s\nBACKUP_DIR=%s\n' "$RELEASE" "$STAGE" > "$STAGE/release-marker"
put "$STAGE/release-marker" "$MARKER"
completed=1
echo MULTICAMPAIGN_DEPLOY=PASS
echo LEGACY_SINGLETON_DATA=PRESERVED
echo "BACKUP_DIR=$STAGE"
echo "APPEARANCE_URL=https://learncambodia.com/dashboard/appearance/home?lang=en"
