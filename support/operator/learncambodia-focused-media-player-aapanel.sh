#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com

repo=dombong2026/learncambodia
base=cdaa64b10a1b2ab00a7862235d6db051674c22cc
release="${1:?Pass the reviewed focused-media-player commit SHA}"
[[ "$release" =~ ^[a-f0-9]{40}$ && "$release" != "$base" ]]

php=/www/server/php/85/bin/php
backup_root=/var/backups/learncambodia
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
export GIT_CONFIG_GLOBAL=/root/learncambodia-github-auth/gitconfig
export GIT_CONFIG_NOSYSTEM=1

exec 9>"$backup_root/course-builder.lock"
flock -n 9
test ! -e storage/framework/down
test -x "$php"
test -f resources/views/layouts/focused-learning.blade.php

workflow_id() {
    gh api "repos/$repo/actions/runs?head_sha=$release&status=success" \
        --jq "[.workflow_runs[] | select(.path == \".github/workflows/$1\")][0].id // empty"
}
quality="$(workflow_id ci.yml)"
vite="$(workflow_id qa-vite-assets.yml)"
[[ "$quality" =~ ^[0-9]+$ && "$vite" =~ ^[0-9]+$ ]] ||
    { echo 'The pinned release needs successful Foundation Quality and Vite builds.' >&2; exit 1; }
artifact="$(gh api "repos/$repo/actions/runs/$vite/artifacts" --jq '.artifacts[] | select(.name == "learncambodia-pinned-qa-vite-assets" and (.expired | not)) | [.id, .digest] | @tsv')"
IFS=$'\t' read -r artifact_id digest <<< "$artifact"
[[ "$artifact_id" =~ ^[0-9]+$ && "$digest" =~ ^sha256:[a-f0-9]{64}$ ]]

files=(
    public/css/focused-learning.css
    resources/views/layouts/focused-learning.blade.php
    resources/views/learning/lesson.blade.php
    resources/views/learning/partials/viewer.blade.php
)
stage="$(mktemp -d "$backup_root/focused-media-player-XXXXXXXX")"
printf 'BACKUP=%s\n' "$stage"
git init -q "$stage/source"
source_git() { git -C "$stage/source" "$@"; }
source_git fetch -q --depth=1 "https://github.com/$repo.git" "$base" "$release"
source_git diff --name-only --no-renames "$base" "$release" -- public resources/views > "$stage/release-files"
printf '%s\n' "${files[@]}" > "$stage/expected-files"
cmp -- "$stage/release-files" "$stage/expected-files"

changes=()
for file in "${files[@]}"; do
    directory="$(dirname -- "$file")"
    test ! -L "$file"
    test -f "$file"
    test "$(realpath -m "$directory")" = "$PWD/$directory"
    source_git show "$release:$file" > "$stage/target"
    if cmp -s -- "$file" "$stage/target"; then continue; fi

    mkdir -p -- "$stage/original/$directory" "$stage/source/$directory"
    cp -p -- "$file" "$stage/original/$file"
    cp -p -- "$file" "$stage/source/$file"
    source_git add -f -- "$file"
    source_git diff --no-renames --binary --full-index "$base" "$release" -- "$file" > "$stage/release.patch"
    source_git apply --3way --index "$stage/release.patch"

    changes+=("$file")
done

gh api "repos/$repo/actions/artifacts/$artifact_id/zip" > "$stage/vite.zip"
printf '%s  %s\n' "${digest#sha256:}" "$stage/vite.zip" | sha256sum -c --status
unzip -p "$stage/vite.zip" provenance.txt > "$stage/provenance"
grep -Fxq "SOURCE_COMMIT=$release" "$stage/provenance"
hash="$(sed -n 's/^NPM_LOCK_SHA256=//p' "$stage/provenance")"
[[ "$hash" =~ ^[0-9a-f]{64}$ ]]
test "$(source_git show "$release:package-lock.json" | sha256sum | cut -d' ' -f1)" = "$hash"
unzip -p "$stage/vite.zip" learncambodia-vite-build.tar.gz > "$stage/build.tgz"
printf '%s  %s\n' "$(sed -n 's/^ASSET_ARCHIVE_SHA256=//p' "$stage/provenance")" "$stage/build.tgz" | sha256sum -c --status

# Extract the entry's dependency closure for its own Vite manifest.
python3 - "$stage/build.tgz" "$stage/build" <<'PYTHON'
import json
import re
import sys
import tarfile
from pathlib import Path

