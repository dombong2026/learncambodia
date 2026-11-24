"""Offline safety tests for the read-only LearnCambodia MCP production probe."""
import contextlib
import importlib.util
import io
from pathlib import Path
import sys
import unittest
from unittest.mock import patch

PROBE = Path(__file__).resolve().parents[2] / "support/operator/learncambodia-mcp-readonly-acceptance.py"
spec = importlib.util.spec_from_file_location("mcp_readonly_acceptance", PROBE)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

class McpProductionProbeTests(unittest.TestCase):
    def test_anonymous_denial(self):
        def fake(url, payload=None, token=None):
            if payload is None:
                return 200, {"status": "browser_authentication_required"}
            return 401, {}
        output = io.StringIO()
        with patch.object(module, "send", side_effect=fake), \
             patch.object(sys, "argv", ["verify"]), contextlib.redirect_stdout(output):
            module.main()
        self.assertIn("ANONYMOUS_ACCEPTANCE=PASS", output.getvalue())

    def test_authenticated_read_only_never_discloses_token(self):
        calls = []
        def fake(url, payload=None, token=None):
            calls.append((url, payload, token))
            if payload is None:
                return 200, {"status": "browser_authentication_required"}
            if token is None or token.startswith("invalid-"):
                return 401, {}
            method = payload["method"]
            if method == "initialize":
                return 200, {"result": {"serverInfo": {"name": "learncambodia-admin-bridge"}}}
            if method == "tools/list":
                return 200, {"result": {"tools": [{"name": key} for key in (
                    "auth_browser_admin", "crud_manage_platform", "confirm_destructive_action")]}}
            if method == "tools/call":
                return 200, {"result": {"isError": False, "structuredContent": {
                    "items": [{"id": 1}], "receipt_id": "test-receipt"}}}
            raise AssertionError("Unexpected method")
        output = io.StringIO()
        with patch.object(module, "send", side_effect=fake), \
             patch.object(module, "getpass", return_value="123|SECRET_TEST"), \
             patch.object(sys, "argv", ["verify", "--with-token"]), \
             contextlib.redirect_stdout(output):
            module.main()
        self.assertIn("MCP_READ_ONLY_END_TO_END=PASS", output.getvalue())
        self.assertNotIn("SECRET_TEST", output.getvalue())
        self.assertEqual(["READ","READ"], [
            payload["params"]["arguments"]["operation"]
            for _, payload, _ in calls
            if payload is not None and payload["method"] == "tools/call"
        ])

    def test_unauthorized_http_200_fails_closed(self):
        def fake(url, payload=None, token=None):
            if payload is None:
                return 200, {"status": "browser_authentication_required"}
            return 200, {}
        with patch.object(module, "send", side_effect=fake), \
             patch.object(sys, "argv", ["verify"]), self.assertRaises(RuntimeError):
            module.main()

if __name__ == "__main__":
    unittest.main()
