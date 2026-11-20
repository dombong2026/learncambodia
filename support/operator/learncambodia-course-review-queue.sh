set -Eeuo pipefail
trap 'printf "REVIEW_RELEASE=STOPPED at line %s\n" "$LINENO" >&2' ERR
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

merge_review_route() {
    local live="$1" approved="$2" prior="$3" output="$4"
    python3 - "$live" "$approved" "$prior" "$output" <<'ROUTE_PY'
from pathlib import Path
import sys

live, approved, prior, output = map(Path, sys.argv[1:])
anchor = b"        Route::post('/reviews/courses/{course}', [AdminReviewController::class, 'course'])->name('reviews.course');\n"
addition = b"        Route::post('/reviews/courses/approve-selected', [AdminReviewController::class, 'approveCourses'])->name('reviews.courses.approve-selected');\n"
original = prior.read_bytes()
current = live.read_bytes()

if original.count(anchor) != 1 or original.replace(anchor, addition + anchor, 1) != approved.read_bytes():
    raise SystemExit("The reviewed route change is not the expected single addition.")
if current.count(anchor) != 1:
    raise SystemExit("The existing course review route does not match the expected declaration.")
if b"reviews.courses.approve-selected" in current or b"/reviews/courses/approve-selected" in current:
    if current.count(addition) != 1 or addition + anchor not in current:
        raise SystemExit("A conflicting bulk approval route already exists.")
    output.write_bytes(current)
else:
    output.write_bytes(current.replace(anchor, addition + anchor, 1))
ROUTE_PY
}

for file in "${files[@]}"; do
    printf 'Checking %s\n' "$file"
    test ! -L "$file"
    mkdir -p "$work/next/${file%/*}" "$backup/${file%/*}"
    git -C "$work/source" show "$release:$file" > "$work/approved"
    if [[ "$file" == routes/web.php ]]; then
        git -C "$work/source" show "$base:$file" > "$work/prior"
        merge_review_route "$file" "$work/approved" "$work/prior" "$work/next/$file"
        cp -p -- "$file" "$backup/$file"
    elif git -C "$work/source" cat-file -e "$base:$file" 2>/dev/null; then
        if [[ "$(git hash-object "$file")" != "$(git -C "$work/source" rev-parse "$base:$file")" ]] && ! cmp -s "$file" "$work/approved"; then
            printf 'Unexpected live changes in %s. No files installed.\n' "$file" >&2
            exit 1
        fi
        cp -p -- "$file" "$backup/$file"
        cp -- "$work/approved" "$work/next/$file"
    else
        if [[ -e "$file" ]]; then
            if ! cmp -s "$file" "$work/approved"; then
                printf 'Unexpected existing file %s. No files installed.\n' "$file" >&2
                exit 1
            fi
            cp -p -- "$file" "$backup/$file"
        fi
        cp -- "$work/approved" "$work/next/$file"
    fi
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

partial_dir=resources/views/workspace/admin/partials
test ! -L "$partial_dir"
if [[ ! -e "$partial_dir" ]]; then
    install -d -o root -g www -m 0750 -- "$partial_dir"
elif ! runuser -u www -- test -x "$partial_dir" || ! runuser -u www -- test -r "$partial_dir"; then
    # An earlier rolled-back run can leave its empty root-owned directory.
    test "$(stat -c '%u:%g:%a' "$partial_dir")" = '0:0:750'
    test -z "$(find "$partial_dir" -mindepth 1 -maxdepth 1 -print -quit)"
    chgrp www -- "$partial_dir"
fi
runuser -u www -- test -x "$partial_dir"
runuser -u www -- test -r "$partial_dir"

for file in "${files[@]}"; do
    if cmp -s "$file" "$work/next/$file"; then continue; fi
    install -o root -g www -m 0644 -- "$work/next/$file" "$file.release-${release:0:8}"
    mv -fT -- "$file.release-${release:0:8}" "$file"
    installed+=("$file")
done
refresh_caches
artisan route:list --name=admin.reviews.courses.approve-selected | grep -F 'reviews/courses/approve-selected'
for file in public/css/admin-review-queue.css public/js/admin-review-queue.js; do
    curl -fsS --max-time 20 "https://learncambodia.com/${file#public/}?v=1" -o "$work/http"
    cmp "$file" "$work/http"
done
printf '%s\n' "$release" > "$backup/release.sha"
printf '%s\n' "REVIEW_RELEASE=PASS" "BACKUP=$backup"
