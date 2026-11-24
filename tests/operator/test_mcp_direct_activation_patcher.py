#!/usr/bin/env python3
"""Regression tests for exact-site MCP browser consent and sidebar surgical overlay."""
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
PATCHER=ROOT/"support/operator/learncambodia-mcp-activation-patcher.py"
WEB=r"""<?php
Route::middleware(['auth', 'role:admin'])
    ->prefix('dashboard/system-settings')->name('admin.system-settings.')
    ->group(function (): void {
        Route::get('/mcp', [\App\Http\Controllers\Workspace\AdminMcpManagementController::class, 'index'])->name('mcp');
        Route::post('/mcp/sessions', [\App\Http\Controllers\Workspace\McpAdminSessionController::class, 'issue'])
            ->middleware(['password.confirm','throttle:5,1'])->name('mcp.sessions.issue');
        Route::delete('/mcp/sessions/{token}', [\App\Http\Controllers\Workspace\McpAdminSessionController::class, 'revoke'])
            ->whereNumber('token')->middleware('throttle:10,1')->name('mcp.sessions.revoke');
    });
"""
MENU=r"""<?php
foreach ($definitions as [$key, $title, $icon, $sectionKeys]) {
    if ($role === 'admin' && $key === 'people') {
        $items[] = ['label'=>'Unrelated'];
    }
    if ($items !== []) {
        $groups[] = compact('key', 'title', 'icon', 'items');
    }
}
"""

class ActivationPatcherTests(unittest.TestCase):
    def patch(self,web=WEB,menu=MENU):
        with tempfile.TemporaryDirectory() as d:
            stage=Path(d)
            (stage/"backup").mkdir()
            (stage/"patch").mkdir()
            (stage/"backup/route").write_text(web)
            (stage/"backup/menu").write_text(menu)
            result=subprocess.run([sys.executable,str(PATCHER),str(stage)],
                text=True,capture_output=True)
            files=[]
            for name in ("route","menu"):
                file=stage/"patch"/name
                files.append(file.read_text() if file.exists() else "")
            return result,files

    def test_route_and_menu_are_surgically_augmented(self):
        result,files=self.patch()
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertIn("MCP_PASSWORD_ROUTE_PATCH=PASS",result.stdout)
        self.assertIn("MCP_SIDEBAR_LINK_PATCH=PASS",result.stdout)
        self.assertIn("confirmWebsitePassword",files[0])
        self.assertIn("throttle:5,1",files[0])
        self.assertIn("admin.system-settings.mcp",files[1])
        self.assertIn("system-settings",files[1])
        self.assertEqual(files[0].count("mcp.confirm-password"),1)

    def test_unexpected_session_route_is_rejected(self):
        result,_=self.patch(web=WEB.replace("password.confirm","not-protected"))
        self.assertNotEqual(result.returncode,0)
        self.assertIn("MCP_SESSION_ROUTE_DRIFT",result.stderr)

    def test_duplicate_password_route_is_rejected(self):
        result,_=self.patch(web=WEB+"// mcp.confirm-password duplicate")
        self.assertNotEqual(result.returncode,0)
        self.assertIn("MCP_PASSWORD_ROUTE_ALREADY_PRESENT",result.stderr)

    def test_menu_without_known_insert_point_is_rejected(self):
        result,_=self.patch(menu=MENU.replace("if ($items !== [])", "if ($items)"))
        self.assertNotEqual(result.returncode,0)
        self.assertIn("MCP_SIDEBAR_INSERTION_DRIFT",result.stderr)

    def test_existing_sidebar_fallback_is_not_duplicated(self):
        result,files=self.patch()
        self.assertEqual(result.returncode,0)
        result2,_=self.patch(web=WEB,menu=files[1])
        self.assertNotEqual(result2.returncode,0)
        self.assertIn("MCP_SIDEBAR_FALLBACK_ALREADY_PRESENT",result2.stderr)

if __name__=="__main__":
    unittest.main()
