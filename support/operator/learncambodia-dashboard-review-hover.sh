#!/usr/bin/env bash
set -Eeuo pipefail
umask 027
cd /www/wwwroot/learncambodia.com
exec 9>/var/backups/learncambodia/course-review-queue.lock
flock -n 9

repo=dombong2026/learncambodia
base=87d64173372e49a2014e5b7de647f3d734a2592e
release=05468540d0ba64c7d21e1087e0f2032c7caa0068
pr=117
qa=44743399348
php=/www/server/php/85/bin/php
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1

test "$(gh api "repos/$repo/pulls/$pr" --jq .head.sha)" = "$release"
test "$(gh api "repos/$repo/actions/runs/$qa" --jq '[.head_sha, .conclusion] | join(" ")')" = "$release success"
test ! -e storage/framework/down

work="$(mktemp -d /tmp/learncambodia-approval-hover-XXXXXXXX)"
backup="$(mktemp -d /var/backups/learncambodia/dashboard-review-hover-XXXXXXXX)"
files=(
  public/css/platform-focus.css
  app/Support/DashboardInsights.php
  app/Support/DashboardShowcase.php
  resources/views/components/dashboard-showcase.blade.php
  resources/views/workspace/overview.blade.php
  resources/views/partials/brand-head.blade.php
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
        printf '%s\n' DASHBOARD_REVIEW_HOVER_ROLLED_BACK >&2
    fi
    rm -r -- "$work"
    exit "$rc"
}
trap cleanup EXIT
trap 'printf "DASHBOARD_REVIEW_HOVER_STOPPED at line %s\n" "$LINENO" >&2' ERR

git init -q "$work/source"
git -C "$work/source" fetch -q --depth=1 "https://github.com/$repo.git" "$base" "$release"
printf '%s\n' "${files[@]}" tests/Feature/PremiumDashboardTest.php | sort > "$work/expected"
git -C "$work/source" diff --name-only "$base" "$release" | sort > "$work/actual"
diff -u "$work/expected" "$work/actual"

for file in "${files[@]}"; do
    printf 'Checking %s\n' "$file"
    test ! -L "$file"
    mkdir -p -- "$work/next/${file%/*}" "$backup/${file%/*}"
    git -C "$work/source" show "$release:$file" > "$work/next/$file"
    if [[ "$file" == app/*.php ]]; then "$php" -l "$work/next/$file"; fi

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

curl -fsS --max-time 20 'https://learncambodia.com/css/platform-focus.css?v=2' -o "$work/http"
cmp public/css/platform-focus.css "$work/http"
printf '%s\n' "$release" > "$backup/release.sha"
printf '%s\n' "DASHBOARD_REVIEW_HOVER=PASS" "BACKUP=$backup"
