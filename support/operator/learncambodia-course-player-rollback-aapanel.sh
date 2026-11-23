#!/usr/bin/env bash
# Restore a verified Learn Cambodia Course Player backup after deployment.
# Requires the exact backup directory displayed by the release script.
set -Eeuo pipefail
set +x
umask 077

if (( $# != 1 )); then
  echo 'Usage: rollback-script /root/learncambodia-course-hero-backups/TIMESTAMP-COMMIT' >&2
  exit 2
fi
BACKUP="$1"
if [[ ! "$BACKUP" =~ ^/root/learncambodia-course-hero-backups/[0-9]{8}T[0-9]{6}Z-[a-f0-9]{40}$ ]]; then
  echo 'STOP: Invalid backup directory.' >&2
  exit 1
fi
SITE=/www/wwwroot/learncambodia.com
test -f "$SITE/artisan"
test -f "$BACKUP/manifest.txt"

# Refuse to overwrite any file changed after the Course Player release.
while read -r old new path; do
  [[ -n "$path" ]] || continue
  if [[ "$old" == '-' ]]; then
    [[ -f "$SITE/$path" && "$(git hash-object "$SITE/$path")" == "$new" ]] || {
      echo "STOP: new release file differs or is absent: $path" >&2; exit 1;
    }
  else
    [[ -f "$BACKUP/$path" && "$(git hash-object "$BACKUP/$path")" == "$old" ]] || {
      echo "STOP: backup integrity failed: $path" >&2; exit 1;
    }
    [[ -f "$SITE/$path" && "$(git hash-object "$SITE/$path")" == "$new" ]] || {
      echo "STOP: deployed file changed since release: $path" >&2; exit 1;
    }
  fi
done < "$BACKUP/manifest.txt"

echo '=== Restoring previous application source ==='
while read -r old new path; do
  [[ -n "$path" ]] || continue
  if [[ "$old" == '-' ]]; then
    rm -f -- "$SITE/$path"
  else
    target="$SITE/$path"
    temp="$(mktemp "$target.XXXXXXXX")"
    cp -p -- "$BACKUP/$path" "$temp"
    mv -f -- "$temp" "$target"
  fi
  echo "RESTORED: $path"
done < "$BACKUP/manifest.txt"
(cd "$SITE" && php artisan view:clear >/dev/null && php artisan view:cache >/dev/null)
echo 'ROLLBACK COMPLETED. Review the public homepage and Home Appearance page.'
