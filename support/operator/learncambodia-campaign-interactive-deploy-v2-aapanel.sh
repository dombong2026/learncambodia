#!/usr/bin/env bash
# Additive, backup-guarded release of the Interactive Campaign Builder.
# Existing CampaignStudioController, homepage_campaigns, old Builder, media, and player remain intact.
set -Eeuo pipefail
umask 077
REPO=dombong2026/learncambodia
SHA="$1"
[[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || { echo BAD_COMMIT_SHA; exit 2; }
test "$(id -u)" -eq 0 || { echo RUN_AS_ROOT; exit 2; }
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
LIVE=/www/wwwroot/learncambodia.com
PHP=/www/server/php/85/bin/php
test -x "$PHP" && test "$("$PHP" -r 'echo PHP_VERSION_ID;')" -ge 80401
test -f "$LIVE/artisan"
test -f "$LIVE/app/Http/Controllers/Workspace/CampaignStudioController.php"
test -f "$LIVE/resources/views/workspace/admin/campaign-studio.blade.php"
test -f "$LIVE/resources/views/workspace/admin/home-campaign-builder.blade.php"
mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/campaign-interactive-deploy.lock
flock -n 9

test "$(gh api "repos/$REPO/pulls/120" --jq '.head.sha')" = "$SHA" || { echo PR_HEAD_MOVED; exit 2; }
for check in "v0.0.1 Foundation Quality" "QA Vite asset artifact - pinned source (no deployment)"; do
  num="$(gh api "repos/$REPO/actions/runs?head_sha=$SHA&per_page=100" --jq "[.workflow_runs[] | select(.name == \"$check\" and .status == \"completed\" and .conclusion == \"success\")] | length")"
  test "$num" -gt 0 || { echo "MISSING_PASSED_CHECK=$check"; exit 2; }
done
echo "RELEASE_CHECKS=PASS"

NEW_FILES="app/Models/HomeCampaign.php
app/Http/Controllers/Workspace/HomeCampaignController.php
database/migrations/2026_11_23_000051_create_home_campaigns_table.php
resources/views/workspace/admin/home-campaign-interactive.blade.php
resources/views/learning/home-campaign.blade.php
resources/views/home-campaign-featured.blade.php
public/js/home-campaign-interactive.js"
OLD_FILES="routes/web.php
resources/views/home.blade.php
resources/views/workspace/admin/campaign-studio.blade.php"

MARKER="$LIVE/storage/app/private/learncambodia-campaign-interactive-deployment.txt"
if test -f "$MARKER"; then
  grep -Fxq "RELEASE_SHA=$SHA" "$MARKER" && { echo RELEASE_ALREADY_APPLIED; exit 0; }
  echo DIFFERENT_RELEASE_ALREADY_INSTALLED
  exit 2
fi
while IFS= read -r p; do
  test ! -e "$LIVE/$p" && test ! -L "$LIVE/$p" || { echo "NEW_PATH_CONFLICT=$p"; exit 2; }
done <<< "$NEW_FILES"

cd "$LIVE"
"$PHP" artisan route:list --json > /tmp/lc-campaign-preflight-routes.json
python3 - /tmp/lc-campaign-preflight-routes.json <<'PY'
import json,sys
rows=json.load(open(sys.argv[1]))
uris={r.get('uri') for r in rows}
for uri in ('dashboard/appearance/home/campaign-builder','dashboard/appearance/home/campaign-builder/media'):
    if uri not in uris: raise SystemExit('MISSING_LEGACY_ROUTE='+uri)
if 'dashboard/appearance/home/campaign-builder/interactive' in uris: raise SystemExit('NEW_ROUTE_ALREADY_EXISTS')
print('LEGACY_ROUTES_VERIFIED=PASS')
PY
rm -f /tmp/lc-campaign-preflight-routes.json

STAGE="$(mktemp -d /var/backups/learncambodia/campaign-interactive.XXXXXXXX)"
mkdir -p "$STAGE/source" "$STAGE/backup" "$STAGE/next"
echo "RELEASE_BACKUP=$STAGE"
while IFS= read -r p; do
  mkdir -p "$STAGE/source/$(dirname "$p")"
  gh api -H 'Accept: application/vnd.github.raw+json' "repos/$REPO/contents/$p?ref=$SHA" > "$STAGE/source/$p"
  test -s "$STAGE/source/$p"
  if [[ "$p" == *.php ]]; then "$PHP" -l "$STAGE/source/$p" >/dev/null; fi
done <<< "$NEW_FILES"
if command -v node >/dev/null; then node --check "$STAGE/source/public/js/home-campaign-interactive.js"; fi
while IFS= read -r p; do
  test ! -L "$LIVE/$p" || { echo "UNSAFE_SYMLINK=$p"; exit 2; }
  mkdir -p "$STAGE/backup/$(dirname "$p")" "$STAGE/next/$(dirname "$p")"
  cp -p "$LIVE/$p" "$STAGE/backup/$p"
done <<< "$OLD_FILES"

python3 - "$STAGE" <<'PY'
from pathlib import Path
import sys
stage=Path(sys.argv[1])
before=stage/'backup'
after=stage/'next'
path=before/'routes/web.php'
content=path.read_text()
hero="Route::get('/', [CatalogController::class, 'home'])->name('home');"
appearance="Route::prefix('appearance')->name('appearance.')->group(function (): void {"
if content.count(hero)!=1 or content.count(appearance)!=1: raise SystemExit('ROUTE_ANCHORS_NOT_UNIQUE')
public=r"""Route::get('/interactive-campaigns/{campaign}', [\App\Http\Controllers\Workspace\HomeCampaignController::class, 'show'])->name('interactive-campaign.show');
Route::get('/campaigns/{campaign}/cover', [\App\Http\Controllers\Workspace\HomeCampaignController::class, 'cover'])->name('interactive-campaign.cover');
Route::post('/interactive-campaigns/{campaign}/lessons/{lessonId}/check', [\App\Http\Controllers\Workspace\HomeCampaignController::class, 'check'])->middleware('throttle:30,1')->name('interactive-campaign.check');
"""
admin=r"""Route::get('/appearance/home/campaign-builder/interactive', [\App\Http\Controllers\Workspace\HomeCampaignController::class, 'builder'])->name('campaign.builder');
        Route::post('/appearance/home/campaign-builder/interactive', [\App\Http\Controllers\Workspace\HomeCampaignController::class, 'save'])->name('campaign.save');

        """
content=content.replace(hero,hero+'\n'+public,1)
content=content.replace(appearance,admin+appearance,1)
(after/'routes/web.php').write_text(content)

path=before/'resources/views/home.blade.php'
content=path.read_text()
if not content.rstrip().endswith('@endsection'): raise SystemExit('HOME_TEMPLATE_STRUCTURE_CHANGED')
content=content.rstrip()[:-len('@endsection')] + "@include('home-campaign-featured')\n@endsection\n"
(after/'resources/views/home.blade.php').write_text(content)

path=before/'resources/views/workspace/admin/campaign-studio.blade.php'
content=path.read_text()
anchor="@section('content')"
if content.count(anchor)!=1: raise SystemExit('STUDIO_TEMPLATE_STRUCTURE_CHANGED')
link="""\n<div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm dark:border-amber-800 dark:bg-slate-900">
    <div><strong>Interactive Quiz &amp; Exam Builder</strong><p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Modules, lessons, matching, explanations, feedback, and 16:9 covers. Existing campaigns are preserved.</p></div>
    <a href="{{ route('admin.campaign.builder') }}" class="rounded-lg bg-[#0e315e] px-4 py-2 font-bold text-white">Open interactive builder</a>
</div>\n"""
content=content.replace(anchor,anchor+link,1)
(after/'resources/views/workspace/admin/campaign-studio.blade.php').write_text(content)
print('SURGICAL_LIVE_OVERLAY_PREPARED=PASS')
PY
"$PHP" -l "$STAGE/next/routes/web.php" >/dev/null
"$PHP" -r '
  require "vendor/autoload.php";
  $app=require "bootstrap/app.php";
  $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  $rows=\Illuminate\Support\Facades\DB::table("homepage_campaigns")->get();
  file_put_contents($argv[1],json_encode($rows,JSON_THROW_ON_ERROR));
' "$STAGE/original-homepage-campaigns.json"
test -s "$STAGE/original-homepage-campaigns.json"
echo "EXISTING_CAMPAIGN_DATA_BACKUP=PASS"

applied=0
completed=0
restore() {
  local src="$1" dest="$2" tmp
  tmp="$(mktemp "$(dirname "$dest")/.lc-campaign-restore.XXXXXXXX")"
  cat "$src" > "$tmp"
  chown --reference="$src" "$tmp"
  chmod --reference="$src" "$tmp"
  mv -f "$tmp" "$dest"
}
rollback() {
  local code="$?"
  trap - ERR
  if test "$applied" = 1 && test "$completed" = 0; then
    set +e
    echo CAMPAIGN_DEPLOYMENT_ROLLBACK=STARTED
    while IFS= read -r p; do restore "$STAGE/backup/$p" "$LIVE/$p"; done <<< "$OLD_FILES"
    while IFS= read -r p; do rm -f -- "$LIVE/$p"; done <<< "$NEW_FILES"
    cd "$LIVE"
    "$PHP" artisan optimize:clear >/dev/null 2>&1
    echo CAMPAIGN_DEPLOYMENT_ROLLBACK=RESTORED_EXISTING_FILES
    echo 'NOTE: The additive home_campaigns table may remain if its migration completed.'
  fi
  exit "$code"
}
trap rollback ERR

install_atomic() {
  local src="$1" dest="$2" tmp
  mkdir -p "$(dirname "$dest")"
  tmp="$(mktemp "$(dirname "$dest")/.lc-campaign-install.XXXXXXXX")"
  cat "$src" > "$tmp"
  if test -e "$dest"; then
    chown --reference="$dest" "$tmp"
    chmod --reference="$dest" "$tmp"
  else
    chown root:www "$tmp"
    chmod 0644 "$tmp"
  fi
  mv -f "$tmp" "$dest"
}

applied=1
while IFS= read -r p; do install_atomic "$STAGE/source/$p" "$LIVE/$p"; done <<< "$NEW_FILES"
while IFS= read -r p; do install_atomic "$STAGE/next/$p" "$LIVE/$p"; done <<< "$OLD_FILES"

"$PHP" artisan migrate --path=database/migrations/2026_11_23_000051_create_home_campaigns_table.php --force --no-interaction
"$PHP" artisan route:clear
"$PHP" artisan view:clear
"$PHP" artisan route:list --json > "$STAGE/routes-after.json"
python3 - "$STAGE/routes-after.json" <<'PY'
import json,sys
rows=json.load(open(sys.argv[1]))
names={r.get('name') for r in rows}
required={'admin.appearance.campaign.edit','admin.appearance.campaign.save','admin.appearance.campaign.media',
'admin.campaign.builder','admin.campaign.save','interactive-campaign.show','interactive-campaign.cover','interactive-campaign.check','campaign.check','campaign.asset'}
for name in required-names: raise SystemExit('MISSING_ROUTE='+name)
print('LEGACY_AND_INTERACTIVE_ROUTES=PASS')
PY
"$PHP" artisan view:cache
mkdir -p "$(dirname "$MARKER")"
printf 'RELEASE_SHA=%s\nBACKUP_DIR=%s\n' "$SHA" "$STAGE" > "$STAGE/marker"
install_atomic "$STAGE/marker" "$MARKER"
completed=1
echo CAMPAIGN_DEPLOYMENT=PASS
echo EXISTING_CAMPAIGN_STUDIO=PRESERVED
echo EXISTING_CAMPAIGN_RECORDS=PRESERVED
echo INTERACTIVE_BUILDER_URL=https://learncambodia.com/dashboard/appearance/home/campaign-builder/interactive?lang=en
echo "BACKUP_DIR=$STAGE"
