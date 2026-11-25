<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class McpOAuthDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function readyExceptProvider(): void
    {
        config()->set('mcp.gateway_enabled', true);
        config()->set('mcp.oauth_discovery_enabled', true);
        config()->set('mcp.oauth_ready', true);
        // A real OAuth authorization-code provider must be security accepted
        // before this is ever set to true in the production configuration.
        config()->set('mcp.oauth_provider_accepted', false);
    }

    private function fakeProviderRoutes(): void
    {
        // Route *names* only for isolated metadata tests.
        // These never issue credentials, authenticate, or grant permissions.
        Route::get('/__oauth-metadata-test/authorize', fn () => response('fixture'))
            ->name('mcp.oauth.authorize');
        Route::post('/__oauth-metadata-test/token', fn () => response('fixture'))
            ->name('mcp.oauth.token');
        // Refresh the route collection's lookup cache for routes created
        // after Laravel booted in this isolated fixture.
        Route::getRoutes()->refreshNameLookups();
        $this->assertTrue(Route::has('mcp.oauth.authorize'));
        $this->assertTrue(Route::has('mcp.oauth.token'));
    }

    public function test_discovery_is_not_advertised_by_default(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource')->assertNotFound();
        $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
        $this->assertFalse(config('mcp.oauth_discovery_enabled'));
        $this->assertFalse(config('mcp.oauth_ready'));
        $this->assertFalse(config('mcp.oauth_provider_accepted'));
    }

    public function test_flag_and_metadata_are_insufficient_without_approved_provider(): void
    {
        $this->readyExceptProvider();
        $this->fakeProviderRoutes();
        $this->getJson('/.well-known/oauth-protected-resource')->assertNotFound();
        $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
    }

    public function test_oauth_is_not_advertised_while_discovery_switch_is_disabled(): void
    {
        $this->readyExceptProvider();
        config()->set('mcp.oauth_provider_accepted', true);
        config()->set('mcp.oauth_discovery_enabled', false);
        $this->assertTrue(Route::has('mcp.oauth.authorize'));
        $this->assertTrue(Route::has('mcp.oauth.token'));
        $this->getJson('/.well-known/oauth-protected-resource')->assertNotFound();
        $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
    }

    public function test_accepted_provider_has_only_read_scopes_and_canonical_metadata(): void
    {
        $this->readyExceptProvider();
        config()->set('mcp.oauth_provider_accepted', true);

        $resource=$this->getJson('/.well-known/oauth-protected-resource')
            ->assertOk()->assertJsonPath('resource', 'https://learncambodia.com/api/mcp/oauth')
            ->assertJsonPath('authorization_servers.0', 'https://learncambodia.com')
            ->assertJsonPath('scopes_supported.0', 'mcp:read')
            ->assertJsonCount(1, 'scopes_supported');
        $this->assertStringContainsString('no-store', $resource->headers->get('Cache-Control'));

        $auth=$this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()->assertJsonPath('issuer', 'https://learncambodia.com')
            ->assertJsonPath('authorization_endpoint',
                'https://learncambodia.com/mcp/oauth/authorize')
            ->assertJsonPath('token_endpoint',
                'https://learncambodia.com/api/mcp/oauth/token')
            ->assertJsonPath('code_challenge_methods_supported.0', 'S256')
            ->assertJsonPath('token_endpoint_auth_methods_supported.0', 'none')
            ->assertJsonCount(1, 'scopes_supported');

        $this->assertStringContainsString('no-store', $auth->headers->get('Cache-Control'));
        $this->assertArrayNotHasKey('registration_endpoint', $auth->json());
        $this->assertArrayNotHasKey('client_id_metadata_document_supported', $auth->json());
        $this->assertArrayNotHasKey('authorization_response_iss_parameter_supported', $auth->json());
        $this->assertContains('refresh_token', $auth->json('grant_types_supported'));
        $this->assertNotContains('mcp:write', $auth->json('scopes_supported'));
    }

    public function test_production_discovery_write_scope_requires_every_independent_gate(): void
    {
        config()->set('mcp.gateway_enabled', true);
        config()->set('mcp.oauth_production_accepted', true);
        config()->set('mcp.oauth_production_enabled', true);
        config()->set('mcp.oauth_discovery_enabled', true);
        config()->set('mcp.oauth_production_registration_enabled', false);
        config()->set('mcp.oauth_provider_staging_enabled', false);
        config()->set('mcp.oauth_client_id', '00000000-0000-4000-8000-000000000001');
        config()->set('mcp.oauth_redirect_uri', 'https://chatgpt.com/connector/oauth/testcallback123');
        $gates = [
            'oauth_draft_writes_accepted',
            'oauth_draft_writes_enabled',
            'oauth_draft_scope_issuance_enabled',
            'oauth_draft_production_accepted',
        ];
        $assertScopes = function (array $scopes): void {
            foreach (['/.well-known/oauth-protected-resource',
                '/.well-known/oauth-protected-resource/api/mcp/oauth',
                '/.well-known/oauth-authorization-server'] as $path) {
                $response = $this->getJson($path)->assertOk()
                    ->assertJsonPath('scopes_supported', $scopes);
                $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            }
        };
        $this->app->instance('env', 'production');
        try {
            foreach ($gates as $gate) {
                $this->assertFalse(config('mcp.'.$gate));
            }
            $this->assertSame('', config('mcp.oauth_draft_production_admin_id'));
            $assertScopes(['mcp:read']);
            foreach ($gates as $gate) {
                config()->set('mcp.'.$gate, true);
            }
            $assertScopes(['mcp:read']);
            foreach (['0', '-1', 'not-an-id'] as $invalid) {
                config()->set('mcp.oauth_draft_production_admin_id', $invalid);
                $this->assertFalse(\App\Support\McpOAuthDraftScopePolicy::issuanceEnabled());
            }
            config()->set('mcp.oauth_draft_production_admin_id', '123');
            $assertScopes(['mcp:read', 'mcp:write']);
            foreach ($gates as $gate) {
                config()->set('mcp.'.$gate, false);
                $assertScopes(['mcp:read']);
                config()->set('mcp.'.$gate, true);
            }
            config()->set('mcp.oauth_draft_production_admin_id', '');
            $assertScopes(['mcp:read']);
        } finally {
            foreach ($gates as $gate) {
                config()->set('mcp.'.$gate, false);
            }
            config()->set('mcp.oauth_draft_production_admin_id', '');
            $this->app->instance('env', 'testing');
        }
    }

    public function test_discovery_url_cannot_be_poisoned_by_host_header(): void
    {
        $this->readyExceptProvider();
        config()->set('mcp.oauth_provider_accepted', true);
        $response=$this->withHeaders(['Host'=>'untrusted.example'])
            ->getJson('/.well-known/oauth-authorization-server')
            ->assertOk();
        $this->assertSame('https://learncambodia.com',
            $response->json('issuer'));
        $this->assertSame('https://learncambodia.com/api/mcp/oauth/token',
            $response->json('token_endpoint'));
    }

    public function test_existing_anonymous_mcp_bridge_remains_protected(): void
    {
        config()->set('mcp.gateway_enabled', true);
        $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
        ])->assertUnauthorized();
    }
}
