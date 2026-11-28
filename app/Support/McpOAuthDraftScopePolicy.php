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
    public const ASSESSMENT_MEDIA_SCOPE = 'mcp:assessment-media';
    public const ASSESSMENT_ADVANCED_SCOPE = 'mcp:assessment-advanced';
    public const ASSESSMENT_AUDIO_SCOPE = 'mcp:assessment-audio';
    public const ASSESSMENT_GUEST_SCOPE = 'mcp:assessment-guest';

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
        if (!self::issuanceEnabled()) {
            return false;
        }
        if (app()->environment('production')) {
            return config('mcp.oauth_assessment_production_accepted', false) === true
                && config('mcp.oauth_assessment_production_enabled', false) === true
                && config('mcp.oauth_assessment_rehearsal', false) === false;
        }
        return app()->environment(['local', 'testing', 'staging'])
            && config('mcp.oauth_assessment_rehearsal', false) === true;
    }

    public static function assessmentMediaEnabled(): bool
    {
        if (!self::assessmentEnabled() || !self::mediaEnabled()) {
            return false;
        }
        if (app()->environment('production')) {
            return config('mcp.oauth_assessment_media_production_accepted', false) === true
                && config('mcp.oauth_assessment_media_production_enabled', false) === true
                && config('mcp.oauth_assessment_media_rehearsal', false) === false;
        }
        return app()->environment(['local', 'testing', 'staging'])
            && config('mcp.oauth_assessment_media_rehearsal', false) === true;
    }

    public static function assessmentAdvancedEnabled(): bool
    {
        if (!self::assessmentEnabled()) {
            return false;
        }
        if (app()->environment('production')) {
            return config('mcp.oauth_assessment_advanced_production_accepted', false) === true
                && config('mcp.oauth_assessment_advanced_production_enabled', false) === true
                && config('mcp.oauth_assessment_advanced_rehearsal', false) === false;
        }
        return app()->environment(['local', 'testing', 'staging'])
            && config('mcp.oauth_assessment_advanced_rehearsal', false) === true;
    }

    public static function assessmentAudioEnabled(): bool
    {
        if (!self::assessmentAdvancedEnabled() || !extension_loaded('fileinfo')
            || config('filesystems.managed') !== 'local'
            || config('filesystems.disks.local.driver') !== 'local') {
            return false;
        }
        if (app()->environment('production')) {
            return config('mcp.oauth_assessment_audio_production_accepted', false) === true
                && config('mcp.oauth_assessment_audio_production_enabled', false) === true
                && config('mcp.oauth_assessment_audio_rehearsal', false) === false;
        }
        return app()->environment(['local', 'testing', 'staging'])
            && config('mcp.oauth_assessment_audio_rehearsal', false) === true;
    }

    public static function assessmentGuestEnabled(): bool
    {
        if (!self::assessmentEnabled() || !GuestAssessment::enabled()) {
            return false;
        }
        if (app()->environment('production')) {
            return config('mcp.oauth_assessment_guest_production_accepted', false) === true
                && config('mcp.oauth_assessment_guest_production_enabled', false) === true
                && config('mcp.oauth_assessment_guest_rehearsal', false) === false;
        }
        return app()->environment(['local', 'testing', 'staging'])
            && config('mcp.oauth_assessment_guest_rehearsal', false) === true;
    }

    private static function withoutAssessmentGuest(array $scopes): array
    {
        return array_values(array_filter($scopes, static fn ($s) => $s !== self::ASSESSMENT_GUEST_SCOPE));
    }

    public static function hasAssessmentGuestScopes(array $scopes): bool
    {
        if (!self::assessmentGuestEnabled() || !array_is_list($scopes)
            || !in_array(self::ASSESSMENT_GUEST_SCOPE, $scopes, true)) {
            return false;
        }
        $remaining = self::withoutAssessmentGuest($scopes);
        return count($remaining) === count($scopes) - 1 && self::hasAssessmentScopes($remaining);
    }

    private static function withoutAssessmentAudio(array $scopes): array
    {
        return array_values(array_filter($scopes, static fn ($s) => $s !== self::ASSESSMENT_AUDIO_SCOPE));
    }

    public static function hasAssessmentAudioScopes(array $scopes): bool
    {
        if (self::hasAssessmentGuestScopes($scopes)) {
            return self::hasAssessmentAudioScopes(self::withoutAssessmentGuest($scopes));
        }
        if (!self::assessmentAudioEnabled() || !array_is_list($scopes)
            || !in_array(self::ASSESSMENT_AUDIO_SCOPE, $scopes, true)) {
            return false;
        }
        $remaining = self::withoutAssessmentAudio($scopes);
        return count($remaining) === count($scopes) - 1 && self::hasAssessmentAdvancedScopes($remaining);
    }

    private static function withoutAssessmentAdvanced(array $scopes): array
    {
        return array_values(array_filter($scopes, static fn ($s) => $s !== self::ASSESSMENT_ADVANCED_SCOPE));
    }

    public static function hasAssessmentAdvancedScopes(array $scopes): bool
    {
        if (self::hasAssessmentGuestScopes($scopes)) {
            return self::hasAssessmentAdvancedScopes(self::withoutAssessmentGuest($scopes));
        }
        if (self::hasAssessmentAudioScopes($scopes)) {
            return self::hasAssessmentAdvancedScopes(self::withoutAssessmentAudio($scopes));
        }
        if (!self::assessmentAdvancedEnabled() || !array_is_list($scopes)
            || !in_array(self::ASSESSMENT_ADVANCED_SCOPE, $scopes, true)) {
            return false;
        }
        $remaining = self::withoutAssessmentAdvanced($scopes);
        return count($remaining) === count($scopes) - 1 && self::hasAssessmentScopes($remaining);
    }

    private static function withoutAssessmentMedia(array $scopes): array
    {
        return array_values(array_filter($scopes, static fn ($s) => $s !== self::ASSESSMENT_MEDIA_SCOPE));
    }

    public static function hasAssessmentMediaScopes(array $scopes): bool
    {
        if (self::hasAssessmentGuestScopes($scopes)) {
            return self::hasAssessmentMediaScopes(self::withoutAssessmentGuest($scopes));
        }
        if (self::hasAssessmentAudioScopes($scopes)) {
            return self::hasAssessmentMediaScopes(self::withoutAssessmentAudio($scopes));
        }
        if (self::hasAssessmentAdvancedScopes($scopes)) {
            return self::hasAssessmentMediaScopes(self::withoutAssessmentAdvanced($scopes));
        }
        if (!self::assessmentMediaEnabled() || !array_is_list($scopes)
            || !in_array(self::ASSESSMENT_MEDIA_SCOPE, $scopes, true)) {
            return false;
        }
        $remaining = self::withoutAssessmentMedia($scopes);
        return count($remaining) === count($scopes) - 1
            && self::hasAssessmentScopes($remaining)
            && self::hasMediaScopes($remaining);
    }

    private static function withoutAssessment(array $scopes): array
    {
        return array_values(array_filter($scopes, static fn ($s) => $s !== self::ASSESSMENT_SCOPE));
    }

    public static function hasAssessmentScopes(array $scopes): bool
    {
        if (self::hasAssessmentGuestScopes($scopes)) {
            return self::hasAssessmentScopes(self::withoutAssessmentGuest($scopes));
        }
        if (self::hasAssessmentAudioScopes($scopes)) {
            return self::hasAssessmentScopes(self::withoutAssessmentAudio($scopes));
        }
        if (self::hasAssessmentAdvancedScopes($scopes)) {
            return self::hasAssessmentScopes(self::withoutAssessmentAdvanced($scopes));
        }
        if (self::hasAssessmentMediaScopes($scopes)) {
            return self::hasAssessmentScopes(self::withoutAssessmentMedia($scopes));
        }
        if (!self::assessmentEnabled() || !array_is_list($scopes)
            || !in_array(self::ASSESSMENT_SCOPE, $scopes, true)) {
            return false;
        }
        $remaining = self::withoutAssessment($scopes);
        return count($remaining) === count($scopes) - 1 && self::permittedWriteScopes($remaining);
    }

    public static function hasEditScopes(array $scopes): bool
    {
        if (self::hasAssessmentGuestScopes($scopes)) {
            return self::hasEditScopes(self::withoutAssessmentGuest($scopes));
        }
        if (self::hasAssessmentAudioScopes($scopes)) {
            return self::hasEditScopes(self::withoutAssessmentAudio($scopes));
        }
        if (self::hasAssessmentAdvancedScopes($scopes)) {
            return self::hasEditScopes(self::withoutAssessmentAdvanced($scopes));
        }
        if (self::hasAssessmentMediaScopes($scopes)) {
            return self::hasEditScopes(self::withoutAssessmentMedia($scopes));
        }
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
        if (self::hasAssessmentGuestScopes($scopes)) {
            return self::hasMediaScopes(self::withoutAssessmentGuest($scopes));
        }
        if (self::hasAssessmentAudioScopes($scopes)) {
            return self::hasMediaScopes(self::withoutAssessmentAudio($scopes));
        }
        if (self::hasAssessmentAdvancedScopes($scopes)) {
            return self::hasMediaScopes(self::withoutAssessmentAdvanced($scopes));
        }
        if (self::hasAssessmentMediaScopes($scopes)) {
            return self::hasMediaScopes(self::withoutAssessmentMedia($scopes));
        }
        if (self::hasAssessmentScopes($scopes)) {
            return self::hasMediaScopes(self::withoutAssessment($scopes));
        }
        return self::mediaEnabled() && (self::exactMediaScopes($scopes)
            || (self::hasEditScopes($scopes) && in_array(self::MEDIA_SCOPE, $scopes, true)));
    }

    public static function hasCurriculumScopes(array $scopes): bool
    {
        if (self::hasAssessmentGuestScopes($scopes)) {
            return self::hasCurriculumScopes(self::withoutAssessmentGuest($scopes));
        }
        if (self::hasAssessmentAudioScopes($scopes)) {
            return self::hasCurriculumScopes(self::withoutAssessmentAudio($scopes));
        }
        if (self::hasAssessmentAdvancedScopes($scopes)) {
            return self::hasCurriculumScopes(self::withoutAssessmentAdvanced($scopes));
        }
        if (self::hasAssessmentMediaScopes($scopes)) {
            return self::hasCurriculumScopes(self::withoutAssessmentMedia($scopes));
        }
        if (self::hasAssessmentScopes($scopes)) {
            return self::hasCurriculumScopes(self::withoutAssessment($scopes));
        }
        return self::exactCurriculumScopes($scopes)
            || self::hasMediaScopes($scopes)
            || self::hasEditScopes($scopes);
    }

    public static function permittedWriteScopes(array $scopes): bool
    {
        return self::hasAssessmentGuestScopes($scopes) || self::hasAssessmentAudioScopes($scopes) || self::hasAssessmentAdvancedScopes($scopes) || self::hasAssessmentMediaScopes($scopes) || self::hasAssessmentScopes($scopes) || self::exactWriteScopes($scopes)
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

        if (self::assessmentMediaEnabled()) {
            $scopes[self::ASSESSMENT_MEDIA_SCOPE] = 'Upload one validated cover image to your own MCP-created unpublished standalone quiz or exam with no existing cover';
        }

        if (self::assessmentAdvancedEnabled()) {
            $scopes[self::ASSESSMENT_ADVANCED_SCOPE] = 'Append native listening, writing, speaking and unscored Likert questions to your own unpublished MCP-created standalone assessments, and list your own exam audio';
        }

        if (self::assessmentAudioEnabled()) {
            $scopes[self::ASSESSMENT_AUDIO_SCOPE] = 'Upload validated PCM WAV audio and append a listening question to your own unpublished MCP-created standalone assessment';
        }

        if (self::assessmentGuestEnabled()) {
            $scopes[self::ASSESSMENT_GUEST_SCOPE] = 'Enable or disable guest participation on your own unpublished free standalone assessment; no publication or access to learner responses';
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
