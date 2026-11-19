#!/usr/bin/env bash
# Install the Specialized Exam view and its standalone stylesheet.
set -Eeuo pipefail
umask 077

[[ "$(id -u)" = 0 ]]
release="${1:?Pass the reviewed release commit SHA}"
[[ "$release" =~ ^[a-f0-9]{40}$ ]]
base=e46a3724ba695faab1e5dda22cf2dd19f0e7a16b
repo=dombong2026/learncambodia
php=/www/server/php/85/bin/php
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
cd -P /www/wwwroot/learncambodia.com
test -x "$php"
test -f artisan

mkdir -p /var/backups/learncambodia
exec 9>/var/backups/learncambodia/course-builder.lock
flock -n 9
backup="$(mktemp -d /var/backups/learncambodia/specialized-exam-mobile.XXXXXXXX)"
echo "BACKUP=$backup"

css=public/css/specialized-exam.css
view=resources/views/learning/specialized-exam.blade.php
files=("$css" "$view")
changed=()
installed=()
swap=''
artisan() { runuser -u www -- "$php" artisan "$@" --no-interaction; }
put() {
    local source="$1" target="$2"
    swap="$(mktemp "$(dirname "$target")/.specialized-exam-mobile.XXXXXXXX")"
    cp -p -- "$source" "$swap"
    mv -fT -- "$swap" "$target"
    swap=''
}
finish() {
    local result=$? file rollback=0
    trap - EXIT INT TERM
    if (( result )); then
        set +e
        [[ -z "$swap" ]] || rm -f -- "$swap"
        for file in "${installed[@]}"; do
            put "$backup/original/$file" "$file" || rollback=1
        done
        if (( ${#installed[@]} )); then
            artisan view:cache || rollback=1
            echo "SPECIALIZED_EXAM_MOBILE_ROLLBACK_STATUS=$rollback"
        fi
        echo 'SPECIALIZED_EXAM_MOBILE_APPLY=FAIL' >&2
    fi
    exit "$result"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

for file in "${files[@]}"; do
    test -f "$file" && test ! -L "$file"
    test "$(realpath -- "$(dirname "$file")")" = "$PWD/$(dirname "$file")"
    original="$backup/original/$file"
    staged="$backup/staged/$file"
    mkdir -p -- "$(dirname "$original")" "$(dirname "$staged")"
    cp -p -- "$file" "$original"
    endpoint="repos/$repo/contents/$file"
    old_sha="$(gh api "$endpoint?ref=$base" --jq .sha)"
    new_sha="$(gh api "$endpoint?ref=$release" --jq .sha)"
    live_sha="$(git hash-object --no-filters "$original")"
    if [[ "$live_sha" != "$old_sha" && "$live_sha" != "$new_sha" ]]; then
        echo "Unrecognized live file: $file ($live_sha)" >&2
        exit 1
    fi
    gh api -H 'Accept: application/vnd.github.raw+json' "$endpoint?ref=$release" > "$staged"
    test "$(git hash-object --no-filters "$staged")" = "$new_sha"
    chown --reference="$original" "$staged"
    chmod --reference="$original" "$staged"
    if [[ "$live_sha" != "$new_sha" ]]; then changed+=("$file"); fi
done
grep -Fq 'class="specialized-exam-question"' "$backup/staged/$view"
grep -Fq "specialized-exam.css') }}?v=2" "$backup/staged/$view"
grep -Fq '.specialized-exam-question legend' "$backup/staged/$css"

for file in "${changed[@]}"; do
    cmp -s -- "$backup/original/$file" "$file"
    installed+=("$file")
    put "$backup/staged/$file" "$file"
done
artisan view:cache
curl --fail --silent --show-error --max-time 15 --noproxy '*' \
    --resolve 'learncambodia.com:443:127.0.0.1' \
    'https://learncambodia.com/css/specialized-exam.css?v=2' > "$backup/served.css"
cmp -s -- "$backup/staged/$css" "$backup/served.css"
echo 'SPECIALIZED_EXAM_MOBILE_APPLY=PASS'
