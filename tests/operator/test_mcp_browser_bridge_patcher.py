#!/usr/bin/env python3
"""Regression tests for the browser bridge's live-foundation route reconciliation."""
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
PATCHER = ROOT / "support/operator/learncambodia-mcp-browser-bridge-patcher.py"

GROUP = r"""
// Real production foundation shape, with route name on a separate line.
Route::middleware(['auth', 'role:admin'])
    ->prefix('dashboard/system-settings')->name('admin.system-settings.')
    ->group(function (): void {
        Route::get('/mcp', [\App\Http\Controllers\Workspace\AdminMcpManagementController::class, 'index'])
            ->name('mcp');
    });
"""
API = r"""<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogController;
Route::get('/navigation/categories', [CatalogController::class, 'navigationCategories'])
    ->name('navigation.categories');
"""

class BrowserBridgeRouteOverlayTests(unittest.TestCase):
    def patch(self, web=GROUP, api=API):
        with tempfile.TemporaryDirectory() as td:
            stage=Path(td)
            (stage/"backup").mkdir()
            (stage/"patch").mkdir()
            (stage/"backup/patch-0").write_text(web)
            (stage/"backup/patch-1").write_text(api)
            result=subprocess.run([sys.executable,str(PATCHER),str(stage)],
                text=True,capture_output=True)
            result_files=[]
            for i in range(2):
                candidate=stage/"patch"/str(i)
                result_files.append(candidate.read_text() if candidate.exists() else "")
            return result,result_files

    def test_install_into_protected_live_foundation(self):
        result,files=self.patch()
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertIn("MCP_ADDITIVE_ROUTE_PATCHES=PASS",result.stdout)
        self.assertIn("mcp.sessions.issue",files[0])
        self.assertIn("mcp.approvals.decide",files[0])
        self.assertIn("auth', 'role:admin",files[0])
        self.assertIn("auth:sanctum",files[1])
        self.assertEqual(files[0].count("mcp.sessions.issue"),1)

    def test_missing_admin_role_is_refused(self):
        result,_=self.patch(GROUP.replace("['auth', 'role:admin']","['auth']"))
        self.assertNotEqual(result.returncode,0)
        self.assertIn("MCP_ADMIN_ROUTE_GROUP_NOT_RECOGNIZED",result.stderr)

    def test_unauthenticated_admin_route_is_refused(self):
        public=GROUP.replace("['auth', 'role:admin']","[]")
        result,_=self.patch(public)
        self.assertNotEqual(result.returncode,0)
        self.assertIn("MCP_ADMIN_ROUTE_GROUP_NOT_RECOGNIZED",result.stderr)

    def test_different_admin_controller_is_refused(self):
        result,_=self.patch(GROUP.replace("AdminMcpManagementController::class","OtherController::class"))
        self.assertNotEqual(result.returncode,0)
        self.assertIn("MCP_FOUNDATION_ADMIN_ROUTE_NOT_UNIQUE",result.stderr)

    def test_duplicate_route_is_refused(self):
        result,_=self.patch(GROUP+GROUP)
        self.assertNotEqual(result.returncode,0)
        self.assertIn("MCP_FOUNDATION_ADMIN_ROUTE_NOT_UNIQUE",result.stderr)

    def test_missing_api_anchor_is_refused(self):
        result,_=self.patch(api=API.replace("/navigation/categories","/other"))
        self.assertNotEqual(result.returncode,0)
        self.assertIn("MCP_API_ROUTE_ANCHOR_DRIFT",result.stderr)

    def test_preexisting_api_rpc_is_refused(self):
        result,_=self.patch(api=API+"\nRoute::post('/mcp', fn()=>null)->name('mcp.rpc');")
        self.assertNotEqual(result.returncode,0)
        self.assertIn("MCP_API_ROUTE_ALREADY_PRESENT",result.stderr)

if __name__ == "__main__":
    unittest.main()
