#!/usr/bin/env bash
# Install automatic Course, Exam, and Quiz certificate awards.
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com

repo=dombong2026/learncambodia
base=5fdbda5b5a7112e79d1ebad469aca9e46f6956da
release="${1:?Pass the reviewed certificate awards commit SHA}"
[[ "$release" =~ ^[a-f0-9]{40}$ && "$release" != "$base" ]]

php=/www/server/php/85/bin/php
backup_root=/var/backups/learncambodia
migration=database/migrations/2026_11_19_000003_enable_automatic_certificate_awards.php
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1

exec 9>"$backup_root/course-builder.lock"
flock -n 9
test ! -e storage/framework/down
test -x "$php"
test -f artisan

files=(
    app/Http/Controllers/CertificateVerificationController.php
    app/Http/Controllers/Workspace/AdminExaminationController.php
    app/Http/Controllers/Workspace/CertificateController.php
    app/Http/Controllers/Workspace/StudentEnrollmentController.php
    app/Http/Controllers/Workspace/StudentExaminationController.php
    app/Http/Controllers/Workspace/StudentPracticeController.php
    app/Http/Controllers/Workspace/StudentProgressController.php
    app/Models/CourseExam.php
    app/Models/IssuedCertificate.php
    app/Support/CertificateAwards.php
    app/Support/WorkspaceMenu.php
    config/workspaces.php
    "$migration"
    lang/en/certificate_verification.php
    lang/km/certificate_verification.php
    lang/km/workspace.php
    lang/zh/certificate_verification.php
    lang/zh/workspace.php
    public/js/certificate-designer.js
    resources/views/components/certificate-award.blade.php
    resources/views/components/certificate-paper.blade.php
    resources/views/workspace/admin/examination/builder.blade.php
    resources/views/workspace/admin/examination/certificate-builder.blade.php
    resources/views/workspace/admin/examination/issued.blade.php
    resources/views/workspace/admin/examination/types.blade.php
    resources/views/workspace/student/certificates.blade.php
    resources/views/workspace/student/course-examination.blade.php
    resources/views/workspace/student/exam-attempt.blade.php
    resources/views/workspace/student/practice-result.blade.php
    resources/views/workspace/student/practice-show.blade.php
    resources/views/workspace/student/progress.blade.php
    routes/web.php
)

stage="$(mktemp -d "$backup_root/certificate-awards-XXXXXXXX")"
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
config_cached=0
if [[ -f bootstrap/cache/config.php ]]; then config_cached=1; fi
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
            if ((config_cached)); then artisan config:cache; fi
            if ((route_cached)); then artisan route:cache; fi
            refresh_views || echo 'View cache restore failed.' >&2
        fi
        if ((maintenance)); then artisan up; fi
        printf 'CERTIFICATE_AWARDS_APPLY=FAIL\nBACKUP=%s\n' "$stage" >&2
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
    swap="$(mktemp "$(dirname -- "$file")/.certificate-awards.XXXXXXXX")"
    install -o root -g www -m 0640 "$stage/source/$file" "$swap"
    installed+=("$file")
    mv -f -- "$swap" "$file"
    swap=''
    runuser -u www -- test -r "$file"
done

if ((config_cached)); then artisan config:cache; fi
if ((route_cached)); then artisan route:cache; fi
refresh_views
artisan route:list --name=account.certificates --json > "$stage/award-routes.json"
for name in index claim-course claim-assessment claim-practice; do
    grep -Fq "\"account.certificates.$name\"" "$stage/award-routes.json"
done
for file in "${changes[@]}"; do
    cmp -s -- "$stage/source/$file" "$file"
done
artisan migrate --force --path="$migration"
artisan up
maintenance=0
test ! -e storage/framework/down
printf 'CERTIFICATE_AWARDS_APPLY=PASS\nBACKUP=%s\n' "$stage"
