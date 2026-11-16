#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

repo=dombong2026/learncambodia
base=9a8c999f4148c5c39e841eba497904dafad538d6
previous_player=f286972797ad007470804ce01596210e99f5b34d
release="${1:?Pass the reviewed lesson media player commit SHA}"
[[ "$release" =~ ^[0-9a-f]{40}$ ]]
test "$(id -u)" -eq 0
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
cd /www/wwwroot/learncambodia.com
php=/www/server/php/85/bin/php
test -x "$php"

exec 9>/var/backups/learncambodia/course-builder.lock
flock -n 9
test ! -e storage/framework/down
grep -Fxq 'COURSE_EDITORS_SHA=4ac3e655accd10e66bc75930bf0ebc526c3d4b29' storage/app/private/learncambodia-course-editors-release.txt
manifest=public/build/manifest.json
files=(resources/js/app.ts resources/js/rich-editor.ts resources/js/lesson-media-player.ts resources/css/lesson-media-player.css)
marker=storage/app/private/learncambodia-lesson-media-player-release.txt
test -f "$manifest"
test ! -L "$manifest"
test ! -L public/build/assets

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

stage="$(mktemp -d /var/backups/learncambodia/lesson-media-player-XXXXXXXX)"
declare -a changed=()
manifest_changed=0
marker_changed=0
replace() {
    local temp
    temp="$(mktemp "$2.XXXXXXXX")"
    install -o root -g www -m 0640 "$1" "$temp"
    mv -fT "$temp" "$2"
}
rollback() {
    local code=$? file
    trap - EXIT
    if ((code)); then
        set +e
        if ((manifest_changed)); then replace "$stage/previous.manifest.json" "$manifest"; fi
        for file in "${changed[@]}"; do
            if [[ -f "$stage/previous/$file" ]]; then
                cp -aT "$stage/previous/$file" "$file"
            else
                rm -f "$file"
            fi
        done
        if ((marker_changed)); then
            if [[ -f "$stage/previous.marker" ]]; then cp -aT "$stage/previous.marker" "$marker"; else rm -f "$marker"; fi
        fi
        printf 'LESSON_MEDIA_PLAYER_APPLY=FAILED Backup: %s\n' "$stage" >&2
    fi
    exit "$code"
}
trap rollback EXIT

fetch() {
    gh api -H 'Accept: application/vnd.github.raw+json' "repos/$repo/contents/$2?ref=$1"
}
for file in "${files[@]}"; do
    test ! -L "$file"
    mkdir -p "$stage/previous/${file%/*}" "$stage/next/${file%/*}"
    fetch "$release" "$file" > "$stage/next/$file"
    test -s "$stage/next/$file"
    if [[ -e "$file" ]]; then
        test -f "$file"
        if ! cmp -s "$file" "$stage/next/$file"; then
            case "$file" in
                resources/js/app.ts|resources/js/rich-editor.ts) baseline="$base" ;;
                *) baseline="$previous_player" ;;
            esac
            fetch "$baseline" "$file" > "$stage/baseline"
            cmp -s "$file" "$stage/baseline"
        fi
        cp -a "$file" "$stage/previous/$file"
    fi
done
cp -p "$manifest" "$stage/previous.manifest.json"
if [[ -f "$marker" ]]; then cp -p "$marker" "$stage/previous.marker"; fi

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

# Resolve the pinned asset paths before writing anything to the live checkout.
asset_paths="$("$php" -r '
try {
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $workspace = $manifest["resources/js/workspace.ts"] ?? [];
    $app = $manifest["resources/js/app.ts"] ?? [];
    $players = array_filter($manifest, static fn ($entry) => ($entry["name"] ?? null) === "lesson-media-player");
    $playerKey = array_key_first($players);

    if (count($players) !== 1
        || !in_array($playerKey, $workspace["imports"] ?? [], true)
        || !in_array($playerKey, $app["imports"] ?? [], true)) {
        throw new RuntimeException("The lesson player is missing from a Vite entry point.");
    }

    $paths = [$workspace["file"] ?? null, $app["file"] ?? null, $players[$playerKey]["file"] ?? null];
    foreach ($paths as $path) {
        if (!is_string($path) || !preg_match("~^assets/[A-Za-z0-9_.-]+[.]js$~D", $path)) {
            throw new RuntimeException("The Vite manifest contains an invalid JavaScript asset path.");
        }
    }
    echo implode("\t", $paths), "\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Lesson media player manifest: ".$error->getMessage()."\n");
    exit(1);
}
' "$stage/build/manifest.json")"
IFS=$'\t' read -r workspace_asset app_asset player_asset <<< "$asset_paths"
for path in "$workspace_asset" "$app_asset" "$player_asset"; do
    test -s "$stage/build/$path"
done

rsync -rlpt --chown=root:www --chmod=D750,F640 "$stage/build/assets/" public/build/assets/
for file in "${files[@]}"; do
    if cmp -s "$file" "$stage/next/$file"; then continue; fi
    changed+=("$file")
    replace "$stage/next/$file" "$file"
done
if ! cmp -s "$stage/build/manifest.json" "$manifest"; then
    manifest_changed=1
    replace "$stage/build/manifest.json" "$manifest"
fi

# Verify the installed files as the web user before probing the origin.
runuser -u www -- test -r "$manifest"
cmp -s "$stage/build/manifest.json" "$manifest"
origin_authenticated=1
for path in "$workspace_asset" "$app_asset" "$player_asset"; do
    runuser -u www -- test -r "public/build/$path"
    cmp -s "$stage/build/$path" "public/build/$path"
    status="$(curl -sS --max-time 20 --noproxy '*' --resolve learncambodia.com:443:127.0.0.1 \
        -D "$stage/headers" -o "$stage/served" -w '%{http_code}' "https://learncambodia.com/build/$path")"
    case "$status" in
        200) cmp -s "public/build/$path" "$stage/served" ;;
        401)
            # The private installation challenges unauthenticated curl requests.
            # Leave Basic Auth enabled and report that only disk contents were verified.
            grep -Eiq '^www-authenticate:[[:blank:]]+Basic[[:blank:]]+realm="Learn Cambodia - Private Installation"[[:space:]]*$' "$stage/headers" ||
                { printf 'Unexpected HTTP 401 for %s\n' "$path" >&2; exit 1; }
            origin_authenticated=0
            ;;
        *) printf 'Unexpected HTTP %s for %s\n' "$status" "$path" >&2; exit 1 ;;
    esac
done
printf 'LESSON_MEDIA_PLAYER_FILES=PASS\n'
if ((origin_authenticated)); then
    printf 'LESSON_MEDIA_PLAYER_ORIGIN=PASS\n'
else
    printf 'LESSON_MEDIA_PLAYER_ORIGIN=AUTH_REQUIRED\n'
fi
printf 'LESSON_MEDIA_PLAYER_SHA=%s\nBACKUP=%s\n' "$release" "$stage" > "$stage/marker"
marker_changed=1
replace "$stage/marker" "$marker"
printf 'LESSON_MEDIA_PLAYER_APPLY=PASS\nBACKUP=%s\n' "$stage"
