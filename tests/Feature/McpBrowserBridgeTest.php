<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\McpActionApproval;
use App\Models\McpActionAudit;
use App\Models\User;
use App\Support\McpActionConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

final class McpBrowserBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create(['role'=>User::ROLE_ADMIN]);
    }

    private function rpc(string $name,array $args=[]): array
    {
        return ['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call',
            'params'=>['name'=>$name,'arguments'=>$args]];
    }

    public function test_guest_cannot_issue_browser_admin_session(): void
    {
        $this->postJson('/dashboard/system-settings/mcp/sessions',[
            'client_name'=>'Browser Client','scopes'=>['mcp:read'],
            'confirmation'=>'CONNECT','expires_in_minutes'=>15,
        ])->assertUnauthorized();
    }

    public function test_website_admin_issues_read_only_token_with_expiry_and_revokes_it(): void
    {
        $admin=$this->admin();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at'=>time()]);
        $issued=$this->postJson('/dashboard/system-settings/mcp/sessions',[
            'client_name'=>'Read Only Test','scopes'=>['mcp:read'],
            'confirmation'=>'CONNECT','expires_in_minutes'=>15,
        ])->assertCreated()->json();
        $this->assertStringContainsString('|',$issued['access_token']);
        $this->assertSame(['mcp:read'],$issued['scopes']);
        $record=$admin->tokens()->findOrFail($issued['session_id']);
        $this->assertSame(['mcp:use','mcp:read'],$record->abilities);
        $this->assertNotNull($record->expires_at);
        $this->assertDatabaseCount('mcp_action_audits',1);
        $this->deleteJson('/dashboard/system-settings/mcp/sessions/'.$issued['session_id'])
            ->assertOk()->assertJsonPath('status','revoked');
        $this->assertDatabaseMissing('personal_access_tokens',['id'=>$issued['session_id']]);
        $this->assertDatabaseCount('mcp_action_audits',2);
    }

    public function test_admin_cannot_issue_write_permissions_without_confirmed_two_factor(): void
    {
        $admin=$this->admin();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at'=>time()]);
        $this->postJson('/dashboard/system-settings/mcp/sessions',[
            'client_name'=>'Write Without 2FA','scopes'=>['mcp:write'],
            'confirmation'=>'CONNECT','expires_in_minutes'=>30,
        ])->assertUnprocessable()->assertJsonValidationErrors('scopes');
        $this->assertDatabaseCount('personal_access_tokens',0);
    }

    public function test_plain_developer_token_and_browser_session_cannot_call_mcp(): void
    {
        config()->set('mcp.gateway_enabled',true);
        $user=$this->admin();
        $plain=$user->createToken('Ordinary API token',['catalog:read'],now()->addMinutes(15));
        $this->withToken($plain->plainTextToken)
            ->postJson('/api/mcp',['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize'])
            ->assertForbidden();
    }

    public function test_mcp_transport_is_disabled_by_default(): void
    {
        config()->set('mcp.gateway_enabled',false);
        $admin=$this->admin();
        $token=$admin->createToken('LearnCambodia MCP: Read Test',['mcp:use','mcp:read'],now()->addMinutes(15));
        $this->withToken($token->plainTextToken)
            ->postJson('/api/mcp',['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize'])
            ->assertNotFound();
    }

    public function test_admin_mcp_session_can_initialize_list_scoped_tools_and_read_redacted_records(): void
    {
        config()->set('mcp.gateway_enabled',true);
        $admin=$this->admin();
        $token=$admin->createToken('LearnCambodia MCP: Read Test',['mcp:use','mcp:read'],now()->addMinutes(15));
        $this->withToken($token->plainTextToken)
            ->postJson('/api/mcp',['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize'])
            ->assertOk()->assertJsonPath('result.serverInfo.name','learncambodia-admin-bridge');
        $this->postJson('/api/mcp',['jsonrpc'=>'2.0','id'=>2,'method'=>'tools/list'])
            ->assertOk()->assertJsonCount(3,'result.tools');
        $response=$this->postJson('/api/mcp',$this->rpc('crud_manage_platform',[
            'operation'=>'READ','resource'=>'users',
        ]))->assertOk();
        $response->assertJsonPath('result.isError',true)
            ->assertJsonPath('result.structuredContent.http_status',403);
        $response=$this->postJson('/api/mcp',$this->rpc('crud_manage_platform',[
            'operation'=>'READ','resource'=>'courses',
        ]))->assertOk()->assertJsonPath('result.isError',false);
        $this->assertArrayHasKey('items',$response->json('result.structuredContent'));
        $this->assertDatabaseCount('mcp_action_audits',2);
    }

    public function test_browser_authorized_write_scope_creates_only_unpublished_editorial_draft(): void
    {
        config()->set('mcp.gateway_enabled',true);
        config()->set('mcp.remote_writes_enabled',true);
        $admin=$this->admin();
        $token=$admin->createToken('LearnCambodia MCP: Draft Test',
            ['mcp:use','mcp:write'],now()->addMinutes(15));
        $payload=['operation'=>'CREATE','resource'=>'posts',
            'payload'=>['title'=>'MCP Safe Draft','slug'=>'mcp-safe-draft',
                'summary'=>'This is a test.','body_html'=>'<p>Allowed</p><script>alert(1)</script>',
                'content_language'=>'en','status'=>'published']];
        $response=$this->withToken($token->plainTextToken)
            ->postJson('/api/mcp',$this->rpc('crud_manage_platform',$payload))
            ->assertOk()->assertJsonPath('result.isError',false)
            ->assertJsonPath('result.structuredContent.status','created');
        $id=$response->json('result.structuredContent.resource_id');
        $entry=ContentEntry::findOrFail($id);
        $this->assertSame('draft',$entry->status);
        $this->assertSame('post',$entry->type);
        $this->assertStringNotContainsString('<script>',$entry->body_html??'');
        $this->assertDatabaseCount('mcp_action_audits',1);
    }

    public function test_delete_requires_one_time_matching_browser_approval(): void
    {
        config()->set('mcp.gateway_enabled',true);
        config()->set('mcp.remote_writes_enabled',true);
        $admin=$this->admin();
        $entry=ContentEntry::create([
            'type'=>'post','title'=>'Delete Test','slug'=>'delete-test',
            'status'=>'draft','created_by'=>$admin->id,'updated_by'=>$admin->id,
        ]);
        $token=$admin->createToken('LearnCambodia MCP: Delete Test',
            ['mcp:use','mcp:delete'],now()->addMinutes(15));
        $input=['operation'=>'DELETE','resource'=>'posts',
            'payload'=>['id'=>$entry->id,'approval_id'=>(string) Str::uuid()]];
        $this->withToken($token->plainTextToken)
            ->postJson('/api/mcp',$this->rpc('crud_manage_platform',$input))
            ->assertOk()->assertJsonPath('result.isError',true);
        $this->assertNull($entry->fresh()->deleted_at);

        $approved=McpActionApproval::create([
            'id'=>(string)Str::uuid(), 'user_id'=>$admin->id,
            'token_id'=>$token->accessToken->id,
            'operation'=>'DELETE','resource'=>'posts','resource_id'=>(string)$entry->id,
            'payload_hash'=>McpActionConsent::hash('DELETE','posts',(string)$entry->id),
            'status'=>'approved','expires_at'=>now()->addMinutes(10),
            'approved_at'=>now(),
        ]);
        $input['payload']['approval_id']=$approved->id;
        $this->postJson('/api/mcp',$this->rpc('crud_manage_platform',$input))
            ->assertOk()->assertJsonPath('result.structuredContent.status','soft_deleted')
            ->assertJsonPath('result.structuredContent.reversible',true);
        $this->assertSoftDeleted($entry);
        $this->assertSame('consumed',$approved->fresh()->status);
        $this->postJson('/api/mcp',$this->rpc('crud_manage_platform',$input))
            ->assertOk()->assertJsonPath('result.isError',true);
    }

    public function test_approval_can_only_be_accepted_in_website_admin_browser(): void
    {
        $admin=$this->admin();
        $other=$this->admin();
        $approval=McpActionApproval::create([
            'id'=>(string)Str::uuid(),'user_id'=>$admin->id,'token_id'=>321,
            'operation'=>'DELETE','resource'=>'posts','resource_id'=>'123',
            'payload_hash'=>str_repeat('a',64),'status'=>'pending',
            'expires_at'=>now()->addMinutes(10),
        ]);
        $this->actingAs($other)->withSession(['auth.password_confirmed_at'=>time()])
            ->get('/dashboard/system-settings/mcp/approvals/'.$approval->id)->assertForbidden();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at'=>time()])
            ->get('/dashboard/system-settings/mcp/approvals/'.$approval->id)->assertOk()
            ->assertSee('Type CONFIRM');
        $this->post('/dashboard/system-settings/mcp/approvals/'.$approval->id,[
            'decision'=>'approve','confirmation'=>'CONFIRM',
        ])->assertRedirect();
        $this->assertSame('approved',$approval->fresh()->status);
    }

    public function test_unapproved_code_push_and_publish_are_rejected_even_with_manage_scope(): void
    {
        config()->set('mcp.gateway_enabled',true);
        config()->set('mcp.remote_writes_enabled',true);
        $admin=$this->admin();
        $token=$admin->createToken('LearnCambodia MCP: Restricted Test',
            ['mcp:use','mcp:manage','mcp:publish'],now()->addMinutes(15));
        foreach(['INSTALL','PUSH','PUBLISH'] as $op){
            $this->withToken($token->plainTextToken)
                ->postJson('/api/mcp',$this->rpc('crud_manage_platform',[
                    'operation'=>$op,'resource'=>'courses','payload'=>[],
                ]))->assertOk()->assertJsonPath('result.isError',true);
        }
    }
}
