<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/**
 * Phase 2a: fail-closed OAuth discovery for the existing READ-only MCP server.
 *
 * This controller does NOT issue tokens, implement OAuth, or enable OAuth by
 * itself. The project deliberately keeps oauth_ready=false until a complete,
 * independently accepted authorization server is deployed.
 */
final class McpOAuthDiscoveryController
{
    private const ISSUER = 'https://learncambodia.com';
    private const RESOURCE = 'https://learncambodia.com/api/mcp/oauth';

    private static function enabled(): bool
    {
        if (app()->environment('production')) {
            return \App\Http\Middleware\EnsureMcpOAuthStagingEnabled::enabled()
                && Route::has('mcp.oauth.authorize')
                && Route::has('mcp.oauth.token');
        }

        return config('mcp.gateway_enabled', false)
            && config('mcp.oauth_discovery_enabled', false)
            && config('mcp.oauth_ready', false)
            && config('mcp.oauth_provider_accepted', false)
            && Route::has('mcp.oauth.authorize')
            && Route::has('mcp.oauth.token');
    }

    public function protectedResource(): JsonResponse
    {
        abort_unless(self::enabled(), 404, 'OAuth has not been activated.');

        return response()->json([
            'resource' => self::RESOURCE,
            'authorization_servers' => [self::ISSUER],
            // Limit discovery to the already accepted Phase 1 READ capability.
            'scopes_supported' => ['mcp:read'],
            'resource_documentation' => self::ISSUER.'/api/mcp/auth-guide',
        ])->header('Cache-Control', 'no-store');
    }

    public function authorizationServer(): JsonResponse
    {
        abort_unless(self::enabled(), 404, 'OAuth has not been activated.');

        // Future OAuth provider routes must be named only AFTER their
        // production security acceptance. No pretend token endpoint.
        return response()->json([
            'issuer' => self::ISSUER,
            'authorization_endpoint' => self::ISSUER.route('mcp.oauth.authorize', [], false),
            'token_endpoint' => self::ISSUER.route('mcp.oauth.token', [], false),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => ['mcp:read'],
        ])->header('Cache-Control', 'no-store');
    }
}
