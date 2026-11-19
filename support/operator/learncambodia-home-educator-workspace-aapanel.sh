#!/usr/bin/env bash
# Apply the role-aware For Educators homepage link.
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com

repo=dombong2026/learncambodia
base=793d2d3b7ccad6f92d0f00b9f44c30aeefa9d668
release="${1:?Pass the reviewed homepage fix commit SHA}"
[[ "$release" =~ ^[a-f0-9]{40}$ && "$release" != "$base" ]]

php=/www/server/php/85/bin/php
backup_root=/var/backups/learncambodia
file=resources/views/home.blade.php
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1

exec 9>"$backup_root/course-builder.lock"
flock -n 9
test ! -e storage/framework/down
test -x "$php"
test -f artisan
test -f "$file"
test ! -L "$file"
test "$(realpath -- "$file")" = "$PWD/$file"

stage="$(mktemp -d "$backup_root/home-educator-workspace-XXXXXXXX")"
printf 'BACKUP=%s\n' "$stage"
git init -q "$stage/source"
source_git() { git -C "$stage/source" "$@"; }
source_git fetch -q --depth=1 "https://github.com/$repo.git" "$base" "$release"
source_git diff --name-only --no-renames "$base" "$release" -- app config resources routes database public lang > "$stage/release-files"
test "$(cat "$stage/release-files")" = "$file"
source_git show "$release:$file" > "$stage/target"
if cmp -s -- "$file" "$stage/target"; then
    printf 'HOME_EDUCATOR_WORKSPACE_APPLY=PASS\n'
    exit 0
fi

mkdir -p -- "$stage/source/$(dirname -- "$file")"
cp -p -- "$file" "$stage/original.blade.php"
cp -p -- "$file" "$stage/source/$file"
source_git add -f -- "$file"
source_git diff --no-renames --binary --full-index "$base" "$release" -- "$file" > "$stage/release.patch"
source_git apply --3way --index "$stage/release.patch"

artisan() { runuser -u www -- "$php" artisan "$@" --no-interaction; }
refresh_views() { artisan view:clear && artisan view:cache; }

swap=''
installed=0
finish() {
    local status=$?
    trap - EXIT INT TERM
    if ((status)); then
        set +e
        [[ -z "$swap" ]] || rm -f -- "$swap"
        if ((installed)); then
            if cp -p -- "$stage/original.blade.php" "$file"; then
                refresh_views || printf 'View cache restore failed.\n' >&2
            else
                printf 'Restore failed: %s\n' "$file" >&2
            fi
        fi
        printf 'HOME_EDUCATOR_WORKSPACE_APPLY=FAIL\nBACKUP=%s\n' "$stage" >&2
    fi
    exit "$status"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

cmp -s -- "$stage/original.blade.php" "$file"
swap="$(mktemp "$(dirname -- "$file")/.home-educator-workspace.XXXXXXXX")"
install -o root -g www -m 0640 "$stage/source/$file" "$swap"
installed=1
mv -f -- "$swap" "$file"
swap=''
runuser -u www -- test -r "$file"
refresh_views
cmp -s -- "$stage/source/$file" "$file"
printf 'HOME_EDUCATOR_WORKSPACE_APPLY=PASS\nBACKUP=%s\n' "$stage"
