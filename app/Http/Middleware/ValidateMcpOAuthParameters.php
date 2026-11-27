<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\McpOAuthAuthorizationFailure;
use App\Support\McpOAuthDraftScopePolicy;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preflight for the SINGLE MCP resource and a PRE-REGISTERED client.
 * Passport subsequently verifies exact redirect_uri / code / PKCE signature.
 *
 * No dynamic client registration, wildcard client IDs or implicit scope grants.
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
        $resourceInput = $request->input('resource');
        $clientInput = $request->input('client_id');
        if (!is_string($resourceInput) || !hash_equals($resource, $resourceInput)
            || !is_string($clientInput) || !hash_equals($client, $clientInput)) {
            return $this->reject($phase, 'client_resource', 400);
        }
        if ($request->input('client_secret') !== null) {
            return $this->reject($phase, 'public_client', 400);
        }

        // Public ChatGPT client is pre-registered on the server. No arbitrary
        // client metadata URL, dynamic registration or confidential grant.
        $passportClient = Passport::client()->newQuery()->find($client);
        if (!($passportClient !== null && ! $passportClient->revoked && ! $passportClient->firstParty()
            && $passportClient->getRawOriginal('secret') === null
            && $passportClient->hasGrantType('authorization_code')
            && in_array($passportClient->provider, [null, 'mcp_oauth_users'], true))) {
            return $this->reject($phase, 'client_registration', 400);
        }

        if (app()->environment('production')) {
            if (!EnsureMcpOAuthStagingEnabled::productionRedirectIsValid($redirect)
                || $passportClient->redirect_uris !== [$redirect]) {
                return $this->reject($phase, 'callback_registration', 400);
            }
        }

        if ($phase === 'authorize') {
            if (!$request->isMethod('GET') || $request->query('response_type') !== 'code') {
                return $this->reject($phase, 'response_type', 400);
            }
            $callback = $request->query('redirect_uri');
            if (!is_string($callback) || !hash_equals($redirect, $callback)) {
                return $this->reject($phase, 'callback_request', 400);
            }
            $challenge = $request->query('code_challenge');
            if ($request->query('code_challenge_method') !== 'S256' || !is_string($challenge)
                || preg_match('/\\A[A-Za-z0-9_-]{43,128}\\z/D', $challenge) !== 1) {
                return $this->reject($phase, 'pkce', 400);
            }
            $state = $request->query('state');
            if (!is_string($state) || strlen($state) < 16 || strlen($state) > 512) {
                return $this->reject($phase, 'state', 400);
            }

            // Separate requested scope validity from the actual web session.
            // Never substitute the configured administrator for the signed-in user.
            $scope = $request->query('scope');
            if ($scope !== 'mcp:read') {
                if (!is_string($scope) || !McpOAuthDraftScopePolicy::issuanceEnabled()
                    || !McpOAuthDraftScopePolicy::permittedWriteScopes(explode(' ', $scope))) {
                    return $this->reject($phase, 'scope_policy', 400);
                }
                $identity = $request->user('web');
                $actor = $identity instanceof User ? User::query()->find($identity->getAuthIdentifier()) : null;
                if (!McpOAuthDraftScopePolicy::eligible($actor)) {
                    return $this->reject($phase, 'administrator_session', 400);
                }
                if (!McpOAuthDraftScopePolicy::permitsAuthorization($scope, $actor)) {
                    return $this->reject($phase, 'scope_policy', 400);
                }
            }
            // Ensure Passport's scope registry agrees with current release gates.
            // This cannot expand permissions beyond the already validated request.
            Passport::tokensCan(McpOAuthDraftScopePolicy::scopes());
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

    private function reject(string $phase, string $reason, int $status): Response
    {
        if ($phase === 'authorize') {
            return McpOAuthAuthorizationFailure::respond($reason, $status);
        }
        abort($status, 'Invalid OAuth request.');
    }
}
