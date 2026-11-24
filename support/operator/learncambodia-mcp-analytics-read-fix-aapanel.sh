#!/usr/bin/env bash
# Targeted LearnCambodia MCP aggregate-analytics READ validation fix.
# Does not modify .env, databases, permissions, routes, content, or MCP tokens.
set -Eeuo pipefail
set +x
umask 077

test "$#" -eq 1 || { echo "USAGE: script <reviewed 40-char commit>"; exit 2; }
RELEASE="$1"
[[ "$RELEASE" =~ ^[a-f0-9]{40}$ ]] || { echo INVALID_COMMIT >&2; exit 2; }
test "$(id -u)" -eq 0 || { echo ROOT_REQUIRED >&2; exit 2; }

export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
REPO=dombong2026/learncambodia
BRANCH=fix/mcp-analytics-read-validation-20261124
BASE=90595e4cf3a55681ad1b8ac8d3168096ea09c249
BASE_BLOB=235d35b9eabe4272cf884681be02dac6a57a1c22
FILE=app/Support/McpCrudEngine.php
SITE=/www/wwwroot/learncambodia.com
PHP=/www/server/php/85/bin/php
MARKER="$SITE/storage/app/private/learncambodia-mcp-analytics-read-fix.txt"

for tool in gh git python3 php sha256sum flock cmp mktemp runuser; do
    command -v "$tool" >/dev/null || { echo "MISSING_TOOL=$tool" >&2; exit 2; }
done
test -f "$SITE/artisan" && test -x "$PHP" && test -f "$SITE/$FILE"
test ! -L "$SITE/$FILE" || { echo "SYMLINK_REFUSED" >&2; exit 2; }
mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/mcp-analytics-read.lock
flock -n 9 || { echo "MCP_ANALYTICS_LOCK_BUSY" >&2; exit 2; }

test "$(gh api "repos/$REPO/git/ref/heads/$BRANCH" --jq '.object.sha')" = "$RELEASE" || {
    echo "BRANCH_MOVED_OR_SHA_NOT_REVIEWED" >&2; exit 2;
}
gh api "repos/$REPO/actions/runs?head_sha=$RELEASE&per_page=100" | python3 -c '
import json,sys
runs=json.load(sys.stdin).get("workflow_runs",[])
if not any(x.get("name")=="MCP Analytics READ Regression" and x.get("status")=="completed"
           and x.get("conclusion")=="success" for x in runs):
    raise SystemExit("MCP_ANALYTICS_CI_NOT_GREEN")
print("MCP_ANALYTICS_PINNED_CI=PASS")
'

CURRENT_BLOB="$(git hash-object "$SITE/$FILE")"
if test -f "$MARKER"; then
    test "$(sed -n 's/^TARGET_BLOB=//p' "$MARKER")" = "$CURRENT_BLOB" || {
        echo "MCP_ANALYTICS_MARKER_DRIFT" >&2; exit 2;
    }
    echo "MCP_ANALYTICS_ALREADY_APPLIED=PASS"
    exit 0
fi
test "$CURRENT_BLOB" = "$BASE_BLOB" || {
    echo "MCP_ANALYTICS_LIVE_SOURCE_DRIFT_ABORT" >&2; exit 2;
}

STAGE="$(mktemp -d /var/backups/learncambodia/mcp-analytics-read.XXXXXXXX)"
echo "MCP_ANALYTICS_BACKUP=$STAGE"
cp -p -- "$SITE/$FILE" "$STAGE/original.php"
gh api -H 'Accept: application/vnd.github.raw+json' \
    "repos/$REPO/contents/$FILE?ref=$BASE" > "$STAGE/base.php"
cmp -s "$STAGE/original.php" "$STAGE/base.php" || {
    echo "MCP_ANALYTICS_BASELINE_MISMATCH" >&2; exit 2;
}
gh api -H 'Accept: application/vnd.github.raw+json' \
    "repos/$REPO/contents/$FILE?ref=$RELEASE" > "$STAGE/new.php"

