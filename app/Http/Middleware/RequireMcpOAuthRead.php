<?php

namespace App\Http\Middleware;

use App\Models\McpOAuthUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The OAuth resource is completely independent from the browser Sanctum MCP.
 * Requires Passport-signed bearer token, current DB token, explicit audience
 * binding, exact public client, verified admin and ONLY mcp:read.
 */
final class RequireMcpOAuthRead
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        // Only the explicitly enabled nonproduction rehearsal may dispatch
        // a WRITE-scoped request to its own controller. The original READ
        // authorization below remains unchanged, including its exact scope.
        if (\App\Support\McpOAuthDraftScopePolicy::issuanceEnabled()
            && $user instanceof McpOAuthUser && $token instanceof AccessToken
            && $user->tokenCan('mcp:write')) {
            return app(\App\Http\Controllers\Api\McpOAuthDraftController::class)->handle($request);
        }


        abort_unless(is_string($request->bearerToken()) && $request->bearerToken() !== ''
            && $user instanceof McpOAuthUser
            && $user->isAdministrator()
            && $user->hasVerifiedEmail()
            && $token instanceof AccessToken
            && $user->tokenCan('mcp:read')
            && ! $user->tokenCan('*')
            && ! $user->tokenCan('mcp:write'), 403, 'Scoped OAuth bearer token required.');

        $id = $token->oauth_access_token_id;
        $client = $token->oauth_client_id;
        $configured = config('mcp.oauth_client_id');
        $resource = config('mcp.oauth_resource');

        abort_unless(is_string($id) && $id !== ''
            && is_string($client) && is_string($configured) && $configured !== ''
            && hash_equals($configured, $client)
            && $resource === 'https://learncambodia.com/api/mcp/oauth', 403);

        $record = DB::table('oauth_access_tokens')->where('id', $id)
            ->where('client_id', $client)->where('user_id', (string) $user->getAuthIdentifier())
            ->where('revoked', false)->where('expires_at', '>', now())->first(['id','scopes']);
        abort_unless($record !== null, 403, 'OAuth token revoked or expired.');
        $scopes = json_decode($record->scopes ?? '[]', true);
        abort_unless(is_array($scopes) && $scopes === ['mcp:read'], 403,
            'Only exact MCP read scope is permitted.');

        abort_unless(DB::table('mcp_oauth_token_audiences')->where('access_token_id', $id)
            ->where('client_id', $client)
            ->where('user_id', $user->getAuthIdentifier())
            ->where('resource', $resource)->exists(), 403,
            'OAuth token was not issued for this MCP resource.');

        return $next($request);
    }
}
