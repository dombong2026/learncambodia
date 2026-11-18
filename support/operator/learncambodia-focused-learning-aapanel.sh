#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com
repo=dombong2026/learncambodia
base=1deabf2e7d55f1280f8ebf6fccf9dc98084e7285
older_watch=c715a9e0ce6998e170f5e9f56f476d065921e918
release="${1:?Pass the reviewed focused learning commit SHA}"
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

stage="$(mktemp -d "$backup_root/focused-learning-XXXXXXXX")"
printf 'BACKUP=%s\n' "$stage"
git init -q "$stage/source"
git_stage() { git -C "$stage/source" "$@"; }
git_stage fetch -q --depth=1 "https://github.com/$repo.git" "$base" "$release" "$older_watch"
git_stage diff --name-only --no-renames "$base" "$release" -- app database/migrations lang public resources routes > "$stage/paths"
mapfile -t files < "$stage/paths"
test "${#files[@]}" -gt 0

migration=database/migrations/2026_11_18_235000_create_lesson_conversations.php
hub=app/Http/Controllers/LearningHubController.php
lesson_view=resources/views/learning/lesson.blade.php
grep -Fxq "$migration" "$stage/paths"

# The earlier Course Watch deployment changed the public Watch catalog too.
# Keep that exact deployed catalog method when installing the updated private viewer.
git_stage show "$older_watch:$hub" > "$stage/previous-hub"
git_stage show "$older_watch:$lesson_view" > "$stage/previous-view"
git_stage show "$release:$hub" > "$stage/target-hub"
sed -n '/^    public function watch(/,/^    public function lesson(/p' "$stage/previous-hub" | sed '$d' > "$stage/watch-method"
test -s "$stage/watch-method"
awk -v method="$stage/watch-method" '
    /^    public function watch\(/ {
        while ((getline line < method) > 0) print line
        skipping = 1
    }
    /^    public function lesson\(/ { skipping = 0 }
    !skipping { print }
' "$stage/target-hub" > "$stage/target-hub-with-previous-catalog"

changes=()
for file in "${files[@]}"; do
    case "$file" in
        app/*.php|resources/views/*.blade.php|lang/*/focused_learning.php|routes/web.php|public/css/focused-learning.css|public/js/focused-learning.js|"$migration") ;;
        *) printf 'Unexpected runtime file: %s\n' "$file" >&2; exit 1 ;;
    esac
    directory="$(dirname -- "$file")"
    test ! -L "$file"
    test "$(realpath -m "$directory")" = "$PWD/$directory"
    git_stage show "$release:$file" > "$stage/target"
    if [[ -f "$file" ]] && cmp -s -- "$file" "$stage/target"; then
        continue
    fi
    if [[ "$file" == "$hub" ]] && cmp -s -- "$file" "$stage/target-hub-with-previous-catalog"; then
        continue
    fi

    mkdir -p -- "$stage/source/$directory"
    if git_stage cat-file -e "$base:$file" 2>/dev/null; then
        test -f "$file"
        mkdir -p -- "$stage/original/$directory"
        cp -p -- "$file" "$stage/original/$file"
        cp -p -- "$file" "$stage/source/$file"
        git_stage add -f -- "$file"
    else
        test ! -e "$file"
    fi

    if [[ "$file" == "$hub" ]] && cmp -s -- "$file" "$stage/previous-hub"; then
        cp -- "$stage/target-hub-with-previous-catalog" "$stage/source/$file"
    elif [[ "$file" == "$lesson_view" ]] && cmp -s -- "$file" "$stage/previous-view"; then
        cp -- "$stage/target" "$stage/source/$file"
    else
        git_stage diff --no-renames --binary --full-index "$base" "$release" -- "$file" > "$stage/release.patch"
        git_stage apply --3way --index "$stage/release.patch"
    fi
    if [[ "$file" == *.php && "$file" != *.blade.php ]]; then
        "$php" -l "$stage/source/$file" >/dev/null
    fi
    changes+=("$file")
done

artisan() { runuser -u www -- "$php" artisan "$@" --no-interaction; }
route_cached=0
if compgen -G 'bootstrap/cache/routes-*.php' >/dev/null; then route_cached=1; fi
refresh_caches() {
    if ((route_cached)); then artisan route:cache || return; fi
    artisan view:clear && artisan view:cache
}

maintenance=0
installed=()
finish() {
    local status=$?
    trap - EXIT INT TERM
    if ((status)); then
        set +e
        if ((${#installed[@]})); then
            for file in "${installed[@]}"; do
                if [[ -f "$stage/original/$file" ]]; then
                    cp -p -- "$stage/original/$file" "$file"
                else
                    rm -f -- "$file"
                fi
            done
            refresh_caches || echo 'Cache refresh after code rollback failed.' >&2
        fi
        if ((maintenance)); then artisan up; fi
        printf 'FOCUSED_LEARNING_APPLY=FAIL Backup: %s\n' "$stage" >&2
    fi
    exit "$status"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

maintenance=1
artisan down --retry=30
for file in "${changes[@]}"; do
    if [[ -f "$stage/original/$file" ]]; then
        cmp -s -- "$stage/original/$file" "$file"
    else
        test ! -e "$file"
    fi
    directory="$(dirname -- "$file")"
    if [[ ! -d "$directory" ]]; then install -d -m 0755 -o root -g www "$directory"; fi
    swap="$(mktemp "$directory/.focused-learning.XXXXXXXX")"
    mode=0640
    if [[ "$file" == public/* ]]; then mode=0644; fi
    installed+=("$file")
    install -o root -g www -m "$mode" "$stage/source/$file" "$swap"
    mv -f -- "$swap" "$file"
    runuser -u www -- test -r "$file"
done
refresh_caches
artisan route:list --json --name=account.lessons > "$stage/student-routes"
artisan route:list --json --name=instructor.lesson-support > "$stage/instructor-routes"
grep -Fq 'account.lessons.support' "$stage/student-routes"
grep -Fq 'instructor.lesson-support.reply' "$stage/instructor-routes"
artisan migrate --path="$migration" --force
artisan up
maintenance=0
test ! -e storage/framework/down
printf 'FOCUSED_LEARNING_APPLY=PASS\nBACKUP=%s\n' "$stage"
