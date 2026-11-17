#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com

repo=dombong2026/learncambodia
old=1ac5b86700fd662821a2f1c30dbc815a304ec7f6
release="${1:?Pass the reviewed User Lifecycle commit SHA}"
[[ "$release" =~ ^[a-f0-9]{40}$ && "$release" != "$old" ]]
php=/www/server/php/85/bin/php
marker=storage/app/private/learncambodia-user-account-lifecycle-release.txt
backup_root=/var/backups/learncambodia
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1

exec 9>"$backup_root/course-builder.lock"
flock -n 9
test ! -e storage/framework/down
test ! -L "$marker"
grep -Fxq "ADMIN_USER_MANAGER_SHA=1ac5b86700fd662821a2f1c30dbc815a304ec7f6" storage/app/private/learncambodia-admin-user-manager-release.txt
if [[ -f "$marker" ]] && grep -Fxq "USER_ACCOUNT_LIFECYCLE_SHA=$release" "$marker"; then
    printf '%s\n' USER_ACCOUNT_LIFECYCLE_APPLY=PASS 'This release is already installed.'
    exit 0
fi
test ! -e "$marker"
test -x "$php"

quality="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/ci.yml")][0].id // empty')"
[[ "$quality" =~ ^[0-9]+$ ]] || {
    echo 'The pinned release must pass Foundation Quality.' >&2
    exit 1
}

stage="$(mktemp -d "$backup_root/user-account-lifecycle-XXXXXXXX")"
printf 'BACKUP=%s\n' "$stage"
git init -q "$stage/source"
cd_source() { git -C "$stage/source" "$@"; }
cd_source fetch -q --depth=1 "https://github.com/$repo.git" "$old" "$release"

cd_source diff --name-only --no-renames "$old" "$release" -- app bootstrap database public resources routes > "$stage/files"
test -s "$stage/files"
while IFS= read -r file; do
    case "$file" in
        database/migrations/2026_11_17_000005_add_managed_account_lifecycle.php|\
        app/Models/User.php|\
        app/Providers/AppServiceProvider.php|\
        app/Support/AccountSignIn.php|\
        app/Http/Controllers/Auth/SessionController.php|\
        app/Http/Middleware/EnsureDeveloperToken.php|\
        bootstrap/app.php|\
        app/Support/ManagedAccount.php|\
        app/Support/AccountPreview.php|\
        app/Http/Middleware/EnforceAccountSession.php|\
        app/Http/Controllers/Workspace/AdminUserController.php|\
        app/Http/Controllers/Workspace/AdminUserActionsController.php|\
        routes/web.php|\
        app/Http/Controllers/Workspace/AdminRecordsController.php|\
        resources/views/workspace/admin/records.blade.php|\
        resources/views/workspace/admin/user-row-actions.blade.php|\
        resources/views/workspace/admin/user-form.blade.php|\
        resources/views/workspace/admin/user-record.blade.php|\
        resources/views/workspace/admin/user-action.blade.php|\
        resources/views/partials/account-preview.blade.php|\
        resources/views/layouts/workspace.blade.php|\
        resources/views/layouts/public.blade.php) ;;
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
    printf 'USER_ACCOUNT_LIFECYCLE_PREFLIGHT=CONFLICT Backup: %s\n' "$stage" >&2
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
config_cached=0
route_cached=0
if [[ -f bootstrap/cache/config.php ]]; then config_cached=1; fi
if compgen -G 'bootstrap/cache/routes-*.php' >/dev/null; then route_cached=1; fi

refresh_caches() {
    if ((config_cached)); then artisan config:cache || return; fi
    if ((route_cached)); then artisan route:cache || return; fi
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
        printf 'USER_ACCOUNT_LIFECYCLE_APPLY=FAIL Backup: %s\n' "$stage" >&2
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
    swap="$(mktemp "$directory/.user-account-lifecycle.XXXXXXXX")"
    install -o root -g www -m "$file_mode" "$stage/source/$file" "$swap"
    mv -f -- "$swap" "$file"
    runuser -u www -- test -r "$file"
done < "$stage/files"

artisan migrate --force --path=database/migrations/2026_11_17_000005_add_managed_account_lifecycle.php
refresh_caches
runuser -u www -- "$php" <<'PHP'
<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (!Route::has(['admin.users.verify', 'admin.users.status', 'admin.users.sign-in-as', 'account-preview.stop'])
    || !Schema::hasColumns('users', ['account_status', 'account_auth_version', 'phone', 'phone_verified_at'])
    || !Schema::hasTable('user_account_events')) {
    fwrite(STDERR, "User account lifecycle routes or schema verification failed.\n");
    exit(1);
}
echo "USER_ACCOUNT_LIFECYCLE_ROUTES_SCHEMA=PASS\n";
PHP

printf 'USER_ACCOUNT_LIFECYCLE_SHA=%s\nBACKUP=%s\n' "$release" "$stage" > "$stage/marker"
install -o root -g www -m 0640 "$stage/marker" "$marker"
artisan up
test ! -e storage/framework/down
printf 'USER_ACCOUNT_LIFECYCLE_APPLY=PASS\nBACKUP=%s\n' "$stage"
