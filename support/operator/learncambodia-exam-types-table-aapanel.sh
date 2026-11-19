#!/usr/bin/env bash
# Install the Exam Types table and row actions.
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com

repo=dombong2026/learncambodia
base=e46a3724ba695faab1e5dda22cf2dd19f0e7a16b
release="${1:?Pass the reviewed Exam Types table commit SHA}"
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
test -f artisan

files=(
    app/Http/Controllers/Workspace/AdminExaminationController.php
    lang/en/exam_types.php
    lang/km/exam_types.php
    lang/zh/exam_types.php
    public/css/exam-types.css
    public/js/exam-type-dialog.js
    resources/views/workspace/admin/examination/exam-type-dialog.blade.php
    resources/views/workspace/admin/examination/exam-types.blade.php
    routes/web.php
)

stage="$(mktemp -d "$backup_root/exam-types-table-XXXXXXXX")"
printf 'BACKUP=%s\n' "$stage"
git init -q "$stage/source"
source_git() { git -C "$stage/source" "$@"; }
source_git fetch -q --depth=1 "https://github.com/$repo.git" "$base" "$release"
source_git diff --name-only --no-renames "$base" "$release" -- app config resources routes database public lang > "$stage/release-files"
printf '%s\n' "${files[@]}" > "$stage/expected-files"
cmp -- "$stage/release-files" "$stage/expected-files"

changes=()
for file in "${files[@]}"; do
    directory="$(dirname -- "$file")"
    test ! -L "$file"
    test "$(realpath -- "$directory")" = "$PWD/$directory"
    mkdir -p -- "$stage/source/$directory"
    source_git show "$release:$file" > "$stage/target"

    if [[ -f "$file" ]]; then
        if cmp -s -- "$file" "$stage/target"; then continue; fi
        # A pre-existing release addition must match the pinned file exactly.
        source_git cat-file -e "$base:$file"
        mkdir -p -- "$stage/original/$directory"
        cp -p -- "$file" "$stage/original/$file"
        cp -p -- "$file" "$stage/source/$file"
        source_git add -f -- "$file"
        source_git diff --no-renames --binary --full-index "$base" "$release" -- "$file" > "$stage/release.patch"
        source_git apply --3way --index "$stage/release.patch"
    else
        test ! -e "$file"
        if source_git cat-file -e "$base:$file" 2>/dev/null; then exit 1; fi
        cp -- "$stage/target" "$stage/source/$file"
    fi
    if [[ "$file" == *.php && "$file" != *.blade.php ]]; then "$php" -l "$stage/source/$file"; fi
    changes+=("$file")
done

artisan() { runuser -u www -- "$php" artisan "$@" --no-interaction; }
refresh_views() { artisan view:clear && artisan view:cache; }
maintenance=0
route_cached=0
if compgen -G 'bootstrap/cache/routes-*.php' > /dev/null; then route_cached=1; fi
installed=()
swap=''
finish() {
    local status=$?
    trap - EXIT INT TERM
    if ((status)); then
        set +e
        [[ -z "$swap" ]] || rm -f -- "$swap"
        for file in "${installed[@]}"; do
            if [[ -f "$stage/original/$file" ]]; then
                cp -p -- "$stage/original/$file" "$file" || printf 'Restore failed: %s\n' "$file" >&2
            else
                rm -f -- "$file" || printf 'Restore failed: %s\n' "$file" >&2
            fi
        done
        if (("${#installed[@]}")); then
            if ((route_cached)); then artisan route:cache; fi
            refresh_views || echo 'View cache restore failed.' >&2
        fi
        if ((maintenance)); then artisan up; fi
        printf 'EXAM_TYPES_TABLE_APPLY=FAIL\nBACKUP=%s\n' "$stage" >&2
    fi
    exit "$status"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

artisan down --retry=30
maintenance=1
for file in "${changes[@]}"; do
    if [[ -f "$stage/original/$file" ]]; then
        cmp -s -- "$stage/original/$file" "$file"
    else
        test ! -e "$file"
    fi
    swap="$(mktemp "$(dirname -- "$file")/.exam-types-table.XXXXXXXX")"
    install -o root -g www -m 0640 "$stage/source/$file" "$swap"
    installed+=("$file")
    mv -f -- "$swap" "$file"
    swap=''
    runuser -u www -- test -r "$file"
done

if ((route_cached)); then artisan route:cache; fi
refresh_views
artisan route:list --name=admin.exam-types.destroy --json > "$stage/exam-type-routes.json"
grep -Fq '"admin.exam-types.destroy"' "$stage/exam-type-routes.json"
for file in "${changes[@]}"; do
    cmp -s -- "$stage/source/$file" "$file"
done
artisan up
maintenance=0
test ! -e storage/framework/down
printf 'EXAM_TYPES_TABLE_APPLY=PASS\nBACKUP=%s\n' "$stage"
