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

# aaPanel puts a shell wrapper on PATH that invokes PHP 8.3. Run the
# actual Composer entry point with the same PHP as the application.
composer="${COMPOSER_PHP_ENTRYPOINT:-/usr/bin/composer}"
composer_php() {
    COMPOSER_ALLOW_SUPERUSER=1 "$php" -d display_errors=stderr -d 'include_path=/usr/share/php:.' "$composer" "$@"
}
if [[ ! -r "$composer" ]] || ! version="$(composer_php --version --no-ansi)" || [[ "$version" != 'Composer version 2.'* ]]; then
    printf '%s\n' 'Set COMPOSER_PHP_ENTRYPOINT to a Composer 2 PHP script or PHAR readable by the site PHP CLI.' >&2
    exit 1
fi

quality="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/ci.yml")][0].id // empty')"
[[ "$quality" =~ ^[0-9]+$ ]]

stage="$(mktemp -d /var/backups/learncambodia/cloud-storage-XXXXXXXX)"
git init -q "$stage/source"
git -C "$stage/source" fetch -q --depth=1 "https://github.com/$repo.git" "$old" "$release"
git -C "$stage/source" diff --name-only "$old" "$release" | awk '!/^(tests|docs|support\/operator)\// && $0 != ".env.example"' > "$stage/files"
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
git -C "$stage/source" checkout -q --detach "$release"
composer_php --working-dir="$stage/source" install \
    --no-dev --prefer-dist --no-plugins --no-scripts --no-interaction --no-progress
composer_php --working-dir="$stage/source" check-platform-reqs --no-dev --no-plugins --no-interaction
test -s "$stage/source/vendor/autoload.php"
"$php" -r 'require $argv[1]; exit(class_exists(\Aws\S3\S3Client::class) ? 0 : 1);' "$stage/source/vendor/autoload.php"
mv "$stage/source/vendor" "$stage/next-vendor"
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
