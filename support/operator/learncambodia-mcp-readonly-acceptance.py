#!/usr/bin/env python3
"""LearnCambodia MCP production acceptance probe. READ ONLY; no token persistence.

Run once without arguments to verify anonymous denial, then with --with-token
after issuing a short-lived READ-scoped credential in the Website Admin browser.
Never paste the credential into ChatGPT or run with shell xtrace enabled.
"""
import argparse
from getpass import getpass
import json
import sys
import urllib.error
import urllib.request

BASE = "https://learncambodia.com"
MCP = BASE + "/api/mcp"
HEADERS = {"Accept": "application/json", "Content-Type": "application/json",
           "User-Agent": "LearnCambodiaMCPReadOnlyAcceptance/1.0"}

def send(url, payload=None, token=None):
    headers = dict(HEADERS)
    if token is not None:
        headers["Authorization"] = "Bearer " + token
    data = json.dumps(payload, separators=(",", ":")).encode() if payload is not None else None
    request = urllib.request.Request(
        url, data=data, headers=headers, method="POST" if data is not None else "GET")
    try:
        with urllib.request.urlopen(request, timeout=20) as response:
            status, body = response.status, response.read(1024 * 1024)
    except urllib.error.HTTPError as error:
        status, body = error.code, error.read(1024 * 1024)
    try:
        return status, json.loads(body.decode())
    except (UnicodeDecodeError, ValueError):
        return status, None

def rpc(method, ident, params=None):
    request = {"jsonrpc": "2.0", "id": ident, "method": method}
    if params is not None:
        request["params"] = params
    return request

def require(condition, message):
    if not condition:
        raise RuntimeError(message)

def main():
    parser = argparse.ArgumentParser(description="Verify direct MCP bridge without writing content")
    parser.add_argument("--with-token", action="store_true", help="Enter the browser-issued token invisibly")
    args = parser.parse_args()

    status, guide = send(BASE + "/api/mcp/auth-guide")
    require(status == 200 and isinstance(guide, dict)
            and guide.get("status") == "browser_authentication_required",
            "PUBLIC_AUTH_GUIDE=FAIL HTTP %s" % status)
    print("PUBLIC_AUTH_GUIDE=PASS HTTP 200")

    status, _ = send(MCP, rpc("initialize", 1))
    require(status == 401, "ANONYMOUS_DENIAL=FAIL HTTP %s (expected 401)" % status)
    print("ANONYMOUS_DENIAL=PASS HTTP 401")

    status, _ = send(MCP, rpc("initialize", 2), token="invalid-mcp-acceptance-token")
    require(status == 401, "INVALID_TOKEN_DENIAL=FAIL HTTP %s (expected 401)" % status)
    print("INVALID_TOKEN_DENIAL=PASS HTTP 401")

    if not args.with_token:
        print("ANONYMOUS_ACCEPTANCE=PASS")
        print("NEXT=Authenticate in Browser, grant READ, then rerun with --with-token")
        return

    token = getpass("Paste short-lived MCP READ token (hidden input): ").strip()
    require(bool(token) and len(token) < 4096 and not any(c.isspace() for c in token),
            "TOKEN_FORMAT_INVALID")
    try:
        status, data = send(MCP, rpc("initialize", 3), token=token)
        info = (data or {}).get("result", {}).get("serverInfo", {})
        require(status == 200 and info.get("name") == "learncambodia-admin-bridge",
                "MCP_INITIALIZE=FAIL HTTP %s" % status)
        print("MCP_INITIALIZE=PASS HTTP 200")

        status, data = send(MCP, rpc("tools/list", 4), token=token)
        tools = (data or {}).get("result", {}).get("tools", [])
        names = {item.get("name") for item in tools if isinstance(item, dict)}
        required = {"auth_browser_admin", "crud_manage_platform", "confirm_destructive_action"}
        require(status == 200 and required.issubset(names),
                "MCP_TOOLS_LIST=FAIL HTTP %s" % status)
        print("MCP_TOOLS_LIST=PASS TOOLS=%d" % len(names))

        for ident, resource in [(5, "courses"), (6, "analytics")]:
            params = {"name": "crud_manage_platform", "arguments": {
                "operation": "READ", "resource": resource, "payload": {}}}
            status, data = send(MCP, rpc("tools/call", ident, params), token=token)
            result = (data or {}).get("result", {})
            structured = result.get("structuredContent", {})
            require(status == 200 and result.get("isError") is False
                    and isinstance(structured, dict) and "items" in structured,
                    "MCP_READ_%s=FAIL HTTP %s" % (resource.upper(), status))
            receipt = structured.get("receipt_id")
            require(isinstance(receipt, str) and receipt,
                    "MCP_READ_%s_RECEIPT=FAIL" % resource.upper())
            print("MCP_READ_%s=PASS COUNT=%d RECEIPT_ID=%s" %
                  (resource.upper(), len(structured["items"]), receipt))
        print("MCP_READ_ONLY_END_TO_END=PASS")
        print("NO_CONTENT_MUTATIONS=PASS")
    finally:
        token = ""  # Never write bearer credentials to a file or print them.

if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, urllib.error.URLError, TimeoutError) as error:
        print("MCP_ACCEPTANCE=FAIL: " + str(error), file=sys.stderr)
        sys.exit(1)
