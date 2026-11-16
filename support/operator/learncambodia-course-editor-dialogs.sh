#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

repo=dombong2026/learncambodia
baseline=4ac3e655accd10e66bc75930bf0ebc526c3d4b29
release="${1:?Provide the release commit SHA}"
[[ "$release" =~ ^[0-9a-f]{40}$ ]]
test "$(id -u)" -eq 0
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
cd /www/wwwroot/learncambodia.com

exec 9>/var/backups/learncambodia/course-builder.lock
flock -n 9
grep -Fxq "COURSE_EDITORS_SHA=$baseline" storage/app/private/learncambodia-course-editors-release.txt
test ! -e storage/framework/down

marker=storage/app/private/learncambodia-course-editor-dialogs-release.txt
if test -f "$marker"; then
    grep -Fxq "COURSE_EDITOR_DIALOGS_SHA=$release" "$marker"
    echo COURSE_EDITOR_DIALOGS_APPLY=PASS
    exit 0
fi

workflow_id() {
    gh api "repos/$repo/actions/runs?head_sha=$release&status=success" \
        --jq "[.workflow_runs[] | select(.path == \".github/workflows/$1\")][0].id // empty"
}
quality="$(workflow_id ci.yml)"
vite="$(workflow_id qa-vite-assets.yml)"
[[ "$quality" =~ ^[0-9]+$ && "$vite" =~ ^[0-9]+$ ]]
artifact="$(gh api "repos/$repo/actions/runs/$vite/artifacts" --jq '.artifacts[] | select(.name == "learncambodia-pinned-qa-vite-assets" and (.expired | not)) | [.id, .digest] | @tsv')"
IFS=$'\t' read -r artifact_id digest <<< "$artifact"
[[ "$artifact_id" =~ ^[0-9]+$ && "$digest" =~ ^sha256:[a-f0-9]{64}$ ]]

stage="$(mktemp -d /var/backups/learncambodia/course-editor-dialogs-XXXXXXXX)"
css=resources/css/rich-editor.css
manifest=public/build/manifest.json
test ! -L "$css"
test ! -L "$manifest"
test ! -L public/build/assets
gh api -H 'Accept: application/vnd.github.raw+json' "repos/$repo/contents/$css?ref=$baseline" > "$stage/previous.css"
gh api -H 'Accept: application/vnd.github.raw+json' "repos/$repo/contents/$css?ref=$release" > "$stage/next.css"
cmp -s "$css" "$stage/previous.css"
cp -p "$manifest" "$stage/previous.manifest.json"

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

replace() {
    local temp
    temp="$(mktemp "$2.XXXXXXXX")"
    install -o root -g www -m 0640 "$1" "$temp"
    mv -f "$temp" "$2"
}
rollback() {
    local code=$?
    if ((code)); then
        set +e
        replace "$stage/previous.css" "$css"
        replace "$stage/previous.manifest.json" "$manifest"
        rm -f -- "$marker"
        echo "COURSE_EDITOR_DIALOGS_APPLY=FAILED Backup: $stage"
    fi
}
# Keep older hashed assets available for browsers already on the page.
rsync -rlpt --chown=root:www --chmod=D750,F640 "$stage/build/assets/" public/build/assets/
trap rollback EXIT
replace "$stage/next.css" "$css"
replace "$stage/build/manifest.json" "$manifest"
printf 'COURSE_EDITOR_DIALOGS_SHA=%s\nBACKUP=%s\n' "$release" "$stage" > "$stage/marker"
replace "$stage/marker" "$marker"
echo COURSE_EDITOR_DIALOGS_APPLY=PASS
