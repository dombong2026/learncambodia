<?php

return [
    /*
     * The first release is a management UI only: these flags do not expose
     * or register an MCP HTTP endpoint. MCP OAuth and transport must pass
     * separate security acceptance before the endpoint can be activated.
     */
    'gateway_enabled' => (bool) env('MCP_GATEWAY_ENABLED', false),
    'remote_writes_enabled' => (bool) env('MCP_REMOTE_WRITES_ENABLED', false),
    // OAuth discovery is deliberately hidden until a separate, fully tested
    // authorization-code + S256 PKCE provider is available and approved.
    'oauth_discovery_enabled' => (bool) env('MCP_OAUTH_DISCOVERY_ENABLED', false),
    'oauth_provider_accepted' => false,
    // Production-only promotion gates. All default closed; never use staging
    // switches to activate a production authorization server.
    'oauth_production_registration_enabled' => (bool) env('MCP_OAUTH_PRODUCTION_REGISTRATION_ENABLED', false),
    'oauth_production_accepted' => (bool) env('MCP_OAUTH_PRODUCTION_ACCEPTED', false),
    'oauth_production_enabled' => (bool) env('MCP_OAUTH_PRODUCTION_ENABLED', false),
    'oauth_provider_staging_enabled' => (bool) env('MCP_OAUTH_PROVIDER_STAGING_ENABLED', false),
    'oauth_resource' => 'https://learncambodia.com/api/mcp/oauth',
    // An administrator must register an exact public client ID and callback.
    'oauth_client_id' => (string) env('MCP_OAUTH_CLIENT_ID', ''),
    'oauth_redirect_uri' => (string) env('MCP_OAUTH_REDIRECT_URI', ''),
    // OAuth draft writes are independent of the legacy Sanctum write flag.
    // Production additionally requires an explicitly accepted release and
    // one explicitly selected, verified administrator (no implicit default).
    'oauth_draft_writes_accepted' => (bool) env('MCP_OAUTH_DRAFT_WRITES_ACCEPTED', false),
    'oauth_draft_writes_enabled' => (bool) env('MCP_OAUTH_DRAFT_WRITES_ENABLED', false),
    'oauth_draft_scope_issuance_enabled' => (bool) env('MCP_OAUTH_DRAFT_SCOPE_ISSUANCE_ENABLED', false),
    'oauth_draft_production_accepted' => (bool) env('MCP_OAUTH_DRAFT_PRODUCTION_ACCEPTED', false),
    'oauth_draft_production_admin_id' => (string) env('MCP_OAUTH_DRAFT_PRODUCTION_ADMIN_ID', ''),
    // Separate production acceptance and activation; both default closed.
    'oauth_native_course_production_accepted' => (bool) env('MCP_OAUTH_NATIVE_COURSE_PRODUCTION_ACCEPTED', false),
    'oauth_native_course_production_enabled' => (bool) env('MCP_OAUTH_NATIVE_COURSE_PRODUCTION_ENABLED', false),
    // Rehearsal never authorizes production access.
    'oauth_native_course_builder_rehearsal' => (bool) env('MCP_OAUTH_NATIVE_COURSE_BUILDER_REHEARSAL', false),
    // Local-only production promotion requires independent acceptance and activation.
    'oauth_draft_media_production_accepted' => (bool) env('MCP_OAUTH_DRAFT_MEDIA_PRODUCTION_ACCEPTED', false),
    'oauth_draft_media_production_enabled' => (bool) env('MCP_OAUTH_DRAFT_MEDIA_PRODUCTION_ENABLED', false),
    // The rehearsal switch never authorizes production access.
    'oauth_draft_media_rehearsal' => (bool) env('MCP_OAUTH_DRAFT_MEDIA_REHEARSAL', false),
    // Curriculum editing requires independent production acceptance and activation.
    'oauth_curriculum_edit_production_accepted' => (bool) env('MCP_OAUTH_CURRICULUM_EDIT_PRODUCTION_ACCEPTED', false),
    'oauth_curriculum_edit_production_enabled' => (bool) env('MCP_OAUTH_CURRICULUM_EDIT_PRODUCTION_ENABLED', false),
    // Rehearsal is never an authorization for production access.
    'oauth_curriculum_edit_rehearsal' => (bool) env('MCP_OAUTH_CURRICULUM_EDIT_REHEARSAL', false),
    // Phase 5D.1 production promotion is independently accepted and enabled.
    'oauth_assessment_production_accepted' => (bool) env('MCP_OAUTH_ASSESSMENT_PRODUCTION_ACCEPTED', false),
    'oauth_assessment_production_enabled' => (bool) env('MCP_OAUTH_ASSESSMENT_PRODUCTION_ENABLED', false),
    'oauth_assessment_rehearsal' => (bool) env('MCP_OAUTH_ASSESSMENT_REHEARSAL', false),
    'oauth_assessment_audio_rehearsal' => (bool) env('MCP_OAUTH_ASSESSMENT_AUDIO_REHEARSAL', false),
    'oauth_assessment_audio_production_accepted' => (bool) env('MCP_OAUTH_ASSESSMENT_AUDIO_PRODUCTION_ACCEPTED', false),
    'oauth_assessment_audio_production_enabled' => (bool) env('MCP_OAUTH_ASSESSMENT_AUDIO_PRODUCTION_ENABLED', false),
    'oauth_assessment_advanced_rehearsal' => (bool) env('MCP_OAUTH_ASSESSMENT_ADVANCED_REHEARSAL', false),
    'oauth_assessment_advanced_production_accepted' => (bool) env('MCP_OAUTH_ASSESSMENT_ADVANCED_PRODUCTION_ACCEPTED', false),
    'oauth_assessment_advanced_production_enabled' => (bool) env('MCP_OAUTH_ASSESSMENT_ADVANCED_PRODUCTION_ENABLED', false),
    'oauth_assessment_media_rehearsal' => (bool) env('MCP_OAUTH_ASSESSMENT_MEDIA_REHEARSAL', false),
    'oauth_assessment_media_production_accepted' => (bool) env('MCP_OAUTH_ASSESSMENT_MEDIA_PRODUCTION_ACCEPTED', false),
    'oauth_assessment_media_production_enabled' => (bool) env('MCP_OAUTH_ASSESSMENT_MEDIA_PRODUCTION_ENABLED', false),
    'oauth_ready' => false,
    'transport_ready' => false,
];
