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
    public const MEDIA_SCOPE = 'mcp:media';
    public const EDIT_SCOPE = 'mcp:curriculum-edit';
    public const ASSESSMENT_SCOPE = 'mcp:assessment';

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

    public static function mediaEnabled(): bool
    {
        if (!self::curriculumEnabled() || !extension_loaded('gd')
            || config('filesystems.managed') !== 'local'
            || config('filesystems.disks.local.driver') !== 'local') {
            return false;
        }
        // Only the local driver is accepted in this release. R2/S3 remain
        // closed until their conditional-create and rollback paths are tested.
        if (app()->environment('production')) {
            return config('mcp.oauth_draft_media_production_accepted', false) === true
                && config('mcp.oauth_draft_media_production_enabled', false) === true
                && config('mcp.oauth_draft_media_rehearsal', false) === false;
        }
        return app()->environment(['local', 'testing', 'staging'])
            && config('mcp.oauth_draft_media_rehearsal', false) === true;
    }

    public static function exactMediaScopes(array $scopes): bool
    {
        return array_is_list($scopes) && count($scopes) === 4
            && in_array('mcp:read', $scopes, true)
            && in_array('mcp:write', $scopes, true)
            && in_array(self::CURRICULUM_SCOPE, $scopes, true)
            && in_array(self::MEDIA_SCOPE, $scopes, true);
    }

    public static function editEnabled(): bool
    {
        if (!self::curriculumEnabled()) {
            return false;
        }
        if (app()->environment('production')) {
            return config('mcp.oauth_curriculum_edit_production_accepted', false) === true
                && config('mcp.oauth_curriculum_edit_production_enabled', false) === true
                && config('mcp.oauth_curriculum_edit_rehearsal', false) === false;
        }
        return app()->environment(['local', 'testing', 'staging'])
            && config('mcp.oauth_curriculum_edit_rehearsal', false) === true;
    }

    public static function assessmentEnabled(): bool
    {
        // Production promotion and schema acceptance are a separate release.
        return app()->environment(['local', 'testing', 'staging'])
            && self::issuanceEnabled()
            && config('mcp.oauth_assessment_rehearsal', false) === true;
    }

    private static function withoutAssessment(array $scopes): array
    {
        return array_values(array_filter($scopes, static fn ($s) => $s !== self::ASSESSMENT_SCOPE));
    }

    public static function hasAssessmentScopes(array $scopes): bool
    {
        if (!self::assessmentEnabled() || !array_is_list($scopes)
            || !in_array(self::ASSESSMENT_SCOPE, $scopes, true)) {
            return false;
        }
        $remaining = self::withoutAssessment($scopes);
        return count($remaining) === count($scopes) - 1 && self::permittedWriteScopes($remaining);
    }

    public static function hasEditScopes(array $scopes): bool
    {
        if (self::hasAssessmentScopes($scopes)) {
            return self::hasEditScopes(self::withoutAssessment($scopes));
        }
        if (!self::editEnabled() || !array_is_list($scopes)
            || !in_array(self::EDIT_SCOPE, $scopes, true)) {
            return false;
        }
        $remaining = array_values(array_filter($scopes, static fn ($s) => $s !== self::EDIT_SCOPE));
        return count($remaining) === count($scopes) - 1
            && (self::exactCurriculumScopes($remaining)
                || (self::mediaEnabled() && self::exactMediaScopes($remaining)));
    }

    public static function hasMediaScopes(array $scopes): bool
    {
        if (self::hasAssessmentScopes($scopes)) {
            return self::hasMediaScopes(self::withoutAssessment($scopes));
        }
        return self::mediaEnabled() && (self::exactMediaScopes($scopes)
            || (self::hasEditScopes($scopes) && in_array(self::MEDIA_SCOPE, $scopes, true)));
    }

    public static function hasCurriculumScopes(array $scopes): bool
    {
        if (self::hasAssessmentScopes($scopes)) {
            return self::hasCurriculumScopes(self::withoutAssessment($scopes));
        }
        return self::exactCurriculumScopes($scopes)
            || self::hasMediaScopes($scopes)
            || self::hasEditScopes($scopes);
    }

    public static function permittedWriteScopes(array $scopes): bool
    {
        return self::hasAssessmentScopes($scopes) || self::exactWriteScopes($scopes)
            || (self::curriculumEnabled() && self::hasCurriculumScopes($scopes));
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

        if (self::mediaEnabled()) {
            $scopes[self::MEDIA_SCOPE] = 'Upload one validated image as the cover of your own unpublished course without replacing existing media';
        }

        if (self::editEnabled()) {
            $scopes[self::EDIT_SCOPE] = 'Inspect, edit, append, reorder and duplicate native modules and plain-text lessons in your own unpublished courses, including lesson knowledge checks';
        }

        if (self::assessmentEnabled()) {
            $scopes[self::ASSESSMENT_SCOPE] = 'Create, inspect and edit your own MCP-created standalone quiz and exam drafts, including answer keys and feedback';
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
