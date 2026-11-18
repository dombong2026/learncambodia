#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com

repo=dombong2026/learncambodia
base=806ab82103aecbc98cdc90d45feb37815422bf87
release="${1:?Pass the reviewed watch-player-layout commit SHA}"
[[ "$release" =~ ^[a-f0-9]{40}$ && "$release" != "$base" ]]

php=/www/server/php/85/bin/php
backup_root=/var/backups/learncambodia
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1

exec 9>"$backup_root/course-builder.lock"
flock -n 9
test ! -e storage/framework/down
test -x "$php"
test -f resources/views/layouts/focused-learning.blade.php

quality="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/ci.yml")][0].id // empty')"
[[ "$quality" =~ ^[0-9]+$ ]] || { echo 'The pinned release must pass Foundation Quality.' >&2; exit 1; }

files=(
    lang/en/focused_learning.php
    lang/km/focused_learning.php
    lang/zh/focused_learning.php
    public/css/focused-learning.css
    resources/views/layouts/focused-learning.blade.php
    resources/views/learning/lesson.blade.php
    resources/views/learning/partials/outline.blade.php
    resources/views/learning/partials/viewer.blade.php
)
stage="$(mktemp -d "$backup_root/watch-player-layout-XXXXXXXX")"
printf 'BACKUP=%s\n' "$stage"
git init -q "$stage/source"
source_git() { git -C "$stage/source" "$@"; }
source_git fetch -q --depth=1 "https://github.com/$repo.git" "$base" "$release"
source_git diff --name-only --no-renames "$base" "$release" -- app resources routes database public lang > "$stage/release-files"
printf '%s\n' "${files[@]}" > "$stage/expected-files"
cmp -- "$stage/release-files" "$stage/expected-files"

changes=()
for file in "${files[@]}"; do
    directory="$(dirname -- "$file")"
    test ! -L "$file"
    test -f "$file"
    test "$(realpath -m "$directory")" = "$PWD/$directory"
    source_git show "$release:$file" > "$stage/target"
    if cmp -s -- "$file" "$stage/target"; then continue; fi

    mkdir -p -- "$stage/original/$directory" "$stage/source/$directory"
    cp -p -- "$file" "$stage/original/$file"
    cp -p -- "$file" "$stage/source/$file"
    source_git add -f -- "$file"
    source_git diff --no-renames --binary --full-index "$base" "$release" -- "$file" > "$stage/release.patch"
    source_git apply --3way --index "$stage/release.patch"

    if [[ "$file" == lang/* ]]; then "$php" -l "$stage/source/$file"; fi
    changes+=("$file")
done

if ((${#changes[@]} == 0)); then
    printf '%s\n' 'WATCH_PLAYER_LAYOUT_APPLY=PASS' 'This update is already installed.'
    exit 0
fi

artisan() { runuser -u www -- "$php" artisan "$@" --no-interaction; }
refresh_views() { artisan view:clear && artisan view:cache; }
maintenance=0
installed=()
finish() {
    local status=$?
    trap - EXIT INT TERM
    if ((status)); then
        set +e
        for file in "${installed[@]}"; do
            cp -p -- "$stage/original/$file" "$file" || printf 'Restore failed: %s\n' "$file" >&2
        done
        if ((${#installed[@]})); then refresh_views || echo 'View cache restore failed.' >&2; fi
        if ((maintenance)); then artisan up; fi
        printf 'WATCH_PLAYER_LAYOUT_APPLY=FAIL Backup: %s\n' "$stage" >&2
    fi
    exit "$status"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

maintenance=1
artisan down --retry=30
for file in "${changes[@]}"; do
    cmp -s -- "$stage/original/$file" "$file"
    swap="$(mktemp "$(dirname -- "$file")/.watch-player-layout.XXXXXXXX")"
    mode=0640
    if [[ "$file" == public/* ]]; then mode=0644; fi
    install -o root -g www -m "$mode" "$stage/source/$file" "$swap"
    installed+=("$file")
    mv -f -- "$swap" "$file"
    runuser -u www -- test -r "$file"
done
refresh_views
artisan up
maintenance=0
test ! -e storage/framework/down
printf 'WATCH_PLAYER_LAYOUT_APPLY=PASS\nBACKUP=%s\n' "$stage"
