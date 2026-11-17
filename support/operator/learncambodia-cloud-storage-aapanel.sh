#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077
test "$(id -u)" -eq 0
cd /www/wwwroot/learncambodia.com
test ! -L vendor
test -d vendor

repo=dombong2026/learncambodia
old=75e74d0a81cdf045585b917530a80450012d529b
release="${1:?Pass the reviewed cloud storage commit SHA}"
[[ "$release" =~ ^[a-f0-9]{40}$ ]]
php=/www/server/php/85/bin/php
marker=storage/app/private/learncambodia-cloud-storage-release.txt
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1
exec 9>/var/backups/learncambodia/course-builder.lock
flock -n 9
if [[ -f "$marker" ]] && grep -Fxq "CLOUD_STORAGE_SHA=$release" "$marker"; then
    test ! -e storage/framework/down
    echo CLOUD_STORAGE_APPLY=PASS
    printf '%s\n' 'This release is already installed.'
    exit 0
fi
test ! -e "$marker"
test ! -e storage/framework/down
grep -Fxq "LEARNING_MANAGER_SHA=$old" storage/app/private/learncambodia-learning-manager-release.txt
test "$old" != "$release"

quality="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/ci.yml")][0].id // empty')"
[[ "$quality" =~ ^[0-9]+$ ]] || { echo 'The pinned release must pass Foundation Quality.' >&2; exit 1; }
bundle="$(gh api "repos/$repo/actions/runs/$quality/artifacts" --jq '
    [.artifacts[] | select(.name == "learncambodia-cloud-storage-vendor" and (.expired | not)) | [.id, .digest]]
    | if length == 1 then .[0] | @tsv else empty end
')"
IFS=$'\t' read -r bundle_id bundle_digest <<< "$bundle"
[[ "$bundle_id" =~ ^[0-9]+$ && "$bundle_digest" =~ ^sha256:[0-9a-f]{64}$ ]] || {
    echo 'The verified dependency artifact is unavailable for this commit.' >&2
    exit 1
}

stage="$(mktemp -d /var/backups/learncambodia/cloud-storage-XXXXXXXX)"
git init -q "$stage/source"
git -C "$stage/source" fetch -q --depth=1 "https://github.com/$repo.git" "$old" "$release"
git -C "$stage/source" diff --name-only "$old" "$release" | awk '!/^(tests|docs|support\/operator)\// && $0 != ".env.example" && $0 != ".github/workflows/ci.yml"' > "$stage/files"
test -s "$stage/files"
while IFS= read -r file; do
    [[ "$file" =~ ^(app/(Console/Commands/|Support/|Http/Controllers/)|config/filesystems\.php$|database/seeders/|composer\.(json|lock)$) ]]
    test ! -L "$file"
    directory="$(dirname -- "$file")"
    if prior="$(git -C "$stage/source" rev-parse --verify "$old:$file" 2>/dev/null)"; then
        if [[ "$(git hash-object "$file")" != "$prior" ]]; then
            printf 'Unexpected live file contents in %s. Backup workspace at %s.\n' "$file" "$stage" >&2
            exit 1
        fi
        mkdir -p "$stage/original/$directory"
        cp -p "$file" "$stage/original/$file"
    else
        test ! -e "$file"
    fi
    mkdir -p "$stage/next/$directory"
    git -C "$stage/source" show "$release:$file" > "$stage/next/$file"
    if [[ "$file" == *.php && "$file" != *.blade.php ]]; then "$php" -l "$stage/next/$file" >/dev/null; fi
done < "$stage/files"
gh api "repos/$repo/actions/artifacts/$bundle_id/zip" > "$stage/vendor.zip"
printf '%s  %s\n' "${bundle_digest#sha256:}" "$stage/vendor.zip" | sha256sum -c --status
unzip -p "$stage/vendor.zip" provenance.txt > "$stage/provenance.txt"
unzip -p "$stage/vendor.zip" vendor.tar.gz > "$stage/vendor.tar.gz"
grep -Fxq "SOURCE_COMMIT=$release" "$stage/provenance.txt"
lock_digest="$(sha256sum "$stage/next/composer.lock" | cut -d' ' -f1)"
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
"$php" -r 'require $argv[1]; exit(class_exists(\Aws\S3\S3Client::class) && class_exists(\Illuminate\Foundation\Application::class) ? 0 : 1);' "$stage/vendor/autoload.php"
mv "$stage/vendor" "$stage/next-vendor"
chown -R root:www "$stage/next-vendor"
chmod -R g+rX,o-rwx "$stage/next-vendor"

artisan() { runuser -u www -- "$php" artisan "$@" --no-interaction; }
changed=0
maintenance=0
route_cached=0
config_cached=0
if test -f bootstrap/cache/config.php; then config_cached=1; fi
if compgen -G "bootstrap/cache/routes-*.php" >/dev/null; then route_cached=1; fi
rollback() {
    local code=$?
    trap - EXIT
    if ((code)); then
        set +e
        if ((changed)); then
            while IFS= read -r file; do
                if [[ -f "$stage/original/$file" ]]; then
                    cp -p "$stage/original/$file" "$file"
                else
                    rm -f -- "$file"
                fi
            done < "$stage/files"
            if [[ -d "$stage/old-vendor" ]]; then
                if [[ -d vendor ]]; then mv vendor "$stage/failed-vendor"; fi
                mv "$stage/old-vendor" vendor
            fi
            rm -f -- "$marker"
            if ((config_cached)); then artisan config:cache; fi
            if ((route_cached)); then artisan route:cache; fi
            artisan view:clear
            artisan view:cache
        fi
        if ((maintenance)); then artisan up; fi
        echo "CLOUD_STORAGE_APPLY=FAILED Backup: $stage"
    fi
    exit "$code"
}
trap rollback EXIT
artisan down --retry=30
maintenance=1
changed=1
while IFS= read -r file; do
    directory="$(dirname -- "$file")"
    if [[ ! -d "$directory" ]]; then install -d -o root -g www -m 0750 "$directory"; fi
    swap="$(mktemp "$file.XXXXXXXX")"
    install -o root -g www -m 0640 "$stage/next/$file" "$swap"
    mv -f "$swap" "$file"
    runuser -u www -- test -r "$file"
done < "$stage/files"
mv vendor "$stage/old-vendor"
mv "$stage/next-vendor" vendor
if ((config_cached)); then artisan config:cache; fi
if ((route_cached)); then artisan route:cache; fi
artisan view:clear
artisan view:cache
artisan list --raw > "$stage/verified-commands.txt"
grep -q '^storage:managed-transfer ' "$stage/verified-commands.txt"
printf 'CLOUD_STORAGE_SHA=%s\nBACKUP=%s\n' "$release" "$stage" > "$stage/marker"
install -o root -g www -m 0640 "$stage/marker" "$marker"
artisan up
test ! -e storage/framework/down
echo CLOUD_STORAGE_APPLY=PASS
printf 'BACKUP=%s\n' "$stage"
