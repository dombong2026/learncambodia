#!/usr/bin/env python3
"""Apply only the approved MCP transport route additions to a live route file."""
import sys
from pathlib import Path

OPS = {
    "routes/web.php": [
        "    ->middleware('throttle:30,1')->name('mcp.oauth.metadata.resource');\n",
        "// RFC 9728 path-aware discovery alias; identical gate and metadata.\nRoute::get('/.well-known/oauth-protected-resource/api/mcp/oauth', [McpOAuthDiscoveryController::class, 'protectedResource'])\n    ->middleware('throttle:30,1')->name('mcp.oauth.metadata.resource.path');\n",
        "mcp.oauth.metadata.resource.path"
    ],
    "routes/api.php": [
        "    ->name('mcp.oauth.rpc');\n",
        "\n// Streamable HTTP without a standalone SSE stream. No data is returned.\nRoute::get('/mcp/oauth', static fn () => response()->json(\n    ['error' => 'method_not_allowed'], 405, ['Allow' => 'POST', 'Cache-Control' => 'no-store']\n))->middleware([\\App\\Http\\Middleware\\EnsureMcpOAuthApiEnabled::class, 'throttle:60,1'])\n    ->name('mcp.oauth.stream.unsupported');\n",
        "mcp.oauth.stream.unsupported"
    ]
}

def overlay(name, base, reviewed, live):
    if name not in OPS:
        raise ValueError("UNSUPPORTED_ROUTE_FILE")
    anchor, extra, marker = OPS[name]
    if base.count(anchor) != 1 or live.count(anchor) != 1:
        raise ValueError("ROUTE_ANCHOR_MISSING_OR_AMBIGUOUS")
    if marker in base or marker in live:
        raise ValueError("ROUTE_ALREADY_PRESENT_OR_CONFLICT")
    if base.replace(anchor, anchor + extra, 1) != reviewed:
        raise ValueError("REVIEWED_DIFF_NOT_EXACT")
    return live.replace(anchor, anchor + extra, 1)

if __name__ == "__main__":
    name, base, reviewed, live, output = sys.argv[1:]
    result = overlay(name, *(Path(p).read_text() for p in (base, reviewed, live)))
    Path(output).write_text(result)
    print("MCP_TRANSPORT_EXACT_OVERLAY=" + name)
