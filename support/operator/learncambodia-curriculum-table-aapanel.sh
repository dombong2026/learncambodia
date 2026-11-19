#!/usr/bin/env bash
# Install the compact curriculum table after the focused-learning release.
# Changes only the student curriculum view and legacy lesson-quiz redirect.
set -Eeuo pipefail
umask 077

test "$(id -u)" -eq 0
test "$#" -eq 1
release="$1"
[[ "$release" =~ ^[a-f0-9]{40}$ ]] || { echo 'ERROR=INVALID_RELEASE' >&2; exit 1; }
repo="dombong2026/learncambodia"
root="/www/wwwroot/learncambodia.com"
php="/www/server/php/85/bin/php"
study="resources/views/workspace/student/study.blade.php"
quiz="app/Http/Controllers/Workspace/StudentLessonQuizController.php"

command -v gh >/dev/null
command -v git >/dev/null
command -v runuser >/dev/null
test -x "$php"
cd "$root"
test -f artisan
test -f "$study" && test ! -L "$study"
test -f "$quiz" && test ! -L "$quiz"

old_study="1f50835fa699bd02890ae243b52d0d77b448c3ef"
new_study="7bf07ace4732130ee4b59a18da7c1fa8b615e7f4"
old_quiz="2724257071c2b2c24f87e094e689f44eb8a90ef9"
new_quiz="902bac37d505b85386916769b08e2a981098bfea"
current_study="$(git hash-object --no-filters "$study")"
current_quiz="$(git hash-object --no-filters "$quiz")"

if [[ "$current_study" == "$new_study" && "$current_quiz" == "$new_quiz" ]]; then
    echo 'CURRICULUM_TABLE_APPLY=ALREADY_APPLIED'
    exit 0
fi

if [[ "$current_study" != "$old_study" && "$current_study" != "$new_study" ]] ||
   [[ "$current_quiz" != "$old_quiz" && "$current_quiz" != "$new_quiz" ]]; then
    printf 'ERROR=UNEXPECTED_LIVE_SOURCE study=%s quiz=%s\n' "$current_study" "$current_quiz" >&2
    echo 'No changes made; inspect the live files before reconciling.' >&2
    exit 1
fi

temp="$(mktemp -d /root/learncambodia-curriculum-table.XXXXXXXX)"
backup="$(mktemp -d /var/backups/learncambodia/curriculum-table-XXXXXXXX)"
installed=0
maintenance=0
stage_study=""
stage_quiz=""

cleanup() {
    status="$?"
    trap - EXIT
    if [[ "$status" -ne 0 && "$installed" -eq 1 ]]; then
        echo 'CURRICULUM_TABLE_ROLLBACK=STARTED' >&2
        cp -p "$backup/$study" "$root/$study" || true
        cp -p "$backup/$quiz" "$root/$quiz" || true
        runuser -u www -- "$php" artisan view:clear || true
    fi
    if [[ "$maintenance" -eq 1 ]]; then
        runuser -u www -- "$php" artisan up || true
    fi
    [[ -z "$stage_study" ]] || rm -f -- "$stage_study"
    [[ -z "$stage_quiz" ]] || rm -f -- "$stage_quiz"
    rm -rf -- "$temp"
    exit "$status"
}
trap cleanup EXIT

mkdir -p "$backup/$(dirname "$study")" "$backup/$(dirname "$quiz")"
cp -p -- "$study" "$backup/$study"
cp -p -- "$quiz" "$backup/$quiz"
echo "BACKUP=$backup"

gh api -H 'Accept: application/vnd.github.raw+json' \
    "repos/$repo/contents/$study?ref=$release" > "$temp/study.blade.php"
gh api -H 'Accept: application/vnd.github.raw+json' \
    "repos/$repo/contents/$quiz?ref=$release" > "$temp/StudentLessonQuizController.php"
test "$(git hash-object --no-filters "$temp/study.blade.php")" = "$new_study"
test "$(git hash-object --no-filters "$temp/StudentLessonQuizController.php")" = "$new_quiz"
"$php" -l "$temp/StudentLessonQuizController.php"

stage_study="$(mktemp "$(dirname "$study")/.study-curriculum.XXXXXXXX")"
stage_quiz="$(mktemp "$(dirname "$quiz")/.quiz-curriculum.XXXXXXXX")"
cp -- "$temp/study.blade.php" "$stage_study"
cp -- "$temp/StudentLessonQuizController.php" "$stage_quiz"
chown --reference="$study" "$stage_study"
chown --reference="$quiz" "$stage_quiz"
chmod --reference="$study" "$stage_study"
chmod --reference="$quiz" "$stage_quiz"

runuser -u www -- "$php" artisan down
maintenance=1
installed=1
mv -f -- "$stage_study" "$study"
stage_study=""
mv -f -- "$stage_quiz" "$quiz"
stage_quiz=""
runuser -u www -- "$php" artisan view:clear
runuser -u www -- "$php" artisan view:cache
test "$(git hash-object --no-filters "$study")" = "$new_study"
test "$(git hash-object --no-filters "$quiz")" = "$new_quiz"
runuser -u www -- "$php" artisan up
maintenance=0

echo 'CURRICULUM_TABLE_APPLY=PASS'
echo "BACKUP=$backup"
