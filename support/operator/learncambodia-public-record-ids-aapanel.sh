#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077
test "$(id -u)" -eq 0
cd /www/wwwroot/learncambodia.com
test ! -L public/build
test ! -L public/build/assets
test ! -L public/build/manifest.json

repo=dombong2026/learncambodia
old=356031241934e234ac9e5bae7d1a9ad499d1253c
release="${1:?Pass the reviewed public record IDs commit SHA}"
[[ "$release" =~ ^[a-f0-9]{40}$ ]]
php=/www/server/php/85/bin/php
marker=storage/app/private/learncambodia-public-record-ids-release.txt
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1
exec 9>/var/backups/learncambodia/course-builder.lock
flock -n 9
if [[ -f "$marker" ]] && grep -Fxq "PUBLIC_RECORD_IDS_SHA=$release" "$marker"; then
    test ! -e storage/framework/down
    echo PUBLIC_RECORD_IDS_APPLY=PASS
    printf '%s\n' 'This release is already installed.'
    exit 0
fi
test ! -e "$marker"
test ! -e storage/framework/down
grep -Fxq "SIDEBAR_CONTENT_APPEARANCE_SHA=$old" storage/app/private/learncambodia-sidebar-content-appearance-release.txt
test "$old" != "$release"
quality="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/ci.yml")][0].id // empty')"
vite="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/qa-vite-assets.yml")][0].id // empty')"
[[ "$quality" =~ ^[0-9]+$ && "$vite" =~ ^[0-9]+$ ]]
artifact="$(gh api "repos/$repo/actions/runs/$vite/artifacts" --jq '.artifacts[] | select(.name == "learncambodia-pinned-qa-vite-assets" and (.expired | not)) | [.id, .digest] | @tsv')"
IFS=$'\t' read -r artifact_id digest <<< "$artifact"
[[ "$artifact_id" =~ ^[0-9]+$ && "$digest" =~ ^sha256:[a-f0-9]{64}$ ]]

stage="$(mktemp -d /var/backups/learncambodia/public-record-ids-XXXXXXXX)"
git init -q "$stage/source"
git -C "$stage/source" fetch -q --depth=1 "https://github.com/$repo.git" "$old" "$release"
git -C "$stage/source" diff --name-only "$old" "$release" | awk '!/^(tests|support\/operator)\//' > "$stage/files"
test -s "$stage/files"
while IFS= read -r file; do
    [[ "$file" =~ ^(app/(Http/(Controllers/|Middleware/)|Models/|Notifications/|Support/|Console/Commands/)|resources/(views|css|js)/|database/migrations/2026_11_17_000041_add_public_record_ids\.php$|config/workspaces\.php$|routes/(web|api)\.php$) ]]
    test ! -L "$file"
    if prior="$(git -C "$stage/source" rev-parse --verify "$old:$file" 2>/dev/null)"; then
        if [[ "$(git hash-object "$file")" != "$prior" ]]; then
            printf 'Unexpected live file contents in %s. Backup workspace at %s.\n' "$file" "$stage" >&2
            exit 1
        fi
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
            if [[ -d "$stage/old-build" ]]; then
                if [[ -d public/build ]]; then mv public/build "$stage/failed-build"; fi
                mv "$stage/old-build" public/build
            fi
            rm -f -- "$marker"
            if ((config_cached)); then artisan config:cache; fi
            if ((route_cached)); then artisan route:cache; fi
            artisan view:clear
            artisan view:cache
        fi
        if ((maintenance)); then artisan up; fi
        echo "PUBLIC_RECORD_IDS_APPLY=FAILED Backup: $stage"
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
if ((config_cached)); then artisan config:cache; fi
if ((route_cached)); then artisan route:cache; fi
artisan migrate --force --path=database/migrations/2026_11_17_000041_add_public_record_ids.php
artisan view:clear
artisan view:cache
artisan route:list --json > "$stage/verified-routes.json"
for required in courses.show public.exams.show public.quizzes.show articles.show pages.show policies.show admin.users.show; do
    grep -Fq "\"$required\"" "$stage/verified-routes.json"
done
printf 'PUBLIC_RECORD_IDS_SHA=%s\nBACKUP=%s\n' "$release" "$stage" > "$stage/marker"
install -o root -g www -m 0640 "$stage/marker" "$marker"
artisan up
test ! -e storage/framework/down
echo PUBLIC_RECORD_IDS_APPLY=PASS
printf 'BACKUP=%s\n' "$stage"
