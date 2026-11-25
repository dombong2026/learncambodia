<?php

namespace App\Support;

use App\Http\Middleware\EnsureMcpOAuthStagingEnabled;
use App\Models\User;

/**
 * Isolated WRITE consent/issuance rehearsal. Production is deliberately
 * excluded until the dedicated tool transport and activation review pass.
 */
final class McpOAuthDraftScopePolicy
{
    public static function issuanceEnabled(): bool
    {
        return app()->environment(['local', 'testing', 'staging'])
            && config('mcp.oauth_draft_scope_issuance_enabled', false) === true
            && config('mcp.oauth_draft_writes_accepted', false) === true
            && config('mcp.oauth_draft_writes_enabled', false) === true
            && EnsureMcpOAuthStagingEnabled::enabled();
    }

    public static function scopes(): array
    {
        $scopes = ['mcp:read' => 'Read LearnCambodia course catalog and aggregate analytics'];
        if (self::issuanceEnabled()) {
            $scopes['mcp:write'] = 'Create and update your own unpublished course, article and page drafts';
        }

        return $scopes;
    }

    public static function exactWriteScopes(array $scopes): bool
    {
        return array_is_list($scopes) && count($scopes) === 2
            && in_array('mcp:read', $scopes, true)
            && in_array('mcp:write', $scopes, true);
    }

    public static function eligible(?User $actor): bool
    {
        return $actor !== null && $actor->isAdministrator() && $actor->hasVerifiedEmail()
            && $actor->two_factor_confirmed_at !== null && $actor->hasEnabledTwoFactorAuthentication();
    }

    public static function permitsAuthorization(mixed $scope, ?User $actor): bool
    {
        if ($scope === 'mcp:read') {
            return true;
        }

        return is_string($scope)
            && self::issuanceEnabled()
            && self::exactWriteScopes(explode(' ', $scope))
            && self::eligible($actor);
    }

    public static function permitsIssuedScopes(array $scopes, ?User $actor): bool
    {
        return $scopes === ['mcp:read']
            || (self::issuanceEnabled() && self::exactWriteScopes($scopes) && self::eligible($actor));
    }
}
