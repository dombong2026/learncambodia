#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com

repo=dombong2026/learncambodia
old=07381b57b0d4bc7f91e2a40fc822486b47baa393
release="${1:?Pass the reviewed System Settings commit SHA}"
[[ "$release" =~ ^[a-f0-9]{40}$ && "$release" != "$old" ]]
php=/www/server/php/85/bin/php
marker=storage/app/private/learncambodia-system-settings-integrations-release.txt
previous=storage/app/private/learncambodia-system-settings-release.txt
migration=database/migrations/2026_11_17_000001_add_system_settings_integrations.php
backup_root=/var/backups/learncambodia
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1

exec 9>"$backup_root/course-builder.lock"
flock -n 9
test ! -e storage/framework/down
test ! -L "$marker"
grep -Fxq "SYSTEM_SETTINGS_SHA=$old" "$previous"
test -d vendor && test ! -L vendor
if [[ -f "$marker" ]] && grep -Fxq "SETTINGS_INTEGRATIONS_SHA=$release" "$marker"; then
    printf '%s\n' SETTINGS_INTEGRATIONS_APPLY=PASS 'This release is already installed.'
    exit 0
fi
test ! -e "$marker"
test -x "$php"

quality="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/ci.yml")][0].id // empty')"
[[ "$quality" =~ ^[0-9]+$ ]] || {
    echo 'The pinned release must pass Foundation Quality.' >&2
    exit 1
}

bundle="$(gh api "repos/$repo/actions/runs/$quality/artifacts" --jq '
    [.artifacts[] | select(.name == "learncambodia-cloud-storage-vendor" and (.expired | not)) | [.id, .digest]]
    | if length == 1 then .[0] | @tsv else empty end
')"
IFS=$'\t' read -r bundle_id bundle_digest <<< "$bundle"
[[ "$bundle_id" =~ ^[0-9]+$ && "$bundle_digest" =~ ^sha256:[0-9a-f]{64}$ ]] || {
    echo 'The verified dependency artifact is unavailable for this commit.' >&2
    exit 1
}


stage="$(mktemp -d "$backup_root/settings-integrations-XXXXXXXX")"
printf 'BACKUP=%s\n' "$stage"
git init -q "$stage/source"
cd_source() { git -C "$stage/source" "$@"; }
cd_source fetch -q --depth=1 "https://github.com/$repo.git" "$old" "$release"

cd_source diff --name-only --no-renames "$old" "$release" -- app config database public resources routes composer.json composer.lock > "$stage/files"
test -s "$stage/files"
while IFS= read -r file; do
    case "$file" in
        resources/views/components/workspace-icon.blade.php|resources/views/layouts/workspace.blade.php|resources/views/workspace/admin/system-settings.blade.php|public/css/system-settings.css|app/Support/ManagedFiles.php|config/filesystems.php|app/Console/Commands/TransferManagedStorage.php|app/Jobs/RunManagedStorageTransfer.php|app/Http/Controllers/Workspace/AdminSystemSettingsController.php|app/Models/User.php|app/Providers/AppServiceProvider.php|config/fortify.php|app/Support/AccountSignIn.php|app/Http/Controllers/Auth/SessionController.php|app/Http/Controllers/Auth/AccountSecurityController.php|app/Http/Controllers/Auth/AccountPasskeyController.php|app/Support/GoogleSignIn.php|app/Http/Controllers/Auth/GoogleSignInController.php|routes/web.php|config/services.php|config/queue.php|database/migrations/2026_11_17_000001_add_system_settings_integrations.php|resources/views/workspace/admin/storage-transfers.blade.php|resources/views/workspace/security-methods.blade.php|resources/views/workspace/google-method.blade.php|resources/views/workspace/account-security.blade.php|resources/views/workspace/profile.blade.php|public/js/account-passkeys.js|resources/views/auth/security-challenge.blade.php|resources/views/auth/confirm-security-password.blade.php|resources/views/auth/google-registration.blade.php|resources/views/auth/login.blade.php|resources/views/auth/register.blade.php|resources/views/layouts/auth.blade.php|app/Http/Middleware/SecurityHeaders.php|composer.json|composer.lock) ;;
        *) printf 'Unexpected release file: %s\n' "$file" >&2; exit 1 ;;
    esac

    test ! -L "$file"
    directory="$(dirname -- "$file")"
    if [[ "$directory" != . ]]; then
        test "$(realpath -m "$directory")" = "$PWD/$directory"
    fi
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
    printf 'SETTINGS_INTEGRATIONS_PREFLIGHT=CONFLICT Backup: %s\n' "$stage" >&2
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

