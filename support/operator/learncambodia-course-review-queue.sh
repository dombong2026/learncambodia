set -Eeuo pipefail
umask 027
cd /www/wwwroot/learncambodia.com
exec 9>/var/backups/learncambodia/course-review-queue.lock
flock -n 9

repo=dombong2026/learncambodia
base=741e98074c39486c29bbb95460caf070ad4d485f
release=2a17c46476f827e03f17247136c0d9e3b50223e9
qa=44724506639
php=/www/server/php/85/bin/php
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1

test "$(gh api "repos/$repo/pulls/115" --jq .head.sha)" = "$release"
test "$(gh api "repos/$repo/actions/runs/$qa" --jq '[.head_sha, .conclusion] | join(" ")')" = "$release success"
test ! -e storage/framework/down

work="$(mktemp -d /tmp/learncambodia-review-XXXXXXXX)"
backup="$(mktemp -d /var/backups/learncambodia/course-review-queue-XXXXXXXX)"
git init -q "$work/source"
git -C "$work/source" fetch -q --depth=1 "https://github.com/$repo.git" "$base" "$release"
test "$(git -C "$work/source" rev-parse "$release")" = "$release"
files=(
  public/css/admin-review-queue.css
  public/js/admin-review-queue.js
  resources/views/workspace/admin/partials/course-review-queue.blade.php
  app/Http/Controllers/Workspace/AdminReviewController.php
  routes/web.php
  resources/views/workspace/admin/reviews.blade.php
)
printf '%s\n' "${files[@]}" tests/Feature/BulkCourseReviewTest.php | sort > "$work/expected"
git -C "$work/source" diff --name-only "$base" "$release" | sort > "$work/actual"
diff -u "$work/expected" "$work/actual"

for file in "${files[@]}"; do
    test ! -L "$file"
    mkdir -p "$work/next/${file%/*}" "$backup/${file%/*}"
    if git -C "$work/source" cat-file -e "$base:$file" 2>/dev/null; then
        test "$(git hash-object "$file")" = "$(git -C "$work/source" rev-parse "$base:$file")"
        cp -p -- "$file" "$backup/$file"
    else
        test ! -e "$file"
    fi
    git -C "$work/source" show "$release:$file" > "$work/next/$file"
    if [[ "$file" == *.php && "$file" != *.blade.php ]]; then "$php" -l "$work/next/$file"; fi
done

artisan() { runuser -u www -- "$php" artisan "$@" --no-interaction; }
route_cached=0
if [[ -f bootstrap/cache/routes-v7.php ]]; then route_cached=1; fi
refresh_caches() {
    if ((route_cached)); then artisan route:cache || return; fi
    artisan view:cache
}
installed=()
cleanup() {
    rc=$?
    trap - EXIT
    if ((rc != 0 && ${#installed[@]} != 0)); then
        set +e
        for file in "${installed[@]}"; do
            if [[ -f "$backup/$file" ]]; then cp -p -- "$backup/$file" "$file"; else rm -f -- "$file"; fi
        done
        refresh_caches
        printf '%s\n' REVIEW_RELEASE_ROLLED_BACK
    fi
    rm -r -- "$work"
    exit "$rc"
}
trap cleanup EXIT

for file in "${files[@]}"; do
    mkdir -p -- "${file%/*}"
    install -o root -g www -m 0644 -- "$work/next/$file" "$file.release-${release:0:8}"
    mv -fT -- "$file.release-${release:0:8}" "$file"
    installed+=("$file")
done
refresh_caches
artisan route:list --name=admin.reviews.courses.approve-selected | rg 'reviews/courses/approve-selected'
for file in public/css/admin-review-queue.css public/js/admin-review-queue.js; do
    curl -fsS --max-time 20 "https://learncambodia.com/${file#public/}?v=1" -o "$work/http"
    cmp "$file" "$work/http"
done
printf '%s\n' "$release" > "$backup/release.sha"
printf '%s\n' "REVIEW_RELEASE=PASS" "BACKUP=$backup"
