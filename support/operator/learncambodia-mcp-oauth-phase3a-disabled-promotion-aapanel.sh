#!/usr/bin/env bash
# Phase 3A: disabled-first code-only overlay. No DB writes, key generation,
# registration, .env edits, dependency installation or route changes.
set -Eeuo pipefail
set +x
umask 077
[[ $# == 2 && "$1" =~ ^[a-f0-9]{40}$ && ( "$2" == --prepare || "$2" == --apply ) ]]
test "$(id -u)" = 0
RELEASE="$1"
MODE="$2"
BASE=e7416ea12cc64a427818c6f9da159b89ea22eaef
BRANCH=feature/mcp-oauth-production-promotion-20261125
REPO=dombong2026/learncambodia
SITE=/www/wwwroot/learncambodia.com
PHP=/www/server/php/85/bin/php
FPM=/etc/init.d/php-fpm-85
BACKUPS=/var/backups/learncambodia
ORIGINAL="$BACKUPS/mcp-passport-2b.yv4sttqC"
RECOVERY="$BACKUPS/mcp-phase2c-database.PMvHoAcv"
KEYS="$BACKUPS/mcp-phase2d-keys.9eSPLJNB"
export GH_CONFIG_DIR=/root/learncambodia-github-auth/gh
cd "$SITE"
exec 9>"$BACKUPS/mcp-oauth-phase2b-stage.lock"
flock -n 9
exec 8>"$BACKUPS/course-builder.lock"
flock -n 8
test -x "$PHP" && test -x "$FPM"
test ! -e storage/framework/down
test ! -e bootstrap/cache/config.php
test -f storage/app/private/learncambodia-mcp-oauth-phase2b-staged.txt
test "$(git hash-object composer.lock)" = 26844d19ba14bd4c2cf0568d506f7b5bba4568d8
test "$(git hash-object resources/js/learning-manager.ts)" = e232d0f06c1391bf413485079b1a039e79bacc3e
sha256sum -c "$RECOVERY/backup.sha256"
test -f storage/oauth-private.key && test ! -L storage/oauth-private.key
test -f storage/oauth-public.key && test ! -L storage/oauth-public.key
test "$(stat -c '%a:%U:%G' storage/oauth-private.key)" = 640:root:www
test "$(openssl pkey -pubin -in storage/oauth-public.key -outform DER | sha256sum)" = "$(cat "$KEYS/public-key-fingerprint.sha256")"

STAGE="$(mktemp -d "$BACKUPS/mcp-phase3a-promotion.XXXXXXXX")"
echo "EVIDENCE_DIR=$STAGE"
test "$(gh api "repos/$REPO/git/ref/heads/$BRANCH" --jq '.object.sha')" = "$RELEASE"
gh api "repos/$REPO/actions/runs?head_sha=$RELEASE&per_page=100" > "$STAGE/ci.json"
python3 - "$STAGE/ci.json" "$RELEASE" <<'PY'
import json,sys
j=json.load(open(sys.argv[1]))
runs=j["workflow_runs"]
required={
 "MCP Passport Phase 2B Isolated Regression",
 "MCP Passport Phase 2B MySQL Acceptance",
 "MCP OAuth Discovery Phase 2A Preflight",
 "MCP OAuth Passport Compatibility (Isolated)",
 "MCP Passport Phase 2B Operator Preflight Safety",
 "MCP Passport Phase 2B Live Code Safety",
 "v0.0.1 Foundation Quality",
 "Release acceptance - isolated MySQL, browser engines and security",
 "MCP Browser Admin CRUD Bridge acceptance",
 "MCP Management foundation acceptance",
 "MCP Analytics READ Regression",
 "MCP OAuth Phase 2A Operator Safety",
 "MCP OAuth Live Overlay Operator Safety",
 "MCP Direct Bridge Menu Password Acceptance",
 "Campaign Studio premium UI preflight",
 "Campaign Course Builder UX regression",
 "Campaign Course Workspace acceptance",
 "Multi-Campaign homepage preflight",
 "Campaign Module Duplicate Media acceptance",
 "Campaign Unit Assessment acceptance",
 "QA Vite asset artifact - pinned source (no deployment)",
}
if not required.issubset({r["name"] for r in runs}):
    raise SystemExit("REQUIRED_CI_MISSING")
if any(r["head_sha"] != sys.argv[2] or r["status"]!="completed" or r["conclusion"]!="success" for r in runs):
    raise SystemExit("CI_NOT_ALL_GREEN")
print("PINNED_CI=PASS")
PY
files=(
 app/Console/Commands/RegisterMcpOAuthClient.php
 app/Http/Controllers/Api/McpOAuthDiscoveryController.php
 app/Http/Middleware/EnsureMcpOAuthStagingEnabled.php
 app/Http/Middleware/ValidateMcpOAuthParameters.php
 bootstrap/app.php
 config/mcp.php
)
for file in "${files[@]}"; do
 test -f "$file" && test ! -L "$file"
 test "$(realpath "$(dirname "$file")")" = "$SITE/$(dirname "$file")"
 for d in old base new ready; do mkdir -p "$STAGE/$d/$(dirname "$file")"; done
 cp -p "$file" "$STAGE/old/$file"
 for label in base new; do
   ref="$BASE"; if [[ "$label" == new ]]; then ref="$RELEASE"; fi
   expected="$(gh api "repos/$REPO/contents/$file?ref=$ref" --jq '.sha')"
   gh api -H 'Accept: application/vnd.github.raw+json' "repos/$REPO/contents/$file?ref=$ref" > "$STAGE/$label/$file"
   test "$(git hash-object "$STAGE/$label/$file")" = "$expected"
 done
 if [[ "$file" == bootstrap/app.php ]]; then
   cmp "$file" "$ORIGINAL/merged/$file"
   python3 - "$STAGE" "$file" <<'PY'
import sys
from pathlib import Path
root=Path(sys.argv[1])
f=sys.argv[2]
old="""if (! config('mcp.oauth_provider_staging_enabled', false)
                || ! config('mcp.oauth_provider_accepted', false)
                || ! config('mcp.oauth_ready', false))"""
new="if (! EnsureMcpOAuthStagingEnabled::enabled())"
base=(root/"base"/f).read_text()
target=(root/"new"/f).read_text()
live=(root/"old"/f).read_text()
assert base.count(old)==1 and base.replace(old,new,1)==target
assert live.count(old)==1
(root/"ready"/f).write_text(live.replace(old,new,1))
PY
 else
   cmp "$file" "$STAGE/base/$file"
   cp "$STAGE/new/$file" "$STAGE/ready/$file"
 fi
 "$PHP" -l "$STAGE/ready/$file"
done
echo "TARGETED_OVERLAYS=PASS"
# Do not expose config values, secrets or token records.
inspect() {
 runuser -u www -- "$PHP" <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!$app->environment('production')) throw new RuntimeException('Not production');
foreach ([
 'oauth_discovery_enabled','oauth_provider_accepted','oauth_provider_staging_enabled',
 'oauth_ready','transport_ready','oauth_production_registration_enabled',
 'oauth_production_accepted','oauth_production_enabled',
] as $flag) {
 if (config('mcp.'.$flag)) throw new RuntimeException('Open gate: '.$flag);
}
foreach (['MCP_OAUTH_PRODUCTION_REGISTRATION_ENABLED','MCP_OAUTH_PRODUCTION_ACCEPTED','MCP_OAUTH_PRODUCTION_ENABLED'] as $name) {
 if (Illuminate\Support\Env::get($name,false)) throw new RuntimeException('Unexpected production switch: '.$name);
}
$db=Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName()!=='mysql') throw new RuntimeException('Unexpected DB driver');
$passport=config('passport.connection');
if ($passport && $passport!==$db->getName()) throw new RuntimeException('Passport DB mismatch');
foreach (['oauth_auth_codes','oauth_access_tokens','oauth_refresh_tokens','oauth_clients','oauth_device_codes','mcp_oauth_token_audiences'] as $table) {
 if ((int)$db->table($table)->count()!==0) throw new RuntimeException('Unexpected OAuth data');
}
if (config('passport.private_key') || config('passport.public_key')) throw new RuntimeException('Unexpected inline keys');
foreach (['oauth-private.key','oauth-public.key'] as $file) {
 if (Laravel\Passport\Passport::keyPath($file)!==storage_path($file) || !is_readable(storage_path($file))) throw new RuntimeException('Passport key path or permissions');
}
echo "OAUTH_DISABLED_EMPTY_SCHEMA_KEYS_READABLE=PASS\n";
echo 'LEGACY_REMOTE_WRITES='.(config('mcp.remote_writes_enabled')?'ON':'OFF').PHP_EOL;
echo 'LEGACY_GATEWAY='.(config('mcp.gateway_enabled')?'ON':'OFF').PHP_EOL;
PHP
}
inspect > "$STAGE/config-before.txt"
cat "$STAGE/config-before.txt"
runuser -u www -- "$PHP" artisan route:list --json > "$STAGE/routes-before.json"
sha256sum .env composer.lock public/build/manifest.json storage/oauth-private.key storage/oauth-public.key > "$STAGE/preserved.sha256"
probe() {
 local path="$1" expected="$2" method="$3" status
 local -a args=()
 if [[ "$method" == POST ]]; then
   args=(-H 'Content-Type: application/json' -H 'Accept: application/json' --data '{"jsonrpc":"2.0","id":1,"method":"initialize"}')
 fi
 status="$(curl -4 -sS --connect-timeout 8 --max-time 20 --resolve learncambodia.com:443:127.0.0.1 "${args[@]}" -o /dev/null -w '%{http_code}' "https://learncambodia.com$path")"
 echo "$path HTTP=$status"
 test "$status" = "$expected"
}
probes() {
 probe / 200 GET
 probe /api/mcp/auth-guide 200 GET
 probe /api/mcp 401 POST
 probe /.well-known/oauth-protected-resource 404 GET
 probe /.well-known/oauth-authorization-server 404 GET
 probe /mcp/oauth/authorize 404 GET
 probe /api/mcp/oauth 404 POST
 probe /api/mcp/oauth/token 404 POST
}
probes
if [[ "$MODE" == --prepare ]]; then
 echo "PRODUCTION_FILES_MODIFIED=NONE"
 echo "PHASE3A_PREPARE=PASS"
 exit 0
