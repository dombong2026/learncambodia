#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com

repo=dombong2026/learncambodia
old=668f25cd5bcd8fc4188014be75f64a8b936b2103
release="${1:?Pass the reviewed User Manager commit SHA}"
[[ "$release" =~ ^[a-f0-9]{40}$ && "$release" != "$old" ]]
php=/www/server/php/85/bin/php
marker=storage/app/private/learncambodia-admin-user-manager-release.txt
backup_root=/var/backups/learncambodia
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1

exec 9>"$backup_root/course-builder.lock"
flock -n 9
test ! -e storage/framework/down
test ! -L "$marker"
grep -Fxq "LOCALIZED_SOCIAL_SHARING_SHA=ef8e00854e51bd4191e96ecb30106ea54c7d2fed" storage/app/private/learncambodia-localized-social-sharing-release.txt
if [[ -f "$marker" ]] && grep -Fxq "ADMIN_USER_MANAGER_SHA=$release" "$marker"; then
    printf '%s\n' ADMIN_USER_MANAGER_APPLY=PASS 'This release is already installed.'
    exit 0
fi
test ! -e "$marker"
test -x "$php"

quality="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/ci.yml")][0].id // empty')"
[[ "$quality" =~ ^[0-9]+$ ]] || {
    echo 'The pinned release must pass Foundation Quality.' >&2
    exit 1
}

stage="$(mktemp -d "$backup_root/admin-user-manager-XXXXXXXX")"
printf 'BACKUP=%s\n' "$stage"
git init -q "$stage/source"
cd_source() { git -C "$stage/source" "$@"; }
cd_source fetch -q --depth=1 "https://github.com/$repo.git" "$old" "$release"

cd_source diff --name-only --no-renames "$old" "$release" -- app database public resources routes > "$stage/files"
test -s "$stage/files"
while IFS= read -r file; do
    case "$file" in
        app/Console/Commands/AssignAccountRole.php|\
        app/Http/Controllers/Auth/AccountSecurityController.php|\
        app/Http/Controllers/Auth/GoogleSignInController.php|\
        app/Http/Controllers/EditorMediaController.php|\
        app/Http/Controllers/SupportTicketController.php|\
        app/Http/Controllers/Workspace/AdminRecordsController.php|\
        app/Http/Controllers/Workspace/AdminUserController.php|\
        app/Http/Controllers/Workspace/CertificateController.php|\
        app/Http/Controllers/Workspace/StudentExaminationController.php|\
        app/Http/Controllers/Workspace/WorkspaceController.php|\
        app/Http/Middleware/EnsureRole.php|\
        app/Models/User.php|\
        app/Support/AdminAccess.php|\
        app/Support/WorkspaceMenu.php|\
        app/Support/WorkspaceNotifications.php|\
        database/migrations/2026_11_17_000004_add_admin_permissions_and_access_audit.php|\
        public/js/admin-user-access.js|\
        resources/views/layouts/workspace.blade.php|\
        resources/views/partials/profile-menu-content.blade.php|\
        resources/views/workspace/admin/access-overview.blade.php|\
        resources/views/workspace/admin/categories.blade.php|\
        resources/views/workspace/admin/examination/types.blade.php|\
        resources/views/workspace/admin/instructors.blade.php|\
        resources/views/workspace/admin/records.blade.php|\
        resources/views/workspace/admin/user-form.blade.php|\
        resources/views/workspace/admin/user-record.blade.php|\
        routes/web.php) ;;
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
    printf 'ADMIN_USER_MANAGER_PREFLIGHT=CONFLICT Backup: %s\n' "$stage" >&2
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
        printf 'ADMIN_USER_MANAGER_APPLY=FAIL Backup: %s\n' "$stage" >&2
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
    swap="$(mktemp "$directory/.admin-user-manager.XXXXXXXX")"
    install -o root -g www -m "$file_mode" "$stage/source/$file" "$swap"
    mv -f -- "$swap" "$file"
    runuser -u www -- test -r "$file"
done < "$stage/files"

artisan migrate --force --path=database/migrations/2026_11_17_000004_add_admin_permissions_and_access_audit.php
refresh_caches
runuser -u www -- "$php" <<'PHP'
<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (!Route::has(['admin.users.create', 'admin.users.store', 'admin.users.access.update'])
    || !Schema::hasColumn('users', 'admin_permissions')
    || !Schema::hasColumns('account_role_changes', ['actor_user_id', 'previous_permissions', 'new_permissions'])
    || !is_readable(public_path('js/admin-user-access.js'))) {
    fwrite(STDERR, "User Manager routes, schema, or assets verification failed.\n");
    exit(1);
}
echo "ADMIN_USER_MANAGER_ROUTES_SCHEMA_ASSETS=PASS\n";
PHP

printf 'ADMIN_USER_MANAGER_SHA=%s\nBACKUP=%s\n' "$release" "$stage" > "$stage/marker"
install -o root -g www -m 0640 "$stage/marker" "$marker"
artisan up
test ! -e storage/framework/down
printf 'ADMIN_USER_MANAGER_APPLY=PASS\nBACKUP=%s\n' "$stage"