gh api "repos/$repo/actions/artifacts/$bundle_id/zip" > "$stage/vendor.zip"
printf '%s  %s\n' "${bundle_digest#sha256:}" "$stage/vendor.zip" | sha256sum -c --status
unzip -p "$stage/vendor.zip" provenance.txt > "$stage/provenance.txt"
unzip -p "$stage/vendor.zip" vendor.tar.gz > "$stage/vendor.tar.gz"
grep -Fxq "SOURCE_COMMIT=$release" "$stage/provenance.txt"
lock_digest="$(sha256sum "$stage/source/composer.lock" | cut -d' ' -f1)"
grep -Fxq "COMPOSER_LOCK_SHA256=$lock_digest" "$stage/provenance.txt"
archive_digest="$(sha256sum "$stage/vendor.tar.gz" | cut -d' ' -f1)"
grep -Fxq "VENDOR_ARCHIVE_SHA256=$archive_digest" "$stage/provenance.txt"

python3 - "$stage/vendor.tar.gz" <<'PYTHON'
import sys
import tarfile
from pathlib import PurePosixPath

with tarfile.open(sys.argv[1], "r:gz") as archive:
    members = set()
    for member in archive:
        path = PurePosixPath(member.name)
        if (
            not path.parts or path.parts[0] != "vendor" or ".." in path.parts
            or not (member.isdir() or member.isfile()) or path in members
        ):
            sys.exit("Unsafe path or member type in the dependency archive")
        members.add(path)
    if not {PurePosixPath("vendor/autoload.php"), PurePosixPath("vendor/composer/platform_check.php")} <= members:
        sys.exit("The dependency archive is missing its autoloader or platform check")
PYTHON

tar --no-same-owner --no-same-permissions -xzf "$stage/vendor.tar.gz" -C "$stage"
test -s "$stage/vendor/autoload.php"
test -s "$stage/vendor/composer/platform_check.php"
"$php" -r 'require $argv[1]; exit(class_exists(\Aws\S3\S3Client::class) && class_exists(\Laravel\Fortify\Fortify::class) && class_exists(\Laravel\Passkeys\Passkeys::class) ? 0 : 1);' "$stage/vendor/autoload.php"
mv "$stage/vendor" "$stage/next-vendor"
chown -R root:www "$stage/next-vendor"
chmod -R g+rX,o-rwx "$stage/next-vendor"


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
            if [[ -d "$stage/old-vendor" ]]; then
                if [[ -d vendor ]]; then mv vendor "$stage/failed-vendor"; fi
                mv "$stage/old-vendor" vendor
                rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
            fi
            rm -f -- "$marker"
            refresh_caches || echo 'Cache rebuild after rollback failed.' >&2
        fi
        artisan up
        printf 'SETTINGS_INTEGRATIONS_APPLY=FAIL Backup: %s\n' "$stage" >&2
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
    swap="$(mktemp "$directory/.system-settings.XXXXXXXX")"
    install -o root -g www -m "$file_mode" "$stage/source/$file" "$swap"
    mv -f -- "$swap" "$file"
    runuser -u www -- test -r "$file"
done < "$stage/files"

mv vendor "$stage/old-vendor"
mv "$stage/next-vendor" vendor
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
artisan package:discover
if ((config_cached)); then artisan config:cache; fi
artisan migrate --force --path="$migration"
refresh_caches
runuser -u www -- "$php" <<'PHP'
<?php

use App\Support\WorkspaceMenu;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$groups = WorkspaceMenu::groups('admin');
$keys = array_column($groups, 'key');
$position = array_search('reporting', $keys, true);
$settings = $position === false ? null : ($groups[$position + 1] ?? null);
$expected = ['branding', 'security', 'social-auth', 'cloud-storage'];
if (($settings['key'] ?? null) !== 'system-settings'
    || array_column($settings['items'], 'key') !== $expected
    || !Route::has(['admin.system-settings.cloud-storage.probe', 'admin.system-settings.cloud-storage.transfer', 'social.google.callback', 'passkey.store', 'security.challenge'])
    || !Schema::hasTable('managed_storage_transfers') || !Schema::hasTable('passkeys')
    || !Schema::hasColumns('users', ['google_subject', 'two_factor_secret', 'two_factor_confirmed_at'])
    || !is_readable(public_path('css/system-settings.css'))
    || !is_readable(public_path('js/account-passkeys.js'))) {
    fwrite(STDERR, "System Settings route, schema or asset verification failed.\n");
    exit(1);
}
echo "SETTINGS_INTEGRATIONS_ROUTES_SCHEMA=PASS\n";
PHP

printf 'SETTINGS_INTEGRATIONS_SHA=%s\nBACKUP=%s\n' "$release" "$stage" > "$stage/marker"
install -o root -g www -m 0640 "$stage/marker" "$marker"
artisan up
test ! -e storage/framework/down
printf 'SETTINGS_INTEGRATIONS_APPLY=PASS\nBACKUP=%s\n' "$stage"
