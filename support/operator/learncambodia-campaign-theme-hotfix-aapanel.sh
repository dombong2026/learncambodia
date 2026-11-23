#!/usr/bin/env bash
# Targeted Homepage Campaign Manager light/dark theme hotfix.
# No database, route or other application files are changed.
set -Eeuo pipefail
umask 077
RELEASE="$1"
[[ "$RELEASE" =~ ^[0-9a-f]{40}$ ]] || { echo "INVALID_RELEASE_SHA" >&2; exit 2; }
test "$(id -u)" -eq 0 || { echo "ROOT_REQUIRED" >&2; exit 2; }
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
REPO=dombong2026/learncambodia
LIVE=/www/wwwroot/learncambodia.com
PHP=/www/server/php/85/bin/php
OLD_RELEASE=44d34af620a36792b11bf184a5b040bcf7a2eef9
VIEW=resources/views/workspace/admin/homepage-campaigns-table.blade.php
CSS=public/css/homepage-campaigns-theme.css
MARKER="$LIVE/storage/app/private/home-multicampaign-release.txt"
HOTFIX_MARKER="$LIVE/storage/app/private/home-campaign-theme-fix.txt"

for binary in gh flock curl python3 runuser cmp; do
    command -v "$binary" >/dev/null || { echo "MISSING_TOOL=$binary" >&2; exit 2; }
done
test -x "$PHP" && test "$("$PHP" -r 'echo PHP_VERSION_ID;')" -ge 80401 || {
    echo "UNSUPPORTED_PHP" >&2; exit 2;
}
test -f "$LIVE/artisan" && test -f "$LIVE/$VIEW"
test -f "$MARKER" && grep -Fxq "SOURCE_COMMIT=$OLD_RELEASE" "$MARKER" || {
    echo "PRODUCTION_RELEASE_MISMATCH" >&2; exit 2;
}
test ! -f "$HOTFIX_MARKER" || { echo "THEME_HOTFIX_ALREADY_MARKED_APPLIED"; exit 0; }
test ! -e "$LIVE/$CSS" && test ! -L "$LIVE/$CSS" || {
    echo "THEME_CSS_ALREADY_EXISTS" >&2; exit 2;
}
test ! -L "$LIVE/$VIEW" || { echo "UNSAFE_VIEW_SYMLINK" >&2; exit 2; }
test "$(gh api "repos/$REPO/git/ref/heads/feature/home-campaign-multicampaign-20261123" --jq '.object.sha')" = "$RELEASE" || {
    echo "GITHUB_BRANCH_SHA_MOVED" >&2; exit 2;
}

RUNS="$(gh api "repos/$REPO/actions/runs?head_sha=$RELEASE&per_page=100")"
printf '%s' "$RUNS" | python3 -c '
import json,sys
runs=json.load(sys.stdin).get("workflow_runs",[])
name="Multi-Campaign homepage preflight"
if not any(r.get("name")==name and r.get("status")=="completed" and r.get("conclusion")=="success" for r in runs):
    raise SystemExit("CAMPAIGN_THEME_CI_NOT_PASSED")
print("THEME_CI=PASS")
'

mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/home-campaign-theme-hotfix.lock
flock -n 9 || { echo "THEME_HOTFIX_LOCK_BUSY" >&2; exit 2; }
STAGE="$(mktemp -d /var/backups/learncambodia/campaign-theme.XXXXXXXX)"
echo "BACKUP_DIR=$STAGE"
mkdir -p "$STAGE/backup" "$STAGE/new"
download() {
    local path="$1" ref="$2" output="$3"
    gh api -H 'Accept: application/vnd.github.raw+json' \
        "repos/$REPO/contents/$path?ref=$ref" > "$output"
    test -s "$output"
}
download "$VIEW" "$OLD_RELEASE" "$STAGE/backup/expected-original.blade.php"
cmp -s "$STAGE/backup/expected-original.blade.php" "$LIVE/$VIEW" || {
    echo "LIVE_CAMPAIGN_TABLE_DRIFT: no changes made" >&2; exit 2;
}
cp -p "$LIVE/$VIEW" "$STAGE/backup/current.blade.php"
download "$VIEW" "$RELEASE" "$STAGE/new/template.blade.php"
download "$CSS" "$RELEASE" "$STAGE/new/theme.css"
grep -Fq "css/homepage-campaigns-theme.css" "$STAGE/new/template.blade.php"
grep -Fq 'data-hcm-create' "$STAGE/new/template.blade.php"
grep -Fq 'data-campaign-status' "$STAGE/new/template.blade.php"
grep -Fq 'html.dark #homepage-campaign-management' "$STAGE/new/theme.css"
echo "THEME_SOURCE=PASS"

