#!/usr/bin/env python3
"""Verify Phase 2B live overlay is source-exact and preserves unrelated edits."""
import re
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "support/operator/learncambodia-mcp-oauth-phase2b-stage-code-aapanel.sh"
source = SCRIPT.read_text()
pattern = r"python3 - \"\$STAGE/base/\$f\" \"\$STAGE/release/\$f\" \"\$STAGE/old/\$f\" \"\$STAGE/merged/\$f\" \"\$f\" <<'PY'\n(.*?)\nPY"
matches = re.findall(pattern, source, re.S)
assert len(matches) == 1, "Semantic production patcher must have one exact implementation"
patcher = matches[0]
base_sha = "f3409f57da1fb39ac339ad80f5d3e70a1045075b"
paths = ["routes/web.php", "app/Providers/AppServiceProvider.php", "bootstrap/app.php"]

def patch(base: str, reviewed: str, live: str, name: str, expect_ok=True):
    with tempfile.TemporaryDirectory() as d:
        root = Path(d)
        inputs = [root / x for x in ["base.php", "reviewed.php", "live.php", "merged.php"]]
        for path, value in zip(inputs[:3], [base, reviewed, live]):
            path.write_text(value)
        p = subprocess.run([sys.executable, "-c", patcher,
                            *[str(x) for x in inputs], name],
                           text=True, capture_output=True, check=False)
        assert (p.returncode == 0) == expect_ok, (
            name, "unexpected patch outcome", p.stdout, p.stderr
        )
        return inputs[3].read_text() if expect_ok else p.stderr + p.stdout

for path in paths:
    base = subprocess.check_output(
        ["git", "show", base_sha + ":" + path], text=True
    )
    # Phase 2B is a historical operator with a fixed reviewed route contract.
    # Later transport additions are checked separately below.
    # Preserve the historical reviewed fixture contract.
    # Provider and bootstrap use the previously CI-accepted
    # PR revision, not the evolving application worktree.
    reviewed_sha = (
        "58f4a45cc67ff6215259157c8c4b77ce44ea1c98"
        if path == "routes/web.php"
        else "8bd5aa20ee9f0ed217308f1c017a5800d86ade04"
    )
    reviewed = subprocess.check_output(
        ["git", "show", reviewed_sha + ":" + path],
        text=True,
    )

    assert patch(base, reviewed, base, path) == reviewed, (
        "Base overlay must reproduce reviewed code byte-for-byte", path
    )
    if path == "routes/web.php":
        # Actual live Phase 2A metadata was overlaid into a route file with
        # independently added features and a different neighboring comment.
        altered = base.replace(
            "// OAuth 2.1 discovery is fail-closed",
            "// Prior live Phase 2A OAuth metadata is fail-closed", 1
        ) + "\n// Live external feature preserved\n"
        preserved = "// Live external feature preserved"
    elif path == "app/Providers/AppServiceProvider.php":
        altered = base.replace(
            "use Laravel\\Fortify\\Fortify;\n",
            "use Laravel\\Fortify\\Fortify;\nuse App\\Services\\CustomFeature;\n",
            1,
        ).replace(
            "        View::composer('partials.header'",
            "        // Existing production header registration.\n"
            "        View::composer('partials.header'", 1
        )
        preserved = "use App\\Services\\CustomFeature;"
    else:
        altered = base.replace(
            "->withCommands([AssignAccountRole::class])",
            "->withCommands([AssignAccountRole::class, CustomCommand::class])", 1
        ).replace(
            "'developer.token' => EnsureDeveloperToken::class,",
            "'developer.token' => EnsureDeveloperToken::class,\n"
            "            'custom.access' => CustomMiddleware::class,", 1
        )
        preserved = "CustomCommand::class"

    merged = patch(base, reviewed, altered, path)
    assert preserved in merged, ("Production-only code was lost", path)
    assert "mcp.oauth" in merged or "Passport::" in merged or "RegisterMcpOAuthClient" in merged
    assert patch(base, reviewed, merged, path, False), (
        "Repeated OAuth application must fail closed", path
    )
    print("MCP_PHASE2B_SYNTHETIC_PRODUCTION_DRIFT_PRESERVED=" + path)

print("MCP_PHASE2B_SEMANTIC_OVERLAY_ACCEPTANCE=PASS")

# Verify the historical transport overlay against its pinned reviewed route sources.
# Later guest routes are intentionally outside this exact-diff operator contract.
import runpy
transport = runpy.run_path(str(ROOT / "support/operator/learncambodia-mcp-transport-route-overlay.py"))
for path in ("routes/web.php", "routes/api.php"):
    base = subprocess.check_output(["git", "show", "58f4a45cc67ff6215259157c8c4b77ce44ea1c98:" + path], text=True)
    reviewed = subprocess.check_output(["git", "show", "fdb90f3ad7d367192ed458da49e269f9cb06abb6:" + path], text=True)
    overlay = transport["overlay"]
    anchor, extra, marker = transport["OPS"][path]
    assert overlay(path, base, reviewed, base) == reviewed
    live = base + "\n// Production-only route customization\n"
    assert overlay(path, base, reviewed, live) == reviewed + "\n// Production-only route customization\n"
    for bad in (live.replace(anchor, "", 1), live + anchor, reviewed):
        try:
            overlay(path, base, reviewed, bad)
        except ValueError:
            pass
        else:
            raise AssertionError("Unsafe transport overlay accepted: " + path)
    try:
        overlay(path, base, reviewed + "\n// Unreviewed route change\n", live)
    except ValueError:
        pass
    else:
        raise AssertionError("Unreviewed transport diff accepted: " + path)
    print("MCP_TRANSPORT_OVERLAY_VERIFIED=" + path)
