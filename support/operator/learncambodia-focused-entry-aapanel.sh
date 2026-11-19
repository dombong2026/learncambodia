#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com

repo=dombong2026/learncambodia
base=97443ee34c0d6728ee4aaf709a022936b3ba2069
release="${1:?Pass the reviewed focused-entry commit SHA}"
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
    app/Http/Controllers/Workspace/StudentEnrollmentController.php
    app/Http/Controllers/Workspace/StudentLessonQuizController.php
    resources/views/learning/lesson.blade.php
    resources/views/learning/watch.blade.php
    resources/views/workspace/student/assigned-assessments.blade.php
    resources/views/workspace/student/exam-attempt.blade.php
    resources/views/workspace/student/study.blade.php
)
stage="$(mktemp -d "$backup_root/focused-entry-XXXXXXXX")"
printf 'BACKUP=%s\n' "$stage"
git init -q "$stage/source"
source_git() { git -C "$stage/source" "$@"; }
source_git fetch -q --depth=1 "https://github.com/$repo.git" "$base" "$release"
source_git diff --name-only --no-renames "$base" "$release" -- app resources routes database public lang > "$stage/release-files"
printf '%s\n' "${files[@]}" > "$stage/expected-files"
cmp -- "$stage/release-files" "$stage/expected-files"

watch=resources/views/learning/watch.blade.php
watch_earlier=54fa0ae72a2022f2416b3adb4905b5d614ebd770
watch_updated=8937f71756245e19fcb76cf8e272801897f52da9
changes=()
for file in "${files[@]}"; do
    directory="$(dirname -- "$file")"
    test ! -L "$file"
    test -f "$file"
    test "$(realpath -m "$directory")" = "$PWD/$directory"
    source_git show "$release:$file" > "$stage/target"
    if cmp -s -- "$file" "$stage/target"; then continue; fi

    original_blob="$(source_git hash-object "$PWD/$file")"
    if [[ "$file" == "$watch" && "$original_blob" == "$watch_updated" ]]; then continue; fi
    mkdir -p -- "$stage/original/$directory" "$stage/source/$directory"
    cp -p -- "$file" "$stage/original/$file"
    cp -p -- "$file" "$stage/source/$file"
    if [[ "$file" == "$watch" && "$original_blob" == "$watch_earlier" ]]; then
        # The earlier public Watch catalog has two explicit curriculum links.
        sed "s/route('account.enrollments.show', \$enrollment)/route('account.enrollments.show', [\$enrollment, 'view' => 'curriculum'])/g" \
            "$stage/original/$file" > "$stage/source/$file"
        test "$(source_git hash-object "$stage/source/$file")" = "$watch_updated"
    else
        source_git add -f -- "$file"
        source_git diff --no-renames --binary --full-index "$base" "$release" -- "$file" > "$stage/release.patch"
        source_git apply --3way --index "$stage/release.patch"
    fi

    if [[ "$file" == app/* ]]; then "$php" -l "$stage/source/$file"; fi
    changes+=("$file")
done

if ((${#changes[@]} == 0)); then
    printf '%s\n' 'FOCUSED_ENTRY_APPLY=PASS' 'This update is already installed.'
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
        printf 'FOCUSED_ENTRY_APPLY=FAIL Backup: %s\n' "$stage" >&2
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
    swap="$(mktemp "$(dirname -- "$file")/.focused-entry.XXXXXXXX")"
    install -o root -g www -m 0640 "$stage/source/$file" "$swap"
    installed+=("$file")
    mv -f -- "$swap" "$file"
    runuser -u www -- test -r "$file"
done
refresh_views
artisan up
maintenance=0
test ! -e storage/framework/down
printf 'FOCUSED_ENTRY_APPLY=PASS\nBACKUP=%s\n' "$stage"