entry = "resources/js/focused-media.ts"
target = Path(sys.argv[2])
target.mkdir()
(target / "assets").mkdir()
with tarfile.open(sys.argv[1], "r:gz") as archive:
    def read(name):
        member = archive.getmember("build/" + name)
        if not member.isfile():
            raise ValueError(f"Invalid asset: {name}")
        with archive.extractfile(member) as file:
            return file.read()

    (target / "third-party-licenses.md").write_bytes(read("third-party-licenses.md"))
    manifest = json.loads(read("manifest.json"))
    if manifest[entry].get("isEntry") is not True:
        raise ValueError("Missing focused media entry point")
    pending = [entry]
    selected = {}
    paths = set()
    while pending:
        key = pending.pop()
        if key in selected:
            continue
        chunk = manifest[key]
        selected[key] = chunk
        pending.extend(chunk.get("imports", []))
        pending.extend(chunk.get("dynamicImports", []))
        paths.update([chunk["file"], *chunk.get("css", []), *chunk.get("assets", [])])
    for path in sorted(paths):
        if not re.fullmatch(r"assets/[A-Za-z0-9_.-]+", path):
            raise ValueError(f"Invalid asset path: {path}")
        (target / path).write_bytes(read(path))
    (target / "manifest.json").write_text(json.dumps(selected, indent=2) + "\n", encoding="utf-8")
PYTHON

build=public/build-focused-media
test "$(realpath -m public)" = "$PWD/public"
test ! -L "$build"
test ! -e "$build" || test -d "$build"
if ((${#changes[@]} == 0)) && diff -qr "$stage/build" "$build" >/dev/null 2>&1; then
    printf '%s\n' 'FOCUSED_MEDIA_PLAYER_APPLY=PASS' 'This update is already installed.'
    exit 0
fi

artisan() { runuser -u www -- "$php" artisan "$@" --no-interaction; }
refresh_views() { artisan view:clear && artisan view:cache; }
maintenance=0
build_replaced=0
new_build=
installed=()
finish() {
    local status=$?
    trap - EXIT INT TERM
    if ((status)); then
        set +e
        if ((build_replaced)); then
            if [[ -d "$build" ]]; then mv -- "$build" "$stage/failed-build"; fi
            if [[ -d "$stage/original-build" ]]; then mv -- "$stage/original-build" "$build"; fi
        fi
        for file in "${installed[@]}"; do
            cp -p -- "$stage/original/$file" "$file" || printf 'Restore failed: %s\n' "$file" >&2
        done
        if ((${#installed[@]})); then refresh_views || echo 'View cache restore failed.' >&2; fi
        if ((maintenance)); then artisan up; fi
        printf 'FOCUSED_MEDIA_PLAYER_APPLY=FAIL Backup: %s\n' "$stage" >&2
    fi
    if [[ -n "$new_build" && -d "$new_build" ]]; then rm -rf -- "$new_build"; fi
    exit "$status"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

new_build="$(mktemp -d "$PWD/public/.focused-media.XXXXXXXX")"
install -d -o root -g www -m 0755 "$new_build" "$new_build/assets"
install -o root -g www -m 0644 "$stage/build/manifest.json" "$stage/build/third-party-licenses.md" "$new_build/"
install -o root -g www -m 0644 "$stage"/build/assets/* "$new_build/assets/"
diff -qr "$stage/build" "$new_build"
maintenance=1
artisan down --retry=30
if [[ -d "$build" ]]; then mv -- "$build" "$stage/original-build"; fi
build_replaced=1
mv -- "$new_build" "$build"
runuser -u www -- test -r "$build/manifest.json"
for file in "${changes[@]}"; do
    cmp -s -- "$stage/original/$file" "$file"
    swap="$(mktemp "$(dirname -- "$file")/.focused-media-player.XXXXXXXX")"
    mode=0640
    if [[ "$file" == public/* ]]; then mode=0644; fi
    install -o root -g www -m "$mode" "$stage/source/$file" "$swap"
    installed+=("$file")
    mv -f -- "$swap" "$file"
    runuser -u www -- test -r "$file"
done
refresh_views
artisan up
maintenance=0
test ! -e storage/framework/down
printf 'FOCUSED_MEDIA_PLAYER_APPLY=PASS\nBACKUP=%s\n' "$stage"
