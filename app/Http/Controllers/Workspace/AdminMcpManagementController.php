<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Support\McpToolCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminMcpManagementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdministrator(), 403);

        return view('workspace.admin.mcp-management', [
            'role' => 'admin',
            'tools' => McpToolCatalog::tools(),
            'capabilities' => McpToolCatalog::capabilities(),
            'flagEnabled' => (bool) config('mcp.gateway_enabled', false),
            'writeFlagEnabled' => (bool) config('mcp.remote_writes_enabled', false),
            'oauthReady' => (bool) config('mcp.oauth_ready', false),
            'transportReady' => (bool) config('mcp.transport_ready', false),
        ]);
    }
}
