#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com

repo=dombong2026/learncambodia
old=b291216b38ac808d81f3b444e984db7502d191c1
release="${1:?Pass the reviewed Email Verification commit SHA}"
[[ "$release" =~ ^[a-f0-9]{40}$ && "$release" != "$old" ]]
php=/www/server/php/85/bin/php
marker=storage/app/private/learncambodia-email-verification-release.txt
backup_root=/var/backups/learncambodia
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1

exec 9>"$backup_root/course-builder.lock"
flock -n 9
test ! -e storage/framework/down
test ! -L "$marker"
grep -Fxq "USER_INVITATIONS_SHA=b291216b38ac808d81f3b444e984db7502d191c1" storage/app/private/learncambodia-user-invitations-release.txt
if [[ -f "$marker" ]] && grep -Fxq "EMAIL_VERIFICATION_SHA=$release" "$marker"; then
    printf '%s\n' EMAIL_VERIFICATION_APPLY=PASS 'This release is already installed.'
    exit 0
fi
test ! -e "$marker"
test -x "$php"

quality="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/ci.yml")][0].id // empty')"
[[ "$quality" =~ ^[0-9]+$ ]] || {
    echo 'The pinned release must pass Foundation Quality.' >&2
    exit 1
}

stage="$(mktemp -d "$backup_root/email-verification-XXXXXXXX")"
printf 'BACKUP=%s\n' "$stage"
git init -q "$stage/source"
cd_source() { git -C "$stage/source" "$@"; }
cd_source fetch -q --depth=1 "https://github.com/$repo.git" "$old" "$release"

cd_source diff --name-only --no-renames "$old" "$release" -- app bootstrap database lang public resources routes > "$stage/files"
test -s "$stage/files"
while IFS= read -r file; do
    case "$file" in
        app/Support/OutboundMail.php|\
        app/Support/AccountInvitations.php|\
        app/Http/Controllers/Auth/EmailVerificationController.php|\
        resources/views/auth/confirm-email.blade.php|\
        lang/en/auth.php|\
        lang/km/auth.php|\
        lang/zh/auth.php) ;;
        *) printf 'Unexpected release file: %s\n' "$file" >&2; exit 1 ;;
    esac

    test ! -L "$file"
    directory="$(dirname -- "$file")"
    test "$(realpath -m "$directory")" = "$PWD/$directory"
    if cd_source cat-file -e "$old:$file" 2>/dev/null; then
        test -f "$file"
        mkdir -p "$stage/original/$directory" "$stage/source/$directory"
        cp -p -- "$file" "$stage/original/$file"
        cp -p -- "$file" "$stage/source/$file"
        cd_source add -f -- "$file"
    else
        test ! -e "$file"
    fi
done < "$stage/files"

mapfile -t files < "$stage/files"
cd_source diff --no-renames --binary --full-index "$old" "$release" -- "${files[@]}" > "$stage/release.patch"
if ! cd_source apply --3way --index "$stage/release.patch"; then
    printf 'EMAIL_VERIFICATION_PREFLIGHT=CONFLICT Backup: %s\n' "$stage" >&2
    exit 1
fi

while IFS= read -r file; do
    test -f "$stage/source/$file"
    if [[ "$file" == *.php && "$file" != *.blade.php ]]; then
        "$php" -l "$stage/source/$file" >/dev/null
    fi
    if [[ -f "$stage/original/$file" ]]; then
        cmp -s -- "$file" "$stage/original/$file"
    else
        test ! -e "$file"
    fi
done < "$stage/files"

artisan() { runuser -u www -- "$php" artisan "$@" --no-interaction; }
refresh_caches() {
    artisan view:clear && artisan view:cache
}

changed=0
rollback() {
    local code=$?
    trap - EXIT INT TERM
    if ((code)); then
        set +e
        if ((changed)); then
            while IFS= read -r file; do
                if [[ -f "$stage/original/$file" ]]; then
                    cp -p -- "$stage/original/$file" "$file" || echo "Restore failed: $file" >&2
                else
                    rm -f -- "$file"
                fi
            done < "$stage/files"
            rm -f -- "$marker"
            refresh_caches || echo 'Cache rebuild after rollback failed.' >&2
        fi
        artisan up
        printf 'EMAIL_VERIFICATION_APPLY=FAIL Backup: %s\n' "$stage" >&2
    fi
    exit "$code"
}
trap rollback EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
artisan down --retry=30
changed=1
while IFS= read -r file; do
    directory="$(dirname -- "$file")"
    file_mode=0640
    directory_mode=0750
    if [[ "$file" == public/* ]]; then
        file_mode=0644
        directory_mode=0755
    fi
    if [[ ! -d "$directory" ]]; then install -d -o root -g www -m "$directory_mode" "$directory"; fi
    swap="$(mktemp "$directory/.email-verification.XXXXXXXX")"
    install -o root -g www -m "$file_mode" "$stage/source/$file" "$swap"
    mv -f -- "$swap" "$file"
    runuser -u www -- test -r "$file"
done < "$stage/files"

refresh_caches
runuser -u www -- "$php" <<'PHP'
<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (!Route::has(['verification.notice', 'verification.send', 'verification.verify'])
    || !class_exists(\App\Support\OutboundMail::class)
    || !\Illuminate\Support\Facades\Lang::has('auth.verification_delivery_failed', 'en')) {
    fwrite(STDERR, "Email verification release check failed.\n");
    exit(1);
}
echo "EMAIL_VERIFICATION_ROUTES=PASS\n";
echo 'EMAIL_VERIFICATION_MAIL_TRANSPORT='.(\App\Support\OutboundMail::deliveryEnabled() ? 'enabled' : 'disabled')."\n";
PHP

printf 'EMAIL_VERIFICATION_SHA=%s\nBACKUP=%s\n' "$release" "$stage" > "$stage/marker"
install -o root -g www -m 0640 "$stage/marker" "$marker"
artisan up
test ! -e storage/framework/down
printf 'EMAIL_VERIFICATION_APPLY=PASS\nBACKUP=%s\n' "$stage"
