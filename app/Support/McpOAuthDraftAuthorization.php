<?php

namespace App\Support;

use App\Http\Middleware\EnsureMcpOAuthStagingEnabled;
use App\Models\McpOAuthUser;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;

/**
 * Internal adapter, NOT registered as an HTTP route or MCP tool.
 *
 * A later reviewed consent/issuance release may call this adapter only
 * after explicitly enabling both independent write gates. READ tokens,
 * the legacy Sanctum bridge and ordinary web sessions cannot authorize it.
 */
final class McpOAuthDraftAuthorization
{
    public static function execute(Request $request, string $operation, string $resource, array $payload, string $requestId): array
    {
        return self::authorized($request, fn (User $actor): array =>
            McpOAuthDraftWriter::execute($actor, $operation, $resource, $payload, $requestId));
    }

    /** Invoke only a trusted, server-defined callback under the authorization locks. */
    public static function authorized(Request $request, Closure $action): mixed
    {
        abort_unless(! app()->environment('production') || McpOAuthDraftScopePolicy::issuanceEnabled(), 403);
        abort_unless(config('mcp.oauth_draft_writes_accepted', false) === true
            && config('mcp.oauth_draft_writes_enabled', false) === true
            && EnsureMcpOAuthStagingEnabled::enabled(), 403, 'OAuth draft writes are disabled.');

        abort_unless($request === app('request') && is_string($request->bearerToken())
            && $request->bearerToken() !== '', 401);
        // Resolve the dedicated Passport guard, not the default web guard
        // or a caller-supplied user ID. This also validates the signed JWT.
        $identity = Auth::guard('mcp_oauth')->user();
        $token = $identity?->currentAccessToken();
        abort_unless($identity instanceof McpOAuthUser && $token instanceof AccessToken
            && $identity->tokenCan('mcp:read') && $identity->tokenCan('mcp:write')
            && ! $identity->tokenCan('*'), 403);

        $id = $token->oauth_access_token_id;
        $client = $token->oauth_client_id;
        $configured = config('mcp.oauth_client_id');
        $audience = config('mcp.oauth_resource');
        abort_unless(is_string($id) && $id !== '' && is_string($client)
            && is_string($configured) && $configured !== '' && hash_equals($configured, $client)
            && $audience === 'https://learncambodia.com/api/mcp/oauth', 403);

        // Hold authorization records until the draft transaction commits.
        return DB::transaction(function () use ($identity, $id, $client, $audience, $action): mixed {
            $registered = Passport::client()->newQuery()->lockForUpdate()->find($client);
            abort_unless($registered !== null && ! $registered->revoked && ! $registered->firstParty()
                && $registered->getRawOriginal('secret') === null
                && $registered->hasGrantType('authorization_code')
                && in_array($registered->provider, [null, 'mcp_oauth_users'], true)
                && $registered->redirect_uris === [config('mcp.oauth_redirect_uri')], 403);

            $record = DB::table('oauth_access_tokens')->where('id', $id)
                ->where('client_id', $client)->where('user_id', (string) $identity->getAuthIdentifier())
                ->where('revoked', false)->where('expires_at', '>', now())
                ->lockForUpdate()->first(['id', 'scopes']);
            abort_unless($record !== null, 403);
            $scopes = json_decode($record->scopes ?? '[]', true);
            abort_unless(is_array($scopes) && array_is_list($scopes)
                && count($scopes) === 2 && in_array('mcp:read', $scopes, true)
                && in_array('mcp:write', $scopes, true), 403, 'Exact draft write scopes required.');

            $binding = DB::table('mcp_oauth_token_audiences')->where('access_token_id', $id)
                ->where('client_id', $client)->where('user_id', $identity->getAuthIdentifier())
                ->where('resource', $audience)->lockForUpdate()->first();
            abort_unless($binding !== null, 403);

            $actor = User::query()->lockForUpdate()->findOrFail($identity->getAuthIdentifier());
            abort_unless(McpOAuthDraftScopePolicy::eligible($actor), 403);
            return $action($actor);
        });
    }
}
