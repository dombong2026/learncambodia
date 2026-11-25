<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preflight for the SINGLE MCP resource and a PRE-REGISTERED client.
 * Passport subsequently verifies exact redirect_uri / code / PKCE signature.
 *
 * No dynamic client registration or wildcard client IDs; WRITE is rehearsal-only.
 */
final class ValidateMcpOAuthParameters
{
    public function handle(Request $request, Closure $next, string $phase): Response
    {
        $resource = config('mcp.oauth_resource');
        $client = config('mcp.oauth_client_id');
        $redirect = config('mcp.oauth_redirect_uri');
        abort_unless(is_string($resource) && $resource === 'https://learncambodia.com/api/mcp/oauth', 503);
        abort_unless(is_string($client) && $client !== '' && strlen($client) <= 128, 503);
        abort_unless(is_string($redirect) && str_starts_with($redirect, 'https://chatgpt.com/'), 503);
        abort_unless(hash_equals($resource, (string) $request->input('resource', ''))
            && hash_equals($client, (string) $request->input('client_id', '')), 400,
            'Invalid OAuth resource or client.');
        abort_unless($request->input('client_secret') === null, 400, 'Public PKCE client only.');

        // Public ChatGPT client is pre-registered on the server. No arbitrary
        // client metadata URL, dynamic registration or confidential grant.
        $passportClient = Passport::client()->newQuery()->find($client);
        abort_unless($passportClient !== null && ! $passportClient->revoked && ! $passportClient->firstParty()
            && $passportClient->getRawOriginal('secret') === null
            && $passportClient->hasGrantType('authorization_code')
            && in_array($passportClient->provider, [null, 'mcp_oauth_users'], true), 400,
            'Unregistered or unsupported OAuth client.');

        if (app()->environment('production')) {
            abort_unless(EnsureMcpOAuthStagingEnabled::productionRedirectIsValid($redirect)
                && $passportClient->redirect_uris === [$redirect], 400,
                'Production OAuth requires the exact registered callback.');
        }

        if ($phase === 'authorize') {
            abort_unless($request->isMethod('GET')
                && $request->query('response_type') === 'code'
                && \App\Support\McpOAuthDraftScopePolicy::permitsAuthorization(
                    $request->query('scope'),
                    $request->user('web') instanceof \App\Models\User ? $request->user('web') : null
                )
                && $request->query('code_challenge_method') === 'S256'
                && hash_equals($redirect, (string) $request->query('redirect_uri', '')), 400,
                'Authorization code, permitted scope, approved redirect URI and S256 required.');
            $challenge = (string) $request->query('code_challenge', '');
            abort_unless((bool) preg_match('/\A[A-Za-z0-9_-]{43,128}\z/D', $challenge), 400,
                'Valid S256 code challenge required.');
            abort_unless(is_string($request->query('state')) &&
                strlen($request->query('state')) >= 16 &&
                strlen($request->query('state')) <= 512, 400, 'OAuth state required.');
        } elseif ($phase === 'token') {
            abort_unless($request->isMethod('POST'), 405);
            $grant = $request->input('grant_type');
            abort_unless(in_array($grant, ['authorization_code', 'refresh_token'], true), 400,
                'Unsupported OAuth grant.');
            if ($grant === 'authorization_code') {
                abort_unless(hash_equals($redirect, (string) $request->input('redirect_uri', ''))
                    && is_string($request->input('code'))
                    && strlen($request->input('code')) > 16, 400, 'Invalid authorization code request.');
                $verifier = (string) $request->input('code_verifier', '');
                abort_unless((bool) preg_match('/\A[A-Za-z0-9._~-]{43,128}\z/D', $verifier), 400,
                    'PKCE code verifier is required.');
            } else {
                abort_unless(is_string($request->input('refresh_token'))
                    && strlen($request->input('refresh_token')) > 24, 400,
                    'Refresh token required.');
            }
        } else {
            abort(500, 'Invalid OAuth policy phase.');
        }

        return $next($request);
    }
}
