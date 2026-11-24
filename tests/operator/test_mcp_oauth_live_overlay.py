#!/usr/bin/env python3
"""CI-only verification that the production OAuth overlay preserves all existing routes."""
import re
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "support/operator/learncambodia-mcp-oauth-phase2a-live-overlay-aapanel.sh"
source = SCRIPT.read_text()

assert "LIVE_ROUTES_BLOB=\"9160b1afaf28f9651585ace8a319337f57109f7f\"" in source
assert "LIVE_CONFIG_BLOB=\"55b145f760879478e5b83ee63f3ffdf4d66b626c\"" in source
assert "MCP_OAUTH_OVERLAY_PINNED_CI=PASS" in source
assert "MCP_OAUTH_PRESERVE_ALL_EXISTING_ROUTES=PASS" in source
assert "MCP_EXISTING_ROUTE_REGISTRY_UNCHANGED=PASS" in source
assert "MCP_OAUTH_DISCOVERY_FAIL_CLOSED=PASS" in source
assert "MCP_OAUTH_PHASE2A_ROLLBACK=RESTORED" in source
assert "MCP_OAUTH_AUTHORIZATION_SERVER=DISABLED" in source
assert "MCP_OAUTH_TOKEN_ISSUANCE=DISABLED" in source
assert "MCP_REMOTE_WRITES=UNCHANGED" in source
assert "MCP_CONTENT_MUTATIONS=NONE" in source
for forbidden in ["migrate:fresh", "db:wipe", "git reset --hard",
                  "chmod -R 777", "MCP_REMOTE_WRITES_ENABLED=true",
                  "artisan migrate", "artisan key:generate"]:
    assert forbidden not in source

m = re.search(r"python3 - \"\$BACKUP/original-routes.php\" \"\$BACKUP/new-routes.php\" <<'PY'\n(.*?)\nPY", source, re.S)
assert m, "Python patch algorithm must be present"
patcher = m.group(1)
fixture = (b"<?php\n"
    b"use App\\Http\\Controllers\\Auth\\SocialAuthController;\n"
    b"use Illuminate\\Support\\Facades\\Route;\n"
    b"Route::get('/social', [SocialAuthController::class, 'start']);\n"
    b"Route::get('/courses', [CourseController::class, 'index']);\n"
    b"require __DIR__.'/student-library.php';\n")
with tempfile.TemporaryDirectory() as tmp:
    orig, target = Path(tmp) / "web.php", Path(tmp) / "patched.php"

    def run_patch(data, ok):
        orig.write_bytes(data)
        result = subprocess.run(
            [sys.executable, "-c", patcher, str(orig), str(target)],
            text=True, capture_output=True, check=False)
        if ok:
            assert result.returncode == 0, result.stderr
            assert b"mcp.oauth.metadata.resource" in target.read_bytes()
            assert b"mcp.oauth.metadata.authorization-server" in target.read_bytes()
            assert target.read_bytes().count(b"McpOAuthDiscoveryController") == 3
            assert target.read_bytes().endswith(
                b"require __DIR__.'/student-library.php';\n")
            assert b"Route::get('/social', [SocialAuthController::class, 'start']);" in target.read_bytes()
            assert b"Route::get('/courses', [CourseController::class, 'index']);" in target.read_bytes()
            print("MCP_OAUTH_PRESERVE_LIVE_SOCIAL_AND_COURSE_ROUTES=PASS")
        else:
            assert result.returncode != 0
            print("MCP_OAUTH_UNEXPECTED_ROUTE_STATE_REJECTED=PASS")
        return target.read_bytes() if ok else None

    applied = run_patch(fixture, True)
    run_patch(applied, False)
    run_patch(fixture.replace(b"use Illuminate\\Support\\Facades\\Route;\n", b""), False)
    run_patch(fixture.replace(b"use Illuminate\\Support\\Facades\\Route;\n",
                             b"use Illuminate\\Support\\Facades\\Route;\n"*2), False)

print("MCP_OAUTH_LIVE_OVERLAY_OPERATOR_SAFETY=PASS")
