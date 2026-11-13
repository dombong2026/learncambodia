#!/usr/bin/env bash
set -Eeuo pipefail
set +x
fail() { echo "RELEASE_SECURITY=FAIL: $*" >&2; exit 1; }
test -f artisan && test -f composer.lock && test -f package-lock.json || fail "required locked source missing"
while IFS= read -r path; do
 case "$path" in
   .env|.env.*) test "$path" = ".env.example" || fail "tracked private env file";;
   *.pem|*.p12|*.pfx|*.sql|*.sqlite|*.key|id_rsa|id_ed25519) fail "tracked key/database artifact";;
 esac
done < <(git ls-files)
grep -qx 'INSTALLER_ENABLED=false' .env.example || fail "installer default on"
grep -qx 'PUBLIC_STUDENT_PROFILES_ENABLED=false' .env.example || fail "public student directory on"
grep -qx 'MAIL_MAILER=log' .env.example || fail "outbound email enabled"
grep -Fq "'enabled' => (bool) env('INSTALLER_ENABLED', false)" config/installer.php || fail "installer configuration drift"
if grep -Eiq 'Route::(post|put|patch|delete)\(.*(khqr|checkout|payment|webhook)' routes/web.php routes/api.php; then fail "unreviewed payment endpoint"; fi
echo 'SOURCE_SECURITY_GATES=PASS'
