#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077
test "$(id -u)" -eq 0
cd /www/wwwroot/learncambodia.com

repo=dombong2026/learncambodia
old=cde3551c62a82dda46e4099d9bd263dbad496668
release="${1:?Pass the reviewed PR 50 commit SHA}"
author="${2:?Pass the existing administrator user ID}"
[[ "$release" =~ ^[a-f0-9]{40}$ && "$author" =~ ^[1-9][0-9]*$ ]]
php=/www/server/php/85/bin/php
migration=database/migrations/2026_11_16_000032_add_course_featured_media.php
marker=storage/app/private/learncambodia-pr50-release.txt
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1
exec 9>/var/backups/learncambodia/starter-courses.lock
flock -n 9
if [[ -f "$marker" ]] && grep -Fxq "PR50_SHA=$release" "$marker"; then
    printf '%s\n' 'This release is already installed.'
    exit 0
fi
test ! -e "$marker"
test ! -e storage/framework/down
grep -Fxq "PR49_SHA=$old" storage/app/private/learncambodia-pr49-release.txt
test "$(gh api "repos/$repo/pulls/50" --jq .head.sha)" = "$release"
quality="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/ci.yml")][0].id // empty')"
vite="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/qa-vite-assets.yml")][0].id // empty')"
[[ "$quality" =~ ^[0-9]+$ && "$vite" =~ ^[0-9]+$ ]]
artifact="$(gh api "repos/$repo/actions/runs/$vite/artifacts" --jq '.artifacts[] | select(.name == "learncambodia-pinned-qa-vite-assets" and (.expired | not)) | [.id, .digest] | @tsv')"
IFS=$'\t' read -r artifact_id digest <<< "$artifact"
[[ "$artifact_id" =~ ^[0-9]+$ && "$digest" =~ ^sha256:[a-f0-9]{64}$ ]]

stage="$(mktemp -d /var/backups/learncambodia/starter-courses-XXXXXXXX)"
git init -q "$stage/source"
git -C "$stage/source" fetch -q --depth=1 "https://github.com/$repo.git" "$old" "$release"
git -C "$stage/source" diff --name-only "$old" "$release" | awk '!/^(tests|support\/operator)\//' > "$stage/files"
while IFS= read -r file; do
    [[ "$file" =~ ^(app/|database/|resources/views/|routes/console\.php$|support/editorial/course-starter/) ]]
    test ! -L "$file"
    if prior="$(git -C "$stage/source" rev-parse --verify "$old:$file" 2>/dev/null)"; then
        test "$(git hash-object "$file")" = "$prior"
        mkdir -p "$stage/original/${file%/*}"
        cp -p "$file" "$stage/original/$file"
    else
        test ! -e "$file"
    fi
    mkdir -p "$stage/next/${file%/*}"
    git -C "$stage/source" show "$release:$file" > "$stage/next/$file"
    if [[ "$file" == *.php && "$file" != *.blade.php ]]; then "$php" -l "$stage/next/$file" >/dev/null; fi
done < "$stage/files"
gh api "repos/$repo/actions/artifacts/$artifact_id/zip" > "$stage/vite.zip"
printf '%s  %s\n' "${digest#sha256:}" "$stage/vite.zip" | sha256sum -c --status
unzip -p "$stage/vite.zip" provenance.txt > "$stage/provenance"
grep -Fxq "SOURCE_COMMIT=$release" "$stage/provenance"
for item in COMPOSER_LOCK_SHA256:composer.lock NPM_LOCK_SHA256:package-lock.json; do
    hash="$(sed -n "s/^${item%:*}=//p" "$stage/provenance")"
    printf '%s  %s\n' "$hash" "${item#*:}" | sha256sum -c --status
done
unzip -p "$stage/vite.zip" learncambodia-vite-build.tar.gz > "$stage/build.tgz"
printf '%s  %s\n' "$(sed -n 's/^ASSET_ARCHIVE_SHA256=//p' "$stage/provenance")" "$stage/build.tgz" | sha256sum -c --status
tar -tzf "$stage/build.tgz" | awk '$0 !~ /^build\/(assets\/([A-Za-z0-9_.-]+)?)?$/ && $0 != "build/manifest.json" { exit 1 }'
tar -xzf "$stage/build.tgz" -C "$stage"
test -s "$stage/build/manifest.json"
cp -a public/build "$stage/next-build"
rsync -rlpt --chown=root:www --chmod=D750,F640 "$stage/build/" "$stage/next-build/"

cat > "$stage/db-backup.php" <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql') exit(1);
$config = $db->getConfig();
$arguments = [
    'mysqldump', '--single-transaction', '--skip-lock-tables', '--no-tablespaces',
    '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'],
    '--databases', $config['database'],
];
if (!empty($config['unix_socket'])) $arguments[] = '--socket='.$config['unix_socket'];
$process = proc_open($arguments, [0=>['file','/dev/null','r'], 1=>STDOUT, 2=>STDERR], $pipes, null, ['MYSQL_PWD' => (string) $config['password']]);
exit(is_resource($process) ? proc_close($process) : 1);
PHP
"$php" "$stage/db-backup.php" | gzip -1 > "$stage/before.sql.gz"
gzip -t "$stage/before.sql.gz"
test "$(stat -c%s "$stage/before.sql.gz")" -gt 300

artisan() { runuser -u www -- "$php" artisan "$@" --no-interaction; }
changed=0
maintenance=0
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
            if [[ -d "$stage/old-build" ]]; then
                if [[ -d public/build ]]; then mv public/build "$stage/failed-build"; fi
                mv "$stage/old-build" public/build
            fi
            rm -f -- "$marker"
            artisan view:clear
            artisan view:cache
        fi
        if ((maintenance)); then artisan up; fi
        echo "STARTER_COURSES_APPLY=FAILED Backup: $stage"
    fi
    exit "$code"
}
trap rollback EXIT
artisan down --retry=30
maintenance=1
changed=1
while IFS= read -r file; do
    if [[ ! -d "${file%/*}" ]]; then install -d -o root -g www -m 0750 "${file%/*}"; fi
    swap="$(mktemp "$file.XXXXXXXX")"
    install -o root -g www -m 0640 "$stage/next/$file" "$swap"
    mv -f "$swap" "$file"
    runuser -u www -- test -r "$file"
done < "$stage/files"
mv public/build "$stage/old-build"
mv "$stage/next-build" public/build
artisan migrate --path="$migration" --force
artisan view:clear
artisan view:cache
printf 'PR50_SHA=%s\nBACKUP=%s\n' "$release" "$stage" > "$stage/marker"
install -o root -g www -m 0640 "$stage/marker" "$marker"
artisan courses:import-starter "$author"
artisan up
test ! -e storage/framework/down
echo STARTER_COURSES_APPLY=PASS
