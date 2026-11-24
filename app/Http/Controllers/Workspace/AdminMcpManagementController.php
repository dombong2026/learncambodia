<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Support\McpToolCatalog;
use App\Models\McpActionAudit;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminMcpManagementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdministrator(), 403);

        $sessions = $request->user()->tokens()
            ->where('name', 'like', 'LearnCambodia MCP: %')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id','name','abilities','last_used_at','expires_at']);
        $audits = Schema::hasTable('mcp_action_audits')
            ? McpActionAudit::query()->where('user_id', $request->user()->id)
                ->latest('created_at')->limit(12)->get()
            : collect();

        return view('workspace.admin.mcp-management', [
            'bridgeSessions'=>$sessions,
            'bridgeAudits'=>$audits,
            'bridgeScopes'=>McpAdminSessionController::SCOPES,
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