fi
replace() {
 local source="$1" dest="$2" reference="$3" tmp
 tmp="$(mktemp "$(dirname "$dest")/.oauth-promotion.XXXXXXXX")"
 cat "$source" > "$tmp"
 chown --reference="$reference" "$tmp"
 chmod --reference="$reference" "$tmp"
 mv -f -- "$tmp" "$dest"
}
installed=()
complete=0
finish() {
 local code=$?
 trap - EXIT
 if [[ "$complete" == 0 && "${#installed[@]}" -gt 0 ]]; then
   set +e
   local failed=0 f
   for f in "${installed[@]}"; do
     replace "$STAGE/old/$f" "$SITE/$f" "$STAGE/old/$f" || failed=1
   done
   "$FPM" reload || failed=1
   if [[ "$failed" == 0 ]]; then echo "PHASE3A_ROLLBACK=RESTORED"; else echo "MANUAL_RECOVERY_REQUIRED"; code=1; fi
 fi
 exit "$code"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
for f in "${files[@]}"; do
 cmp "$f" "$STAGE/old/$f"
 installed+=("$f")
 replace "$STAGE/ready/$f" "$SITE/$f" "$STAGE/old/$f"
 runuser -u www -- test -r "$f"
done
inspect > "$STAGE/config-after.txt"
cmp "$STAGE/config-before.txt" "$STAGE/config-after.txt"
runuser -u www -- "$PHP" artisan route:list --json > "$STAGE/routes-after.json"
python3 - "$STAGE" <<'PY'
import json,sys
from pathlib import Path
p=Path(sys.argv[1])
def route_set(name):
    return sorted(json.dumps(r,sort_keys=True) for r in json.loads((p/name).read_bytes()))
assert route_set("routes-before.json")==route_set("routes-after.json")
print("ROUTES_PRESERVED=PASS")
PY
"$FPM" reload
probes
sha256sum -c "$STAGE/preserved.sha256"
complete=1
echo "OAUTH_CLIENTS_REGISTERED=NONE"
echo "OAUTH_TOKEN_ISSUANCE=DISABLED"
echo "PRODUCTION_ENV_UNCHANGED=PASS"
echo "DATABASE_MUTATIONS=NONE"
echo "PHASE3A_DISABLED_CODE_RELEASE=PASS"
echo "EVIDENCE_DIR=$STAGE"
