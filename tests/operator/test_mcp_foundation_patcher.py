#!/usr/bin/env python3
"""Regression coverage for surgical admin MCP patching of drifted live files."""
import json
import re
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
PATCHER=ROOT / "support/operator/learncambodia-mcp-foundation-patcher.py"
SOURCES=[
    ROOT / "routes/web.php",
    ROOT / "config/workspaces.php",
    ROOT / "app/Support/WorkspaceMenu.php",
]

def without_mcp_entry(files):
    web,menu,icon=files
    web, route_changes=re.subn(
        r"(?m)^[ \t]*Route::get\('/mcp', \[\\App\\Http\\Controllers\\Workspace\\AdminMcpManagementController::class, 'index'\]\)->name\('mcp'\);\n",
        "",web,count=1
    )
    assert route_changes==1
    menu,changes=re.subn(
        r',\s*\{"key": "mcp", "label": "MCP Management", "route": "admin.system-settings.mcp"\}',
        "",menu,count=1,
    )
    assert changes==1
    icon,changes=re.subn(r"(?m)^[ \t]*'system-settings/mcp' => 'terminal',\s*\n", "",icon,count=1)
    assert changes==1
    return [web,menu,icon]

class FoundationOverlayTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.sources=[path.read_text() for path in SOURCES]

    def run_overlay(self,sources):
        with tempfile.TemporaryDirectory() as td:
            stage=Path(td)
            (stage/"backup").mkdir()
            (stage/"patch").mkdir()
            for i,body in enumerate(sources):
                (stage/"backup"/str(i)).write_text(body)
            process=subprocess.run([sys.executable,str(PATCHER),str(stage)],
                capture_output=True,text=True)
            patched=[(stage/"patch"/str(i)).read_text() if (stage/"patch"/str(i)).exists() else ""
                     for i in range(3)]
            return process,patched

    def assert_menu_single(self,source):
        found=re.search(r"<<<'JSON'\s*\n(.*?)\nJSON\b",source,re.S)
        self.assertIsNotNone(found)
        settings=next(x for x in json.loads(found.group(1))["admin"] if x["key"]=="system-settings")
        mcp=[x for x in settings["items"] if x.get("route")=="admin.system-settings.mcp"]
        self.assertEqual(len(mcp),1)

    def test_already_present_source_is_idempotent(self):
        result,out=self.run_overlay(self.sources)
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertEqual(out,self.sources)
        self.assertIn("MCP_ROUTE_OVERLAY=already_present",result.stdout)

    def test_clean_source_gets_explicit_admin_route_and_menu(self):
        clean=without_mcp_entry(self.sources)
        result,out=self.run_overlay(clean)
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertIn("Route::middleware(['auth', 'role:admin'])",out[0])
        self.assertIn("->prefix('dashboard/system-settings')->name('admin.system-settings.')",out[0])
        self.assertIn("AdminMcpManagementController::class",out[0])
        self.assert_menu_single(out[1])
        self.assertIn("'system-settings/mcp' => 'terminal'",out[2])
        result2,out2=self.run_overlay(out)
        self.assertEqual(result2.returncode,0,result2.stderr)
        self.assertEqual(out2,out)

    def test_changed_social_auth_syntax_does_not_break_admin_route(self):
        clean=without_mcp_entry(self.sources)
        clean[0]=clean[0].replace(
            "Route::get('/social-auth', 'socialAuth')->name('social-auth');",
            "Route::get('/social-auth', [AdminSystemSettingsController::class, 'socialAuth'])->name('social-auth');"
        )
        result,out=self.run_overlay(clean)
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertIn("MCP_ROUTE_OVERLAY=dedicated_auth_admin_group",result.stdout)
        self.assertIn("MCP_ADDITIVE_LIVE_PATCHES=PASS",result.stdout)

    def test_preexisting_valid_mcp_route_with_missing_menu_and_icon(self):
        clean=without_mcp_entry(self.sources)
        clean[0]=self.sources[0]
        result,out=self.run_overlay(clean)
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertEqual(out[0],clean[0])
        self.assert_menu_single(out[1])
        self.assertIn("'system-settings/mcp' => 'terminal'",out[2])

    def test_conflicting_public_mcp_route_fails_closed(self):
        sources=list(self.sources)
        sources[0]=sources[0].replace(
            "AdminMcpManagementController::class",
            "UnknownPublicController::class",1
        )
        result,out=self.run_overlay(sources)
        self.assertNotEqual(result.returncode,0)
        self.assertIn("MCP_ROUTE_CONFLICT",result.stderr)

    def test_menu_route_collision_fails_closed(self):
        sources=list(self.sources)
        sources[1]=sources[1].replace(
            '"key": "mcp", "label": "MCP Management"',
            '"key": "wrong", "label": "MCP Management"',1
        )
        result,_=self.run_overlay(sources)
        self.assertNotEqual(result.returncode,0)
        self.assertIn("MCP_MENU_CONFLICT",result.stderr)

    def test_admin_middleware_unknown_fails_closed(self):
        clean=without_mcp_entry(self.sources)
        clean[0]=clean[0].replace("'role:admin'","'not-the-admin-guard'")
        result,_=self.run_overlay(clean)
        self.assertNotEqual(result.returncode,0)
        self.assertIn("MCP_ADMIN_MIDDLEWARE_UNRECOGNIZED",result.stderr)

if __name__=="__main__":
    unittest.main()
