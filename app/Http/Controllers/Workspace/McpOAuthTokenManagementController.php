<?php

namespace App\Http\Controllers\Workspace;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** First-party, CSRF-protected administrator revocation of OAuth credentials. */
final class McpOAuthTokenManagementController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $client = (string) config('mcp.oauth_client_id');
        $tokens = DB::table('oauth_access_tokens')
            ->where('user_id', $request->user()->id)
            ->where('client_id', $client)
            ->orderByDesc('created_at')->limit(30)
            ->get(['id', 'created_at', 'expires_at', 'revoked', 'scopes']);

        return response()->json(['tokens' => $tokens])->header('Cache-Control', 'no-store');
    }

    public function revoke(Request $request, string $token): JsonResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        abort_unless((bool) preg_match('/\A[a-zA-Z0-9_-]{10,128}\z/D', $token), 404);

        $client = (string) config('mcp.oauth_client_id');
        $user = $request->user();
        $receipt = DB::transaction(function () use ($token, $client, $user): string {
            $record = DB::table('oauth_access_tokens')
                ->where('id', $token)->where('user_id', $user->id)
                ->where('client_id', $client)->lockForUpdate()->first();
            abort_unless($record !== null, 404);

            DB::table('oauth_access_tokens')->where('id', $token)->update(['revoked' => true]);
            DB::table('oauth_refresh_tokens')->where('access_token_id', $token)->update(['revoked' => true]);
            DB::table('mcp_oauth_token_audiences')->where('access_token_id', $token)->delete();

            $receipt = (string) Str::uuid();
            DB::table('mcp_action_audits')->insert([
                'id' => $receipt, 'user_id' => $user->id, 'token_id' => null,
                'tool' => 'oauth_browser_revoke', 'operation' => 'DELETE',
                'resource' => 'mcp_oauth_token', 'resource_id' => Str::limit($token, 80, ''),
                'result' => 'success', 'http_status' => 200,
                'summary' => 'Administrator revoked a Passport MCP OAuth access token and its refresh token.',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $receipt;
        }, 3);

        return response()->json(['status' => 'revoked', 'receipt_id' => $receipt])
            ->header('Cache-Control', 'no-store');
    }
}
