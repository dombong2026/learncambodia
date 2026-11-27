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

    public const CURRICULUM_SCOPE = 'mcp:curriculum';

    public static function curriculumEnabled(): bool
    {
        if (!self::issuanceEnabled()) {
            return false;
        }
        if (app()->environment('production')) {
            return config('mcp.oauth_native_course_production_accepted', false) === true
                && config('mcp.oauth_native_course_production_enabled', false) === true
                && config('mcp.oauth_native_course_builder_rehearsal', false) === false;
        }
        return app()->environment(['local', 'testing', 'staging'])
            && config('mcp.oauth_native_course_builder_rehearsal', false) === true;
    }

    public static function exactCurriculumScopes(array $scopes): bool
    {
        return array_is_list($scopes) && count($scopes) === 3
            && in_array('mcp:read', $scopes, true)
            && in_array('mcp:write', $scopes, true)
            && in_array(self::CURRICULUM_SCOPE, $scopes, true);
    }

    public static function permittedWriteScopes(array $scopes): bool
    {
        return self::exactWriteScopes($scopes)
            || (self::curriculumEnabled() && self::exactCurriculumScopes($scopes));
    }

    public static function scopes(): array
    {
        $scopes = ['mcp:read' => 'Read LearnCambodia course catalog and aggregate analytics'];
        if (self::issuanceEnabled()) {
            $scopes['mcp:write'] = 'Create and update your own unpublished course, article and page drafts';
        }

        if (self::curriculumEnabled()) {
            $scopes[self::CURRICULUM_SCOPE] = 'Append modules, lessons and knowledge checks to your own unpublished courses and attach an existing owned library image';
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
            && self::permittedWriteScopes(explode(' ', $scope))
            && self::eligible($actor);
    }

    public static function permitsIssuedScopes(array $scopes, ?User $actor): bool
    {
        return $scopes === ['mcp:read']
            || (self::issuanceEnabled() && self::permittedWriteScopes($scopes) && self::eligible($actor));
    }
}
