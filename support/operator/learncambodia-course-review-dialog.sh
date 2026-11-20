#!/usr/bin/env bash
set -Eeuo pipefail
umask 027
cd /www/wwwroot/learncambodia.com
exec 9>/var/backups/learncambodia/course-review-queue.lock
flock -n 9

repo=dombong2026/learncambodia
base=2a17c46476f827e03f17247136c0d9e3b50223e9
release=87d64173372e49a2014e5b7de647f3d734a2592e
pr=116
qa=44737308366
php=/www/server/php/85/bin/php
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1

test "$(gh api "repos/$repo/pulls/$pr" --jq .head.sha)" = "$release"
test "$(gh api "repos/$repo/actions/runs/$qa" --jq '[.head_sha, .conclusion] | join(" ")')" = "$release success"
test ! -e storage/framework/down
runuser -u www -- test -x resources/views/workspace/admin/partials

work="$(mktemp -d /tmp/learncambodia-course-dialog-XXXXXXXX)"
backup="$(mktemp -d /var/backups/learncambodia/course-review-dialog-XXXXXXXX)"
files=(
  public/css/admin-review-queue.css
  public/js/admin-review-queue.js
  resources/views/workspace/admin/partials/individual-course-review.blade.php
  resources/views/workspace/admin/partials/course-review-queue.blade.php
  resources/views/workspace/admin/reviews.blade.php
)
installed=()

cache_views() { runuser -u www -- "$php" artisan view:cache --no-interaction; }
cleanup() {
    local rc=$?
    trap - EXIT
    if ((rc != 0 && ${#installed[@]} != 0)); then
        set +e
        for file in "${installed[@]}"; do
            if [[ -f "$backup/$file" ]]; then
                cp -p -- "$backup/$file" "$file"
            else
                rm -f -- "$file"
            fi
        done
        cache_views
        printf '%s\n' COURSE_REVIEW_DIALOG_ROLLED_BACK >&2
    fi
    rm -r -- "$work"
    exit "$rc"
}
trap cleanup EXIT
trap 'printf "COURSE_REVIEW_DIALOG_STOPPED at line %s\n" "$LINENO" >&2' ERR

git init -q "$work/source"
git -C "$work/source" fetch -q --depth=1 "https://github.com/$repo.git" "$base" "$release"
printf '%s\n' "${files[@]}" | sort > "$work/expected"
git -C "$work/source" diff --name-only "$base" "$release" | sort > "$work/actual"
diff -u "$work/expected" "$work/actual"

for file in "${files[@]}"; do
    printf 'Checking %s\n' "$file"
    test ! -L "$file"
    mkdir -p -- "$work/next/${file%/*}" "$backup/${file%/*}"
    git -C "$work/source" show "$release:$file" > "$work/next/$file"

    if [[ -e "$file" ]]; then
        if ! cmp -s "$file" "$work/next/$file"; then
            expected="$(git -C "$work/source" rev-parse --verify "$base:$file")"
            test "$(git hash-object "$file")" = "$expected"
        fi
        cp -p -- "$file" "$backup/$file"
    else
        if git -C "$work/source" cat-file -e "$base:$file" 2>/dev/null; then
            printf 'A live file from the installed release is missing: %s\n' "$file" >&2
            exit 1
        fi
    fi
done

for file in "${files[@]}"; do
    if cmp -s "$file" "$work/next/$file"; then continue; fi
    test -d "${file%/*}"
    if [[ -e "$file" ]]; then cmp "$file" "$backup/$file"; fi
    install -o root -g www -m 0644 -- "$work/next/$file" "$file.release-${release:0:8}"
    mv -fT -- "$file.release-${release:0:8}" "$file"
    installed+=("$file")
done
cache_views

for file in public/css/admin-review-queue.css public/js/admin-review-queue.js; do
    curl -fsS --max-time 20 "https://learncambodia.com/${file#public/}?v=2" -o "$work/http"
    cmp "$file" "$work/http"
done
printf '%s\n' "$release" > "$backup/release.sha"
printf '%s\n' "COURSE_REVIEW_DIALOG=PASS" "BACKUP=$backup"
