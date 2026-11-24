<?php

namespace Tests\Feature;

use App\Models\McpOAuthUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

final class McpOAuthPassportTest extends TestCase
{
    use RefreshDatabase;

    private string $callback = 'https://chatgpt.com/connector_platform_oauth_redirect';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('mcp.gateway_enabled', true);
        // Tests may simulate the full staging acceptance gate. Production
        // config/mcp.php intentionally ships oauth_ready=false.
        config()->set('mcp.oauth_provider_staging_enabled', true);
        config()->set('mcp.oauth_ready', true);
        config()->set('mcp.oauth_provider_accepted', true);
        config()->set('mcp.oauth_resource', 'https://learncambodia.com/api/mcp/oauth');
        config()->set('mcp.oauth_redirect_uri', $this->callback);
        config()->set('mcp.remote_writes_enabled', false);
    }

    private function setupClient(): string
    {
        $owner = User::factory()->create(['role'=>User::ROLE_ADMIN, 'email_verified_at'=>now()]);
        $oauthOwner = McpOAuthUser::query()->findOrFail($owner->id);
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            name: 'ChatGPT MCP Test',
            redirectUris: [$this->callback],
            confidential: false,
            user: $oauthOwner,
        );
        config()->set('mcp.oauth_client_id', (string) $client->getKey());
        return (string) $client->getKey();
    }

    private function query(string $client, array $overrides=[]): string
    {
        return http_build_query(array_merge([
            'client_id' => $client,
            'redirect_uri' => $this->callback,
            'response_type' => 'code',
            'scope' => 'mcp:read',
            'state' => str_repeat('a', 32),
            'code_challenge_method' => 'S256',
            'code_challenge' => str_repeat('b', 43),
            'resource' => 'https://learncambodia.com/api/mcp/oauth',
        ], $overrides));
    }

    public function test_existing_sanctum_identity_is_not_replaced(): void
    {
        $this->assertTrue(method_exists(User::class, 'createToken'));
        $this->assertTrue(in_array(\Laravel\Sanctum\HasApiTokens::class,
            class_uses_recursive(User::class), true));
        $this->assertFalse(in_array(\Laravel\Passport\HasApiTokens::class,
            class_uses_recursive(User::class), true));
        $this->assertTrue(in_array(\Laravel\Passport\HasApiTokens::class,
            class_uses_recursive(McpOAuthUser::class), true));
        $this->assertSame('users', (new McpOAuthUser)->getTable());
        $this->assertSame('passport', config('auth.guards.mcp_oauth.driver'));
        $this->assertSame('mcp_oauth_users', config('auth.guards.mcp_oauth.provider'));
    }

    public function test_oauth_remains_closed_when_each_stage_gate_is_not_accepted(): void
    {
        $this->setupClient();
        foreach (['oauth_provider_staging_enabled', 'oauth_provider_accepted', 'oauth_ready'] as $gate) {
            config()->set('mcp.'.$gate, false);
            $this->postJson('/api/mcp/oauth', ['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize'])
                ->assertNotFound();
            $this->postJson('/api/mcp/oauth/token', ['grant_type'=>'authorization_code'])
                ->assertNotFound();
            config()->set('mcp.'.$gate, true);
        }
        $this->postJson('/api/mcp', ['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize'])
            ->assertUnauthorized();
    }

    public function test_only_exact_client_resource_and_pkce_s256_are_accepted(): void
    {
        $client = $this->setupClient();
        $admin = User::factory()->create(['role'=>User::ROLE_ADMIN,
            'email_verified_at'=>now()]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at'=>time()]);

        $bad = [
            ['client_id'=>'random-app'],
            ['resource'=>'https://malicious.example/mcp'],
            ['redirect_uri'=>'https://malicious.example/return'],
            ['scope'=>'mcp:write'],
            ['scope'=>'mcp:read mcp:write'],
            ['code_challenge_method'=>'plain'],
            ['code_challenge'=>'short'],
            ['response_type'=>'token'],
        ];
        foreach ($bad as $overrides) {
            $this->get('/mcp/oauth/authorize?'.$this->query($client,$overrides))
                ->assertBadRequest();
        }

        $this->post('/api/mcp/oauth/token', [
            'client_id'=>$client,
            'grant_type'=>'password',
            'resource'=>'https://learncambodia.com/api/mcp/oauth',
        ])->assertBadRequest();

        $this->post('/api/mcp/oauth/token', [
            'client_id'=>$client,
            'grant_type'=>'authorization_code',
            'resource'=>'https://learncambodia.com/api/mcp/oauth',
            'redirect_uri'=>$this->callback,
            'code'=>'bogus-code-with-valid-length',
            'code_verifier'=>'short',
        ])->assertBadRequest();
    }

    public function test_student_cannot_view_website_admin_oauth_consent(): void
    {
        $client = $this->setupClient();
        $student = User::factory()->create(['role'=>User::ROLE_STUDENT,
            'email_verified_at'=>now()]);
        $this->actingAs($student)->withSession(['auth.password_confirmed_at'=>time()]);
        $this->get('/mcp/oauth/authorize?'.$this->query($client))
            ->assertForbidden();
    }

    public function test_oauth_token_cannot_access_existing_sanctum_mcp(): void
    {
        $this->setupClient();
        $this->postJson('/api/mcp', [
            'jsonrpc'=>'2.0','id'=>1,'method'=>'tools/list',
        ])->assertUnauthorized();
    }

    public function test_passport_configuration_disallows_implicit_and_password_grants(): void
    {
        $this->assertFalse(Passport::$implicitGrantEnabled);
        $this->assertFalse(Passport::$passwordGrantEnabled);
        $this->assertFalse(Passport::$deviceCodeGrantEnabled);
        $this->assertTrue(Passport::$revokeRefreshTokenAfterUse);
        $this->assertSame(['mcp:read'], Passport::scopeIds());
    }

    public function test_authorization_code_s256_exchange_read_refresh_and_browser_revocation(): void
    {
        $client = $this->setupClient();
        $admin = User::factory()->create(['role'=>User::ROLE_ADMIN, 'email_verified_at'=>now()]);
        $this->actingAs($admin, 'web')->withSession(['auth.password_confirmed_at'=>time()]);

        $verifier = str_repeat('V', 48);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $state = str_repeat('s', 32);
        $url = '/mcp/oauth/authorize?'.$this->query($client, [
            'code_challenge' => $challenge,
            'state' => $state,
        ]);

        $consent = $this->get($url)->assertOk()->assertSee('Authorize READ access');
        $this->assertMatchesRegularExpression('/name="auth_token" value="[^"]+"/',
            $consent->getContent());
        preg_match('/name="auth_token" value="([^"]+)"/', $consent->getContent(), $matches);
        $authToken = $matches[1];

        $approve = $this->post('/mcp/oauth/authorize', [
            'state' => $state, 'client_id' => $client,
            'auth_token' => $authToken,
        ])->assertRedirect();
        $redirect = $approve->headers->get('Location');
        $this->assertStringStartsWith($this->callback, $redirect);
        parse_str(parse_url($redirect, PHP_URL_QUERY) ?? '', $parameters);
        $this->assertSame($state, $parameters['state'] ?? null);
        $this->assertNotEmpty($parameters['code'] ?? null);

        $exchange = [
            'grant_type' => 'authorization_code',
            'client_id' => $client,
            'code' => $parameters['code'],
            'redirect_uri' => $this->callback,
            'code_verifier' => $verifier,
            'resource' => config('mcp.oauth_resource'),
        ];
        $response = $this->postJson('/api/mcp/oauth/token', $exchange)->assertOk();
        $token = $response->json('access_token');
        $refresh = $response->json('refresh_token');
        $this->assertIsString($token);
        $this->assertIsString($refresh);
        $this->assertDatabaseCount('mcp_oauth_token_audiences', 1);

        $this->withToken($token)->postJson('/api/mcp/oauth', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
        ])->assertOk()->assertJsonPath('result.serverInfo.name',
            'learncambodia-oauth-read-only');

        $this->withToken($token)->postJson('/api/mcp/oauth', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list',
        ])->assertOk()->assertJsonCount(1, 'result.tools')
            ->assertJsonPath('result.tools.0.inputSchema.properties.operation.enum.0', 'READ');

        $this->withToken($token)->postJson('/api/mcp/oauth', [
            'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
            'params' => ['name' => 'crud_manage_platform', 'arguments' => [
                'operation' => 'CREATE', 'resource' => 'courses']],
        ])->assertOk()->assertJsonPath('result.isError', true);

        $refreshResponse = $this->postJson('/api/mcp/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client,
            'refresh_token' => $refresh,
            'resource' => config('mcp.oauth_resource'),
        ])->assertOk();

        $newToken = $refreshResponse->json('access_token');
        $this->assertNotSame($token, $newToken);
        $this->assertDatabaseCount('mcp_oauth_token_audiences', 2);

        // A refresh token must not be reusable after rotation.
        $this->postJson('/api/mcp/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client,
            'refresh_token' => $refresh,
            'resource' => config('mcp.oauth_resource'),
        ])->assertStatus(400);

        $jti = json_decode(base64_decode(strtr(explode('.', $newToken)[1], '-_', '+/')), true)['jti'];
        $this->actingAs($admin, 'web')->withSession(['auth.password_confirmed_at' => time()]);
        $this->deleteJson('/dashboard/system-settings/mcp/oauth/tokens/'.$jti)
            ->assertOk()->assertJsonPath('status', 'revoked');
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $jti, 'revoked' => true]);
        $this->assertDatabaseMissing('mcp_oauth_token_audiences', ['access_token_id' => $jti]);

        $this->withToken($newToken)->postJson('/api/mcp/oauth', [
            'jsonrpc' => '2.0', 'id' => 4, 'method' => 'initialize',
        ])->assertUnauthorized();
    }

    public function test_revocation_does_not_modify_sanctum_tokens(): void
    {
        $client = $this->setupClient();
        $admin=User::factory()->create(['role'=>User::ROLE_ADMIN, 'email_verified_at'=>now()]);
        $sanctum=$admin->createToken('LearnCambodia MCP: Keep', ['mcp:use','mcp:read'],
            now()->addMinutes(15));

        // Browser token management is not a remote MCP operation.
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at'=>time()]);
        $this->getJson('/dashboard/system-settings/mcp/oauth/tokens')->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', [
            'id'=>$sanctum->accessToken->id,
        ]);
        $this->assertSame(1,DB::table('personal_access_tokens')->count());
    }
}