python3 - "$STAGE/base.php" "$STAGE/new.php" <<'PY'
from pathlib import Path
import sys
original=Path(sys.argv[1]).read_text()
candidate=Path(sys.argv[2]).read_text()
before1="'settings','analytics','analytics'"
after1="'settings','analytics'"
before2="'help_articles','campaigns','media','users','support_tickets','categories','settings'\n            ])],"
after2="'help_articles','campaigns','media','users','support_tickets','categories','settings','analytics'\n            ])],"
if original.count(before1)!=1 or original.count(before2)!=1:
    raise SystemExit("MCP_ANALYTICS_KNOWN_SOURCE_ANCHOR_DRIFT")
expected=original.replace(before1,after1,1).replace(before2,after2,1)
if candidate!=expected:
    raise SystemExit("MCP_ANALYTICS_RELEASE_CONTAINS_UNREVIEWED_ENGINE_CHANGES")
print("MCP_ANALYTICS_EXACT_TWO_LINE_PATCH=PASS")
PY

"$PHP" -l "$STAGE/new.php" || { echo "MCP_ANALYTICS_PHP_LINT_FAIL" >&2; exit 2; }

install_atomically() {
    local source="$1" destination="$2" tmp
    tmp="$(mktemp "$(dirname "$destination")/.lc-mcp-analytics.XXXXXXXX")"
    cat "$source" > "$tmp"
    chown --reference="$destination" "$tmp"
    chmod --reference="$destination" "$tmp"
    mv -f -- "$tmp" "$destination"
}

reload_fpm() {
    if test -x /etc/init.d/php-fpm-85; then
        /etc/init.d/php-fpm-85 reload
        echo "MCP_PHP_FPM_RELOAD=PASS"
    elif command -v systemctl >/dev/null && systemctl is-active --quiet php-fpm-85; then
        systemctl reload php-fpm-85
        echo "MCP_PHP_FPM_RELOAD=PASS"
    else
        echo "MCP_PHP_FPM_RELOAD=UNAVAILABLE_CHECK_AAPANEL"
    fi
}

applied=0
finished=0
rollback() {
    code="$?"
    trap - EXIT ERR
    if test "$applied" -eq 1 && test "$finished" -eq 0; then
        set +e
        install_atomically "$STAGE/original.php" "$SITE/$FILE"
        rm -f -- "$MARKER"
        reload_fpm
        echo "MCP_ANALYTICS_ROLLBACK=RESTORED" >&2
    fi
    exit "$code"
}
trap rollback EXIT ERR
trap 'exit 130' INT
trap 'exit 143' TERM

applied=1
install_atomically "$STAGE/new.php" "$SITE/$FILE"
"$PHP" -l "$SITE/$FILE"
test "$(git hash-object "$SITE/$FILE")" = "$(git hash-object "$STAGE/new.php")"
reload_fpm

NEW_BLOB="$(git hash-object "$STAGE/new.php")"
printf 'SOURCE_COMMIT=%s\nBASE_COMMIT=%s\nTARGET_BLOB=%s\nBACKUP_DIR=%s\n' \
    "$RELEASE" "$BASE" "$NEW_BLOB" "$STAGE" > "$STAGE/marker"
cp -- "$STAGE/marker" "$MARKER"
chown root:www "$MARKER"
chmod 0640 "$MARKER"
finished=1

echo "MCP_ANALYTICS_VALIDATION_FIX=PASS"
echo "MCP_READ_SCOPE=UNCHANGED"
echo "MCP_REMOTE_WRITES=UNCHANGED"
echo "MCP_CONTENT_MUTATIONS=NONE"
echo "MCP_ANALYTICS_LIVE_READ_ACCEPTANCE=PENDING_TOKEN_TEST"
echo "MCP_BACKUP_DIR=$STAGE"
