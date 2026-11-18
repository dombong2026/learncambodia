#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077

test "$(id -u)" -eq 0
cd -P /www/wwwroot/learncambodia.com
repo=dombong2026/learncambodia
base=668f25cd5bcd8fc4188014be75f64a8b936b2103
release="${1:?Pass the reviewed public policy layout commit SHA}"
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

quality="$(gh api "repos/$repo/actions/runs?head_sha=$release&status=success" --jq '[.workflow_runs[] | select(.path == ".github/workflows/ci.yml")][0].id // empty')"
[[ "$quality" =~ ^[0-9]+$ ]] || {
    echo 'The pinned release must pass Foundation Quality.' >&2
    exit 1
}

files=(
    app/Http/Controllers/PublicDocumentController.php
    public/css/public-policies.css
    resources/views/information/document.blade.php
    resources/views/information/policies.blade.php
    resources/views/layouts/public.blade.php
)
stage="$(mktemp -d "$backup_root/public-policies-XXXXXXXX")"
printf 'BACKUP=%s\n' "$stage"
git init -q "$stage/source"
source_git() { git -C "$stage/source" "$@"; }
source_git fetch -q --depth=1 "https://github.com/$repo.git" "$base" "$release"
source_git diff --name-only --no-renames "$base" "$release" -- app public resources > "$stage/release-files"
printf '%s\n' "${files[@]}" > "$stage/expected-files"
cmp -- "$stage/release-files" "$stage/expected-files"

fully_installed=1
for file in "${files[@]}"; do
    test ! -L "$file"
    directory="$(dirname -- "$file")"
    test "$(realpath -m "$directory")" = "$PWD/$directory"
    source_git show "$release:$file" > "$stage/expected"
    if [[ ! -f "$file" ]] || ! cmp -s -- "$file" "$stage/expected"; then
        # The live layout can contain unrelated markup absent from the base branch.
        if [[ "$file" != resources/views/layouts/public.blade.php ]] ||
            ! grep -Fq "@stack('public_styles')" "$file"; then
            fully_installed=0
        fi
    fi
done
if ((fully_installed)); then
    printf '%s\n' 'PUBLIC_POLICIES_APPLY=PASS' 'This release is already installed.'
    exit 0
fi

for file in "${files[@]}"; do
    directory="$(dirname -- "$file")"
    if source_git cat-file -e "$base:$file" 2>/dev/null; then
        test -f "$file"
        mkdir -p -- "$stage/original/$directory" "$stage/source/$directory"
        cp -p -- "$file" "$stage/original/$file"
        cp -p -- "$file" "$stage/source/$file"
        source_git add -f -- "$file"
    else
        test ! -e "$file"
    fi
done
source_git diff --no-renames --binary --full-index "$base" "$release" -- "${files[@]}" > "$stage/release.patch"
source_git apply --3way --index "$stage/release.patch"
"$php" -l "$stage/source/app/Http/Controllers/PublicDocumentController.php"
for file in "${files[@]}"; do
    test -f "$stage/source/$file"
    if [[ -f "$stage/original/$file" ]]; then
        cmp -s -- "$file" "$stage/original/$file"
    else
        test ! -e "$file"
    fi
done

artisan() { runuser -u www -- "$php" artisan "$@" --no-interaction; }
refresh_views() { artisan view:clear && artisan view:cache; }
maintenance=0
changed=0
rollback() {
    local status=$?
    trap - EXIT INT TERM
    if ((status)); then
        set +e
        if ((changed)); then
            for file in "${files[@]}"; do
                if [[ -f "$stage/original/$file" ]]; then
                    cp -p -- "$stage/original/$file" "$file" || printf 'Restore failed: %s\n' "$file" >&2
                else
                    rm -f -- "$file"
                fi
            done
            refresh_views || echo 'View cache rebuild after rollback failed.' >&2
        fi
        if ((maintenance)); then artisan up; fi
        printf 'PUBLIC_POLICIES_APPLY=FAIL Backup: %s\n' "$stage" >&2
    fi
    exit "$status"
}
trap rollback EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

maintenance=1
artisan down --retry=30
changed=1
for file in "${files[@]}"; do
    directory="$(dirname -- "$file")"
    mode=0640
    if [[ "$file" == public/* ]]; then
        mode=0644
        if [[ ! -d "$directory" ]]; then install -d -o root -g www -m 0755 "$directory"; fi
    fi
    swap="$(mktemp "$directory/.public-policies.XXXXXXXX")"
    install -o root -g www -m "$mode" "$stage/source/$file" "$swap"
    mv -f -- "$swap" "$file"
    runuser -u www -- test -r "$file"
done
refresh_views
routes="$(artisan route:list --path=policies --json)"
"$php" -r '
    $routes = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $names = array_column($routes, "name");
    exit(in_array("policies.index", $names, true) && in_array("policies.show", $names, true) ? 0 : 1);
' <<<"$routes"
artisan up
test ! -e storage/framework/down
printf 'PUBLIC_POLICIES_APPLY=PASS\nBACKUP=%s\n' "$stage"
