<?php

namespace App\Support;

use App\Http\Middleware\EnsureMcpOAuthStagingEnabled;
use App\Models\User;

/**
 * Draft WRITE consent/issuance requires independent gates. Production also
 * requires release acceptance and an explicitly selected administrator.
 */
final class McpOAuthDraftScopePolicy
{
    public static function issuanceEnabled(): bool
    {
        if (config('mcp.oauth_draft_scope_issuance_enabled', false) !== true
            || config('mcp.oauth_draft_writes_accepted', false) !== true
            || config('mcp.oauth_draft_writes_enabled', false) !== true
            || ! EnsureMcpOAuthStagingEnabled::enabled()) {
            return false;
        }
        if (app()->environment('production')) {
            $admin = config('mcp.oauth_draft_production_admin_id', '');
            return config('mcp.oauth_draft_production_accepted', false) === true
                && is_string($admin) && preg_match('/\A[1-9][0-9]{0,18}\z/D', $admin) === 1;
        }

        return app()->environment(['local', 'testing', 'staging']);
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
            && $actor->two_factor_confirmed_at !== null && $actor->hasEnabledTwoFactorAuthentication()
            && (! app()->environment('production')
                || (string) $actor->getAuthIdentifier() === config('mcp.oauth_draft_production_admin_id', ''));
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