install_atomic() {
    local src="$1" dst="$2" tmp
    mkdir -p "$(dirname "$dst")"
    tmp="$(mktemp "$(dirname "$dst")/.lc-campaign-theme.XXXXXXXX")"
    cat "$src" > "$tmp"
    if test -e "$dst"; then
        chown --reference="$dst" "$tmp"
        chmod --reference="$dst" "$tmp"
    else
        chown root:www "$tmp"
        chmod 0644 "$tmp"
    fi
    mv -f "$tmp" "$dst"
}
applied=0
completed=0
rollback() {
    local code=$?
    trap - ERR EXIT
    if test "$applied" -eq 1 && test "$completed" -eq 0; then
        set +e
        echo "THEME_HOTFIX_ROLLBACK=STARTED" >&2
        install_atomic "$STAGE/backup/current.blade.php" "$LIVE/$VIEW"
        rm -f -- "$LIVE/$CSS" "$HOTFIX_MARKER"
        cd "$LIVE"
        runuser -u www -- "$PHP" artisan view:clear >/dev/null 2>&1
        runuser -u www -- "$PHP" artisan view:cache >/dev/null 2>&1
        echo "THEME_HOTFIX_ROLLBACK=PREVIOUS_TABLE_RESTORED" >&2
    fi
    exit "$code"
}
trap rollback ERR EXIT
applied=1
install_atomic "$STAGE/new/theme.css" "$LIVE/$CSS"
install_atomic "$STAGE/new/template.blade.php" "$LIVE/$VIEW"

cd "$LIVE"
CACHE=storage/framework/views
test -d "$CACHE" && test ! -L "$CACHE"
chown www:www "$CACHE"
chmod 0750 "$CACHE"
runuser -u www -- "$PHP" artisan view:clear
runuser -u www -- "$PHP" artisan view:cache
UNREADABLE="$(find "$CACHE" -maxdepth 1 -type f -name '*.php' ! -user www -print -quit)"
test -z "$UNREADABLE" || { echo "BLADE_OWNER_MISMATCH=$UNREADABLE" >&2; exit 2; }
CSS_HTTP="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
    --resolve learncambodia.com:443:127.0.0.1 \
    -o /dev/null -w '%{http_code}' \
    'https://learncambodia.com/css/homepage-campaigns-theme.css?v=1')"
test "$CSS_HTTP" = 200 || { echo "CSS_HTTP=$CSS_HTTP" >&2; exit 2; }
HOME_HTTP="$(curl -4 -sS --connect-timeout 8 --max-time 20 \
    --resolve learncambodia.com:443:127.0.0.1 \
    -o /dev/null -w '%{http_code}' \
    https://learncambodia.com/)"
test "$HOME_HTTP" = 200 || { echo "HOMEPAGE_HTTP=$HOME_HTTP" >&2; exit 2; }
printf 'SOURCE_COMMIT=%s\nBACKUP_DIR=%s\n' "$RELEASE" "$STAGE" > "$STAGE/hotfix-marker"
install_atomic "$STAGE/hotfix-marker" "$HOTFIX_MARKER"
completed=1
echo "THEME_HOTFIX=PASS"
echo "THEME_CSS_HTTP=$CSS_HTTP"
echo "HOMEPAGE_HTTP=$HOME_HTTP"
echo "BLADE_PERMISSIONS=PASS"
echo "CAMPAIGN_DATA_CHANGES=NONE"
echo "BACKUP_DIR=$STAGE"
echo "APPEARANCE_URL=https://learncambodia.com/dashboard/appearance/home?lang=en"
