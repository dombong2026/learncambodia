#!/usr/bin/env bash
set -Eeuo pipefail
set +x
umask 077

[[ "$(id -u)" == 0 ]]
host=learncambodia.com
app="/www/wwwroot/$host"
conf="/www/server/panel/vhost/nginx/$host.conf"
nginx=/www/server/nginx/sbin/nginx
backups=/var/backups/learncambodia

[[ -f "$conf" && ! -L "$conf" && -x "$nginx" && -f "$app/public/index.php" ]]
exec 9>"$backups/course-builder.lock"
flock -n 9

backup="$(mktemp -d "$backups/open-public-site-XXXXXXXX")"
original="$backup/nginx.conf"
candidate="$backup/nginx.public.conf"
cp -p -- "$conf" "$original"
"$nginx" -t -q

python3 - "$original" "$candidate" "$app" <<'PY'
import re
import sys
from pathlib import Path

original, candidate = map(Path, sys.argv[1:3])
app = sys.argv[3]
source = original.read_text()
realm = r"""(?m)^[ \t]*auth_basic[ \t]+(?:"Learn Cambodia - Private Installation"|'Learn Cambodia - Private Installation')[ \t]*;[ \t]*(?:#[^\n]*)?\n"""
users_file = re.escape(app + "/.private-nginx-auth/learncambodia.htpasswd")
users = rf"""(?m)^[ \t]*auth_basic_user_file[ \t]+(?:{users_file}|"{users_file}"|'{users_file}')[ \t]*;[ \t]*(?:#[^\n]*)?\n"""
root = re.escape(app + "/public")
if not re.search(rf"""(?m)^[ \t]*root[ \t]+(?:{root}|"{root}"|'{root}')[ \t]*;""", source):
    sys.exit("Stopped before editing: the vhost does not declare the expected Laravel public root.")
updated, realm_count = re.subn(realm, "", source)
updated, user_count = re.subn(users, "", updated)
remaining = re.findall(r"(?m)^[ \t]*(auth_basic(?:_user_file)?)[ \t]+([^;\n]+);", updated)
if (realm_count, user_count) not in {(0, 0), (1, 1)} or any(
    name != "auth_basic" or value.strip() != "off" for name, value in remaining
):
    sys.exit("Stopped before editing: the vhost contains an unexpected Basic Auth configuration.")
candidate.write_text(updated)
PY

scratch="$(mktemp -d)"
changed=0
replace_conf() {
    local temp
    temp="$(mktemp "$(dirname "$conf")/.learncambodia-open.XXXXXXXX")" || return
    if ! cp -p -- "$original" "$temp" || ! cat -- "$1" > "$temp" || ! mv -f -- "$temp" "$conf"; then
        rm -f -- "$temp"
        return 1
    fi
}

cleanup() {
    local status=$?
    trap - EXIT INT TERM
    if ((status != 0 && changed)); then
        if replace_conf "$original" && "$nginx" -t -q && "$nginx" -s reload; then
            printf 'Original vhost restored and reloaded. Backup: %s\n' "$backup" >&2
        else
            printf 'Automatic rollback failed. Original vhost: %s\n' "$original" >&2
        fi
    fi
    rm -f -- "$scratch/headers" "$scratch/body"
    rmdir -- "$scratch"
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

origin() {
    curl --silent --show-error --max-time 15 --noproxy '*' \
        --resolve "$host:443:127.0.0.1" \
        -D "$scratch/headers" -o "$scratch/body" -w '%{http_code}' "$@"
}

if ! cmp -s -- "$original" "$candidate"; then
    cmp -s -- "$conf" "$original"
    replace_conf "$candidate"
    changed=1
    "$nginx" -t -q
    "$nginx" -s reload
fi

for attempt in 1 2 3 4 5; do
    status="$(origin "https://$host/")"
    if [[ "$status" == 200 ]]; then break; fi
    sleep 1
done
[[ "$status" == 200 ]] || { echo "Homepage returned HTTP $status." >&2; exit 1; }
grep -Eqi 'Learn[[:space:]]*Cambodia' "$scratch/body"
for page in privacy terms; do
    status="$(origin --location --max-redirs 5 --proto-redir '=https' "https://$host/information/$page?lang=en")"
    [[ "$status" == 200 ]] || { echo "$page policy returned HTTP $status." >&2; exit 1; }
    case "$page" in
        privacy) grep -Fqi 'Privacy Policy' "$scratch/body" ;;
        terms) grep -Fqi 'Terms of Service' "$scratch/body" ;;
    esac
done

status="$(origin "https://$host/dashboard?lang=en")"
[[ "$status" =~ ^30[12378]$ ]] && grep -Eiq "^location:[[:blank:]]*(https://${host//./\\.})?/login([?[:space:]]|$)" "$scratch/headers" ||
    { echo "Guest dashboard failed the sign-in redirect check (HTTP $status)." >&2; exit 1; }

for page in .env install; do
    status="$(origin "https://$host/$page")"
    [[ "$status" == 403 || "$status" == 404 ]] ||
        { echo "/$page returned unexpected HTTP $status." >&2; exit 1; }
done

printf 'LEARN_CAMBODIA_PUBLIC=PASS\nBACKUP=%s\n' "$backup"
