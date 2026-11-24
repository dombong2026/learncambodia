<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\McpToolCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class McpManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_mcp_management(): void
    {
        $this->get('/dashboard/system-settings/mcp')->assertRedirect('/login');
    }

    public function test_administrator_can_review_mcp_management_with_gateway_disabled(): void
    {
        config()->set('mcp.gateway_enabled', false);
        config()->set('mcp.remote_writes_enabled', false);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $response = $this->actingAs($admin)->get('/dashboard/system-settings/mcp');
        $response->assertOk()
            ->assertSee('LearnCambodia MCP Control Center')
            ->assertSee('Remote connection disabled')
            ->assertSee('OAuth 2.1 authorization')
            ->assertSee('Tool catalog')
            ->assertSee('campaigns.draft.create')
            ->assertSee('Not registered')
            ->assertSee('id="mcp-tool-dialog"', false);
    }

    public function test_student_and_developer_cannot_access_administration(): void
    {
        foreach ([User::ROLE_STUDENT, User::ROLE_DEVELOPER] as $role) {
            $account = User::factory()->create(['role' => $role]);
            $this->actingAs($account)->get('/dashboard/system-settings/mcp')->assertForbidden();
        }
    }

    public function test_mcp_flags_cannot_make_unimplemented_gateway_appear_connected(): void
    {
        config()->set('mcp.gateway_enabled', true);
        config()->set('mcp.remote_writes_enabled', true);
        config()->set('mcp.oauth_ready', false);
        config()->set('mcp.transport_ready', false);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get('/dashboard/system-settings/mcp')->assertOk()
            ->assertSee('Remote connection disabled')
            ->assertSee('Not configured')
            ->assertSee('Locked')
            ->assertDontSee('Ready for secure review');
    }

    public function test_tools_are_documentation_only_and_mcp_remote_routes_are_not_registered(): void
    {
        $this->assertNotEmpty(McpToolCatalog::tools());
        $this->assertFalse(Route::has('mcp.initialize'));
        $this->assertFalse(Route::has('mcp.tools.call'));
        $this->assertFalse(Route::has('mcp.oauth.authorize'));
        foreach (McpToolCatalog::tools() as $tool) {
            $this->assertNotEmpty($tool['scope']);
            $this->assertContains($tool['mode'], ['Read', 'Write', 'Publish']);
            $this->assertContains($tool['risk'], ['Low', 'Medium', 'High', 'Critical']);
        }
    }
}
