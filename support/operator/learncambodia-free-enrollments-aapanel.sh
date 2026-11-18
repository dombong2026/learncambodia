#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com
repo=dombong2026/learncambodia
base=e5a0f9113b848445cb8b2a3a8b2a277e299cecd5
release="${1:?Pass the reviewed free enrollment commit SHA}"
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

quality="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/ci.yml")][0].id // empty')"
[[ "$quality" =~ ^[0-9]+$ ]] || {
    echo 'The pinned release must pass Foundation Quality.' >&2
    exit 1
}

files=(
    app/Console/Commands/ActivateFreeCourseEnrollments.php
    app/Http/Controllers/Workspace/StudentEnrollmentController.php
    resources/views/courses/show.blade.php
    resources/views/workspace/student/course-catalog.blade.php
)
stage="$(mktemp -d "$backup_root/free-enrollments-XXXXXXXX")"
printf 'BACKUP=%s\n' "$stage"
git init -q "$stage/source"
source_git() { git -C "$stage/source" "$@"; }
source_git fetch -q --depth=1 "https://github.com/$repo.git" "$base" "$release"
source_git diff --name-only --no-renames "$base" "$release" -- app public resources > "$stage/release-files"
printf '%s\n' "${files[@]}" > "$stage/expected-files"
cmp -- "$stage/release-files" "$stage/expected-files"

fully_installed=1
for file in "${files[@]}"; do
    test ! -L "$file"
    directory="$(dirname -- "$file")"
    test "$(realpath -m "$directory")" = "$PWD/$directory"
    source_git show "$release:$file" > "$stage/expected"
    if [[ ! -f "$file" ]] || ! cmp -s -- "$file" "$stage/expected"; then
        fully_installed=0
    fi
done
artisan() { runuser -u www -- "$php" artisan "$@" --no-interaction; }

if ((fully_installed)); then
    artisan learncambodia:activate-free-enrollments
    printf '%s\n' 'FREE_ENROLLMENTS_APPLY=PASS' 'This release is already installed.'
    exit 0
fi

for file in "${files[@]}"; do
    directory="$(dirname -- "$file")"
    if source_git cat-file -e "$base:$file" 2>/dev/null; then
        test -f "$file"
        mkdir -p -- "$stage/original/$directory" "$stage/source/$directory"
        cp -p -- "$file" "$stage/original/$file"
        cp -p -- "$file" "$stage/source/$file"
        source_git add -f -- "$file"
    else
        test ! -e "$file"
    fi
done
source_git diff --no-renames --binary --full-index "$base" "$release" -- "${files[@]}" > "$stage/release.patch"
source_git apply --3way --index "$stage/release.patch"
for file in "${files[@]}"; do
    test -f "$stage/source/$file"
    if [[ -f "$stage/original/$file" ]]; then
        cmp -s -- "$file" "$stage/original/$file"
    else
        test ! -e "$file"
    fi
done

for file in "${files[@]}"; do
    if [[ "$file" == app/* ]]; then "$php" -l "$stage/source/$file"; fi
done

refresh_views() { artisan view:clear && artisan view:cache; }
maintenance=0
changed=0
rollback() {
    local status=$?
    trap - EXIT INT TERM
    if ((status)); then
        set +e
        if ((changed)); then
            for file in "${files[@]}"; do
                if [[ -f "$stage/original/$file" ]]; then
                    cp -p -- "$stage/original/$file" "$file" || printf 'Restore failed: %s\n' "$file" >&2
                else
                    rm -f -- "$file"
                fi
            done
            refresh_views || echo 'View cache rebuild after rollback failed.' >&2
        fi
        if ((maintenance)); then artisan up; fi
        printf 'FREE_ENROLLMENTS_APPLY=FAIL Backup: %s\n' "$stage" >&2
    fi
    exit "$status"
}
trap rollback EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

maintenance=1
artisan down --retry=30
changed=1
for file in "${files[@]}"; do
    directory="$(dirname -- "$file")"
    swap="$(mktemp "$directory/.free-enrollments.XXXXXXXX")"
    install -o root -g www -m 0640 "$stage/source/$file" "$swap"
    mv -f -- "$swap" "$file"
    runuser -u www -- test -r "$file"
done
refresh_views
artisan learncambodia:activate-free-enrollments
artisan up
test ! -e storage/framework/down
printf 'FREE_ENROLLMENTS_APPLY=PASS\nBACKUP=%s\n' "$stage"
