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

    public function test_admin_sidebar_includes_mcp_even_when_cached_workspace_config_omits_it(): void
    {
        $config=config('workspaces.admin');
        foreach ($config as &$section) {
            if (($section['key']??'')==='system-settings') {
                $section['items']=array_values(array_filter($section['items'],fn(array $item):bool =>
                    ($item['route']??'')!=='admin.system-settings.mcp'
                ));
            }
        }
        unset($section);
        config()->set('workspaces.admin',$config);
        $this->actingAs(User::factory()->create(['role'=>User::ROLE_ADMIN]));
        $groups=\App\Support\WorkspaceMenu::groups('admin');
        $settings=collect($groups)->firstWhere('key','system-settings');
        $this->assertNotNull($settings);
        $matches=array_values(array_filter($settings['items'],fn(array $item):bool =>
            ($item['route']??'')==='admin.system-settings.mcp'
        ));
        $this->assertCount(1,$matches);
        $this->assertSame('MCP Management',$matches[0]['label']);
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
            ->assertSee('Direct bearer bridge enabled')
            ->assertSee('OAuth pending')
            ->assertSee('Not configured')
            ->assertSee('Scoped writes enabled')
            ->assertDontSee('Ready for secure review');
    }

    public function test_tools_are_documentation_only_and_mcp_remote_routes_are_not_registered(): void
    {
        $this->assertNotEmpty(McpToolCatalog::tools());
        $this->assertFalse(Route::has('mcp.initialize'));
        $this->assertFalse(Route::has('mcp.tools.call'));
        $this->assertTrue(Route::has('mcp.oauth.authorize'));
        // The Phase 2B route exists but MUST remain inaccessible until
        // security acceptance explicitly opens the OAuth stage gates.
        // Web routes redirect guests to login before evaluating the OAuth
        // staging gate. The public token route must stay completely closed.
        $this->get('/mcp/oauth/authorize')->assertRedirect('/login');
        $this->postJson('/api/mcp/oauth/token', [
            'grant_type' => 'authorization_code',
        ])->assertNotFound();
        foreach (McpToolCatalog::tools() as $tool) {
            $this->assertNotEmpty($tool['scope']);
            $this->assertContains($tool['mode'], ['Read', 'Write', 'Publish']);
            $this->assertContains($tool['risk'], ['Low', 'Medium', 'High', 'Critical']);
        }
    }

    private function draftActor(string $role = User::ROLE_ADMIN, bool $secondFactor = true): User
    {
        $actor = User::factory()->create(['role' => $role, 'email_verified_at' => now()]);
        if ($secondFactor) {
            $actor->forceFill([
                'two_factor_secret' => encrypt('TEST-ONLY-BASE32-SECRET'),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }
        return $actor;
    }

    private function draftRejected(callable $action, int $status): void
    {
        try {
            $action();
            $this->fail('Expected the draft mutation to be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
    }

    public function test_oauth_draft_writer_is_closed_and_does_not_register_a_remote_route(): void
    {
        $actor = $this->draftActor();
        $fields = ['title' => 'Draft test', 'summary' => 'Summary', 'body' => 'Body'];
        $run = fn (User $user) => \App\Support\McpOAuthDraftWriter::execute(
            $user, 'CREATE', 'articles', $fields, (string) \Illuminate\Support\Str::uuid()
        );
        $this->draftRejected(fn () => $run($actor), 403);
        config()->set('mcp.oauth_draft_writes_enabled', true);
        $this->draftRejected(fn () => $run($this->draftActor(User::ROLE_STUDENT)), 403);
        $this->draftRejected(fn () => $run($this->draftActor(User::ROLE_ADMIN, false)), 403);
        $this->assertDatabaseCount('mcp_oauth_draft_receipts', 0);
        $this->assertFalse(Route::has('mcp.oauth.write'));
    }

    public function test_oauth_draft_writer_replay_ownership_version_and_publication_boundary(): void
    {
        config()->set('mcp.oauth_draft_writes_enabled', true);
        $actor = $this->draftActor();
        $other = $this->draftActor();
        $uuid = fn () => (string) \Illuminate\Support\Str::uuid();
        $fields = ['title' => 'Draft test', 'summary' => 'Summary', 'body' => 'Body'];
        $run = fn (User $user, string $operation, array $payload, string $id) =>
            \App\Support\McpOAuthDraftWriter::execute($user, $operation, 'articles', $payload, $id);
        $id = $uuid();
        $created = $run($actor, 'CREATE', $fields, $id);
        $this->assertSame('created', $created['status']);
        $this->assertFalse($created['replayed']);
        $replay = $run($actor, 'CREATE', $fields, $id);
        $this->assertTrue($replay['replayed']);
        $this->assertSame($created['resource_id'], $replay['resource_id']);
        $this->assertDatabaseCount('mcp_oauth_draft_receipts', 1);
        $this->assertDatabaseCount('mcp_action_audits', 1);
        $this->draftRejected(fn () => $run($actor, 'CREATE', array_replace($fields, ['title' => 'Other']), $id), 409);
        $this->draftRejected(fn () => $run($other, 'CREATE', $fields, $id), 409);
        $update = array_replace($fields, ['title' => 'Updated']) + [
            'id' => $created['resource_id'], 'expected_version' => $created['draft_version'],
        ];
        $this->draftRejected(fn () => $run($other, 'UPDATE', $update, $uuid()), 403);
        $updated = $run($actor, 'UPDATE', $update, $uuid());
        $this->assertNotSame($created['draft_version'], $updated['draft_version']);
        $this->draftRejected(fn () => $run($actor, 'UPDATE', $update, $uuid()), 409);
        $record = \App\Models\ContentEntry::findOrFail($created['resource_id']);
        $this->assertSame('draft', $record->status);
        $this->assertNull($record->published_at);
        $record->forceFill(['status' => 'published', 'published_at' => now()])->save();
        $this->draftRejected(fn () => $run($actor, 'UPDATE', array_replace($update, [
            'expected_version' => $updated['draft_version'],
        ]), $uuid()), 403);
        $this->assertDatabaseCount('mcp_oauth_draft_receipts', 2);
        $this->assertDatabaseCount('mcp_action_audits', 2);
        $this->assertDatabaseHas('content_entries', ['id' => $record->id, 'title' => 'Updated', 'status' => 'published']);
    }

    public function test_oauth_draft_writer_rejects_publication_fields(): void
    {
        config()->set('mcp.oauth_draft_writes_enabled', true);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        \App\Support\McpOAuthDraftWriter::execute(
            $this->draftActor(), 'CREATE', 'pages',
            ['title' => 'Draft page', 'summary' => 'Summary', 'body' => 'Body', 'status' => 'published'],
            (string) \Illuminate\Support\Str::uuid()
        );
    }

    public function test_oauth_draft_writer_creates_course_and_page_without_publishing(): void
    {
        config()->set('mcp.oauth_draft_writes_enabled', true);
        $actor = $this->draftActor();
        $category = \App\Models\Category::create([
            'code' => 'DRAFT', 'slug' => 'draft-test-category', 'name' => 'Draft test category',
            'group' => 'english-courses', 'description' => 'Isolated fixture', 'is_published' => true,
            'sort_order' => 1,
        ]);
        $course = \App\Support\McpOAuthDraftWriter::execute($actor, 'CREATE', 'courses', [
            'title' => 'Draft course', 'summary' => 'Summary', 'description' => 'Plain text',
            'category_id' => $category->id, 'delivery_mode' => 'online',
        ], (string) \Illuminate\Support\Str::uuid());
        $page = \App\Support\McpOAuthDraftWriter::execute($actor, 'CREATE', 'pages', [
            'title' => 'Draft page', 'summary' => 'Summary', 'body' => 'Plain text',
        ], (string) \Illuminate\Support\Str::uuid());
        $this->assertDatabaseHas('courses', [
            'id' => $course['resource_id'], 'status' => 'draft', 'published_at' => null,
        ]);
        $this->assertDatabaseHas('content_entries', [
            'id' => $page['resource_id'], 'type' => 'page', 'status' => 'draft', 'published_at' => null,
        ]);
    }
}
