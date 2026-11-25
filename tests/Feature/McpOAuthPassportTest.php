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


    /**
     * Only enabled-flow tests need signing keys. Keep the disabled-gate
     * tests independent of this fixture, and never write to storage or .env.
     */
    private function configureEphemeralPassportKeys(): void
    {
        $this->assertTrue($this->app->environment('testing'));
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privateKey));
        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);
        config()->set('passport.private_key', $privateKey);
        config()->set('passport.public_key', $details['key']);
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

    private function oauthQuery(string $client, array $overrides=[]): string
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
            // Laravel can prioritize the auth guard before the route-level
            // staging gate. Both 401 and 404 are fail-closed with no data.
            $status=$this->postJson('/api/mcp/oauth',
                ['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize'])->getStatusCode();
            $this->assertContains($status, [401, 404]);
            $this->postJson('/api/mcp/oauth/token', ['grant_type'=>'authorization_code'])
                ->assertNotFound();
            config()->set('mcp.'.$gate, true);
        }
        $this->postJson('/api/mcp', ['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize'])
            ->assertUnauthorized();
    }

    public function test_oauth_read_unauthorized_challenge_is_only_advertised_when_ready(): void
    {
        $this->configureEphemeralPassportKeys();
        $this->setupClient();
        $this->postJson('/api/mcp/oauth', [
            'jsonrpc'=>'2.0', 'id'=>1, 'method'=>'initialize',
        ])->assertUnauthorized()->assertHeader(
            'WWW-Authenticate',
            'Bearer resource_metadata="https://learncambodia.com/.well-known/oauth-protected-resource", scope="mcp:read"'
        );
        config()->set('mcp.oauth_ready', false);
        $this->postJson('/api/mcp/oauth', [
            'jsonrpc'=>'2.0', 'id'=>2, 'method'=>'initialize',
        ])->assertNotFound();
    }

    public function test_discovery_alias_and_streamable_http_get_are_fail_closed(): void
    {
        $this->setupClient();
        config()->set('mcp.oauth_discovery_enabled', true);
        $root = $this->getJson('/.well-known/oauth-protected-resource')->assertOk()->json();
        $this->getJson('/.well-known/oauth-protected-resource/api/mcp/oauth')
            ->assertOk()->assertExactJson($root);
        $this->get('/api/mcp/oauth', ['Accept' => 'text/event-stream'])
            ->assertStatus(405)->assertHeader('Allow', 'POST')
            ->assertExactJson(['error' => 'method_not_allowed']);

        config()->set('mcp.oauth_ready', false);
        $this->getJson('/.well-known/oauth-protected-resource/api/mcp/oauth')->assertNotFound();
        $this->get('/api/mcp/oauth', ['Accept' => 'text/event-stream'])->assertNotFound();
        $this->assertDatabaseCount('oauth_access_tokens', 0);
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
            $this->get('/mcp/oauth/authorize?'.$this->oauthQuery($client,$overrides))
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
        $this->get('/mcp/oauth/authorize?'.$this->oauthQuery($client))
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
        $this->exerciseAuthorizationFlow(false);
    }

    public function test_production_authorization_code_s256_exchange_read_refresh_and_revocation(): void
    {
        try {
            $this->exerciseAuthorizationFlow(true);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    private function exerciseAuthorizationFlow(bool $production): void
    {
        $this->configureEphemeralPassportKeys();
        if ($production) {
            $this->callback = 'https://chatgpt.com/connector/oauth/testcallback123';
            config()->set('mcp.oauth_redirect_uri', $this->callback);
            config()->set('mcp.oauth_provider_staging_enabled', false);
            config()->set('mcp.oauth_production_registration_enabled', false);
            config()->set('mcp.oauth_production_accepted', true);
            config()->set('mcp.oauth_production_enabled', true);
            config()->set('mcp.oauth_discovery_enabled', true);
            $this->app->instance('env', 'production');
        }
        $client = $this->setupClient();
        $admin = User::factory()->create(['role'=>User::ROLE_ADMIN, 'email_verified_at'=>now()]);
        $this->actingAs($admin, 'web')->withSession(['auth.password_confirmed_at'=>time()]);

        $verifier = str_repeat('V', 48);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $state = str_repeat('s', 32);
        $url = '/mcp/oauth/authorize?'.$this->oauthQuery($client, [
            'code_challenge' => $challenge,
            'state' => $state,
        ]);

        $consent = $this->get($url)->assertOk()->assertSee('Authorize READ access');
        $this->assertMatchesRegularExpression('/name="auth_token" value="[^"]+"/',
            $consent->getContent());
        preg_match('/name="auth_token" value="([^"]+)"/', $consent->getContent(), $matches);
        $authToken = $matches[1];

        $this->assertMatchesRegularExpression('/name="_token" value="[^"]+"/', $consent->getContent());
        preg_match('/name="_token" value="([^"]+)"/', $consent->getContent(), $csrfMatches);
        $csrf = $csrfMatches[1];

        // Production-mode tests must behave like a browser, including CSRF.
        // Verify a missing token is rejected before submitting the real form.
        if ($production) {
            $this->post('/mcp/oauth/authorize', [
                'state' => $state, 'client_id' => $client, 'auth_token' => $authToken,
            ])->assertStatus(419);
        }

        $approve = $this->post('/mcp/oauth/authorize', [
            'state' => $state, 'client_id' => $client,
            'auth_token' => $authToken, '_token' => $csrf,
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
        ])->assertOk()->assertJsonCount(10, 'result.tools')
            ->assertJsonPath('result.tools.0.inputSchema.properties.operation.enum.0', 'READ');

        $this->withToken($token)->postJson('/api/mcp/oauth', [
            'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
            'params' => ['name' => 'crud_manage_platform', 'arguments' => [
                'operation' => 'CREATE', 'resource' => 'courses']],
        ])->assertOk()->assertJsonPath('result.isError', true);

        // Even a genuine, consented Passport READ token must never reach
        // the draft writer, regardless of either write activation gate.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('mcp.oauth.write'));
        $admin->forceFill([
            'two_factor_secret' => encrypt('TEST-ONLY-BASE32-SECRET'),
            'two_factor_confirmed_at' => now(),
        ])->save();
        foreach ([[false, false], [true, false], [false, true], [true, true]] as [$accepted, $enabled]) {
            config()->set('mcp.oauth_draft_writes_accepted', $accepted);
            config()->set('mcp.oauth_draft_writes_enabled', $enabled);
            try {
                \App\Support\McpOAuthDraftAuthorization::execute(
                    app('request'), 'CREATE', 'articles',
                    ['title' => 'Forbidden draft', 'summary' => 'Fixture', 'body' => 'Fixture'],
                    (string) \Illuminate\Support\Str::uuid()
                );
                $this->fail('The READ token must not authorize a draft mutation.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
                $this->assertSame(403, $error->getStatusCode());
            }
        }
        config()->set('mcp.oauth_draft_writes_accepted', false);
        config()->set('mcp.oauth_draft_writes_enabled', false);
        $this->assertDatabaseCount('mcp_oauth_draft_receipts', 0);
        $this->assertDatabaseMissing('content_entries', ['title' => 'Forbidden draft']);

        // Public OAuth must not inherit administrator visibility of drafts.
        // Create only the isolated fixture; production-mode Artisan seed
        // commands correctly require a separate confirmation.
        $category = \App\Models\Category::create([
            'code' => 'OAUTH', 'slug' => 'oauth-test-category',
            'name' => 'OAuth test category', 'group' => 'english-courses',
            'description' => 'Isolated OAuth catalog visibility fixture',
            'is_published' => true, 'sort_order' => 1,
        ]);
        $base = [
            'summary' => 'OAuth catalog fixture',
            'category_id' => $category->id,
            'content_language' => 'en',
            'delivery_mode' => 'online',
            'created_by' => $admin->id,
        ];
        $published = \App\Models\Course::create($base + [
            'title' => 'Public OAuth fixture', 'slug' => 'public-oauth-fixture',
            'status' => 'published', 'published_at' => now()->subDay(),
        ]);
        $draft = \App\Models\Course::create($base + [
            'title' => 'Private OAuth draft fixture', 'slug' => 'private-oauth-draft-fixture',
            'status' => 'draft',
        ]);
        $future = \App\Models\Course::create($base + [
            'title' => 'Future OAuth fixture', 'slug' => 'future-oauth-fixture',
            'status' => 'published', 'published_at' => now()->addDay(),
        ]);
        $rpc = fn (array $payload) => [
            'jsonrpc' => '2.0', 'id' => 10, 'method' => 'tools/call',
            'params' => ['name' => 'crud_manage_platform', 'arguments' => [
                'operation' => 'READ', 'resource' => 'courses', 'payload' => $payload,
            ]],
        ];
        $this->withToken($token)->postJson('/api/mcp/oauth', $rpc([]))
            ->assertOk()->assertJsonPath('result.isError', false)
            ->assertJsonFragment(['id' => $published->id])
            ->assertJsonMissing(['title' => $draft->title])
            ->assertJsonMissing(['title' => $future->title]);
        $this->withToken($token)->postJson('/api/mcp/oauth', $rpc(['id' => $published->id]))
            ->assertOk()->assertJsonPath('result.structuredContent.items.0.id', $published->id);
        foreach ([$draft, $future] as $hidden) {
            $this->withToken($token)->postJson('/api/mcp/oauth', $rpc(['id' => $hidden->id]))
                ->assertOk()->assertJsonPath('result.isError', true)
                ->assertJsonMissing(['title' => $hidden->title]);
        }

        // Dedicated OAuth tools must remain confined to public metadata.
        $read = fn (string $name, array $arguments = []) => $this->withToken($token)
            ->postJson('/api/mcp/oauth', [
                'jsonrpc' => '2.0', 'id' => 11, 'method' => 'tools/call',
                'params' => ['name' => $name, 'arguments' => $arguments],
            ])->assertOk();

        $module = $published->modules()->create(['title' => 'Public module', 'position' => 1]);
        $privateModule = $draft->modules()->create(['title' => 'Private draft module', 'position' => 1]);
        $lesson = $module->lessons()->create([
            'title' => 'Public lesson metadata', 'position' => 1,
            'content' => 'PRIVATE_LESSON_BODY_FIXTURE', 'duration_minutes' => 5,
            'quiz_questions' => [['answer' => 'PRIVATE_ANSWER_FIXTURE']],
        ]);
        $read('list_courses', ['limit' => 1])->assertJsonPath('result.structuredContent.items.0.id', $published->id)
            ->assertJsonPath('result.structuredContent.total', 1);
        $read('list_modules')->assertJsonPath('result.structuredContent.items.0.id', $module->id)
            ->assertJsonMissing(['title' => $privateModule->title]);
        $read('list_modules', ['id' => $privateModule->id])->assertJsonPath('result.isError', true);
        $read('list_lessons', ['id' => $lesson->id])->assertJsonPath('result.isError', false)
            ->assertJsonMissing(['content' => 'PRIVATE_LESSON_BODY_FIXTURE'])
            ->assertJsonMissingPath('result.structuredContent.items.0.quiz_questions');
        $read('list_courses', ['limit' => 51])->assertJsonPath('result.isError', true);
        $read('list_courses', ['operation' => 'CREATE'])->assertJsonPath('result.isError', true);
        $read('manage_courses', ['operation' => 'CREATE'])->assertJsonPath('result.isError', true);

        $type = \App\Models\ExamType::create(['name' => 'OAuth standalone quiz type', 'kind' => 'quiz']);
        $assessment = [
            'course_id' => null, 'kind' => 'quiz', 'exam_type_id' => $type->id,
            'questions' => [['answer' => 'PRIVATE_ASSESSMENT_ANSWER']],
            'time_limit' => 20, 'passing_percent' => 70, 'attempt_limit' => 2,
        ];
        $quiz = \App\Models\CourseExam::create($assessment + ['title' => 'Public standalone quiz', 'published' => true]);
        $privateQuiz = \App\Models\CourseExam::create($assessment + ['title' => 'Private standalone quiz', 'published' => false]);
        $read('list_quizzes')->assertJsonPath('result.structuredContent.items.0.id', $quiz->id)
            ->assertJsonMissing(['title' => $privateQuiz->title])
            ->assertJsonMissingPath('result.structuredContent.items.0.questions');
        $read('list_quizzes', ['id' => $privateQuiz->id])->assertJsonPath('result.isError', true);
        $read('get_platform_analytics')->assertJsonPath('result.structuredContent.items.assessment_quizzes_total', 2)
            ->assertJsonPath('result.structuredContent.items.standalone_quizzes_published', 1)
            ->assertJsonPath('result.structuredContent.items.quizzes_total', 2);

        // The catalog checks and revocation share Laravel's per-user
        // throttle window. Advance only the isolated test clock, preserving
        // the real rate limits and keeping the 20-minute access token valid.
        $this->travel(61)->seconds();

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
        $this->deleteJson('/dashboard/system-settings/mcp/oauth/tokens/'.$jti, [
            '_token' => $csrf,
        ])
            ->assertOk()->assertJsonPath('status', 'revoked');
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $jti, 'revoked' => true]);
        $this->assertDatabaseMissing('mcp_oauth_token_audiences', ['access_token_id' => $jti]);

        $this->withToken($newToken)->postJson('/api/mcp/oauth', [
            'jsonrpc' => '2.0', 'id' => 4, 'method' => 'initialize',
        ])->assertForbidden();
        $this->travelBack();
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

    public function test_production_requires_every_independent_gate_and_exact_callback(): void
    {
        $client = $this->setupClient();
        $this->app->instance('env', 'production');
        try {
            config()->set('mcp.oauth_provider_staging_enabled', false);
            config()->set('mcp.oauth_production_registration_enabled', false);
            config()->set('mcp.oauth_production_accepted', true);
            config()->set('mcp.oauth_production_enabled', true);
            config()->set('mcp.oauth_discovery_enabled', true);
            config()->set('mcp.oauth_redirect_uri', 'https://chatgpt.com/connector/oauth/testcallback123');
            $policy = \App\Http\Middleware\EnsureMcpOAuthStagingEnabled::class;
            $this->assertTrue($policy::enabled());

            foreach (['gateway_enabled', 'oauth_production_accepted',
                'oauth_production_enabled', 'oauth_discovery_enabled'] as $flag) {
                config()->set('mcp.'.$flag, false);
                $this->assertFalse($policy::enabled(), $flag);
                $this->getJson('/.well-known/oauth-protected-resource')->assertNotFound();
                $this->postJson('/api/mcp/oauth/token', ['grant_type'=>'authorization_code'])->assertNotFound();
                config()->set('mcp.'.$flag, true);
            }
            foreach (['oauth_production_registration_enabled', 'oauth_provider_staging_enabled'] as $flag) {
                config()->set('mcp.'.$flag, true);
                $this->assertFalse($policy::enabled(), $flag);
                config()->set('mcp.'.$flag, false);
            }
            config()->set('mcp.oauth_redirect_uri', $this->callback);
            $this->assertFalse($policy::enabled());
            config()->set('mcp.oauth_redirect_uri', 'https://chatgpt.com/connector/oauth/testcallback123');
            config()->set('mcp.oauth_client_id', '');
            $this->assertFalse($policy::enabled());
            config()->set('mcp.oauth_client_id', $client);
            $this->assertTrue($policy::enabled());
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_production_registration_is_closed_by_default_and_requires_explicit_confirmation(): void
    {
        $owner = User::factory()->create(['role'=>User::ROLE_ADMIN, 'email_verified_at'=>now()]);
        $callback = 'https://chatgpt.com/connector/oauth/testcallback123';
        $args = ['owner_id'=>$owner->id, 'redirect_uri'=>$callback, '--production'=>true];
        $this->app->instance('env', 'production');
        try {
            config()->set('mcp.oauth_provider_staging_enabled', false);
            config()->set('mcp.oauth_discovery_enabled', false);
            config()->set('mcp.oauth_production_enabled', false);
            config()->set('mcp.oauth_production_accepted', false);
            config()->set('mcp.oauth_production_registration_enabled', false);
            $this->artisan('mcp:oauth-register-chatgpt', $args)->assertExitCode(1)->run();
            config()->set('mcp.oauth_production_registration_enabled', true);
            $this->artisan('mcp:oauth-register-chatgpt', array_replace($args, ['redirect_uri'=>$this->callback]))->assertExitCode(1)->run();
            $this->artisan('mcp:oauth-register-chatgpt', $args)->assertExitCode(0)->run();
            $this->assertDatabaseCount('oauth_clients', 0);
            config()->set('mcp.oauth_production_enabled', true);
            $this->artisan('mcp:oauth-register-chatgpt', $args + ['--confirm'=>true])->assertExitCode(1)->run();
            config()->set('mcp.oauth_production_enabled', false);
            $this->artisan('mcp:oauth-register-chatgpt', $args + ['--confirm'=>true])->assertExitCode(0)->run();
            $this->assertDatabaseCount('oauth_clients', 1);
            $this->assertDatabaseHas('oauth_clients', ['name'=>'LearnCambodia ChatGPT MCP (READ)', 'secret'=>null]);
            $this->artisan('mcp:oauth-register-chatgpt', $args + ['--confirm'=>true])->assertExitCode(1)->run();
            $this->assertDatabaseCount('oauth_clients', 1);
            $this->postJson('/api/mcp/oauth/token', ['grant_type'=>'authorization_code'])->assertNotFound();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_draft_write_consent_and_signed_token_issuance_in_rehearsal(): void
    {
        $this->exerciseDraftAuthorization(false);
    }

    public function test_production_draft_write_requires_independent_gates_and_selected_admin(): void
    {
        try {
            $this->exerciseDraftAuthorization(true);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    private function exerciseDraftAuthorization(bool $production): void
    {
        $this->configureEphemeralPassportKeys();
        if ($production) {
            $this->callback = 'https://chatgpt.com/connector/oauth/testcallback123';
            config()->set('mcp.oauth_redirect_uri', $this->callback);
            config()->set('mcp.oauth_provider_staging_enabled', false);
            config()->set('mcp.oauth_production_registration_enabled', false);
            config()->set('mcp.oauth_production_accepted', true);
            config()->set('mcp.oauth_production_enabled', true);
            config()->set('mcp.oauth_discovery_enabled', true);
            $this->app->instance('env', 'production');
        }
        $client = $this->setupClient();
        config()->set('mcp.oauth_draft_writes_accepted', true);
        config()->set('mcp.oauth_draft_writes_enabled', true);
        config()->set('mcp.oauth_draft_scope_issuance_enabled', true);
        $policy = \App\Support\McpOAuthDraftScopePolicy::class;
        try {
            $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()]);
            if ($production) {
                $this->assertFalse($policy::issuanceEnabled());
                config()->set('mcp.oauth_draft_production_accepted', true);
                $this->assertFalse($policy::issuanceEnabled());
                config()->set('mcp.oauth_draft_production_admin_id', (string) $admin->id);
                foreach (['oauth_draft_writes_accepted', 'oauth_draft_writes_enabled',
                    'oauth_draft_scope_issuance_enabled', 'oauth_draft_production_accepted'] as $gate) {
                    config()->set('mcp.'.$gate, false);
                    $this->assertSame(['mcp:read'], array_keys($policy::scopes()));
                    config()->set('mcp.'.$gate, true);
                }
            }
            $this->assertTrue($policy::issuanceEnabled());
            Passport::tokensCan($policy::scopes());
            config()->set('mcp.oauth_discovery_enabled', true);
            foreach (['/.well-known/oauth-protected-resource',
                '/.well-known/oauth-protected-resource/api/mcp/oauth',
                '/.well-known/oauth-authorization-server'] as $path) {
                $this->getJson($path)->assertOk()->assertJsonPath('scopes_supported', ['mcp:read', 'mcp:write']);
            }
            $this->actingAs($admin, 'web')->withSession(['auth.password_confirmed_at' => time()]);
            $verifier = str_repeat('V', 48);
            $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            $state = str_repeat('w', 32);
            $url = '/mcp/oauth/authorize?'.$this->oauthQuery($client, [
                'scope' => 'mcp:read mcp:write', 'code_challenge' => $challenge, 'state' => $state,
            ]);
            $this->get($url)->assertBadRequest();
            $admin->forceFill([
                'two_factor_secret' => encrypt('TEST-ONLY-BASE32-SECRET'),
                'two_factor_confirmed_at' => now(),
            ])->save();
            $this->actingAs($admin, 'web');
            if ($production) {
                config()->set('mcp.oauth_draft_production_admin_id', (string) ($admin->id + 1));
                $this->get($url)->assertBadRequest();
                config()->set('mcp.oauth_draft_production_admin_id', (string) $admin->id);
            }
            $consent = $this->get($url)->assertOk()
                ->assertSee('Authorize draft WRITE access')
                ->assertSee('No publishing, deletion, file upload');
            $this->assertMatchesRegularExpression('/name="auth_token" value="[^"]+"/', $consent->getContent());
            preg_match('/name="auth_token" value="([^"]+)"/', $consent->getContent(), $matches);
            preg_match('/name="_token" value="([^"]+)"/', $consent->getContent(), $csrf);
            $approve = $this->post('/mcp/oauth/authorize', [
                'state' => $state, 'client_id' => $client,
                'auth_token' => $matches[1], '_token' => $csrf[1],
            ])->assertRedirect();
            parse_str(parse_url($approve->headers->get('Location'), PHP_URL_QUERY) ?? '', $parameters);
            $this->assertSame($state, $parameters['state'] ?? null);
            $this->assertNotEmpty($parameters['code'] ?? null);
            $response = $this->postJson('/api/mcp/oauth/token', [
                'grant_type' => 'authorization_code', 'client_id' => $client,
                'code' => $parameters['code'], 'redirect_uri' => $this->callback,
                'code_verifier' => $verifier, 'resource' => config('mcp.oauth_resource'),
            ])->assertOk();
            $token = $response->json('access_token');
            $refresh = $response->json('refresh_token');
            $this->assertIsString($token);
            $this->assertIsString($refresh);
            $this->assertDatabaseCount('mcp_oauth_token_audiences', 1);
            $this->assertTrue($policy::exactWriteScopes(json_decode(
                DB::table('oauth_access_tokens')->where('user_id', $admin->id)->value('scopes'), true
            )));

            // Test-only route exercises the real Passport guard and adapter.
            // No application route is added in this release.
            $this->assertFalse(\Illuminate\Support\Facades\Route::has('mcp.oauth.write'));
            \Illuminate\Support\Facades\Route::post('/__test/oauth-draft-write', function (\Illuminate\Http\Request $request) {
                return response()->json(\App\Support\McpOAuthDraftAuthorization::execute(
                    $request, 'CREATE', 'articles',
                    ['title' => 'Isolated OAuth draft', 'summary' => 'Fixture', 'body' => 'Fixture'],
                    '00000000-0000-4000-8000-000000000123'
                ));
            })->middleware('auth:mcp_oauth');
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->postJson('/__test/oauth-draft-write')
                ->assertOk()->assertJsonPath('publication_status', 'draft')
                ->assertJsonPath('replayed', false);
            $this->assertDatabaseHas('content_entries', ['title' => 'Isolated OAuth draft', 'status' => 'draft']);
            $this->assertDatabaseCount('mcp_oauth_draft_receipts', 1);
            // Use the actual MCP endpoint with the signed WRITE token.
            $rpc = function (string $method, array $params = []) use ($token) {
                $this->app['auth']->forgetGuards();
                return $this->withToken($token)->postJson('/api/mcp/oauth', [
                    'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params,
                ])->assertOk();
            };
            $discovery = $rpc('tools/list')->assertJsonCount(19, 'result.tools')
                ->assertJsonPath('result.tools.0.name', 'crud_manage_platform')
                ->assertJsonPath('result.tools.10.name', 'get_draft_courses');
            $this->assertCount(19, array_unique(array_column($discovery->json('result.tools'), 'name')));
            $rpc('tools/call', [
                'name' => 'get_platform_analytics', 'arguments' => [],
            ])->assertJsonPath('result.isError', false)
                ->assertJsonPath('result.structuredContent.http_status', 200);
            $rpc('tools/call', [
                'name' => 'crud_manage_platform', 'arguments' => [
                    'operation' => 'CREATE', 'resource' => 'courses',
                ],
            ])->assertJsonPath('result.isError', true);
            $call = fn (string $name, array $arguments) => $rpc('tools/call', [
                'name' => $name, 'arguments' => $arguments,
            ]);
            $fields = [
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'title' => 'Transport draft', 'summary' => 'Summary', 'body' => 'Body',
            ];
            $created = $call('create_draft_pages', $fields)
                ->assertJsonPath('result.isError', false)
                ->assertJsonPath('result.structuredContent.publication_status', 'draft')
                ->json('result.structuredContent');
            $call('create_draft_pages', $fields)->assertJsonPath('result.structuredContent.replayed', true);
            $lookup = $call('get_draft_pages', ['id' => $created['resource_id']])
                ->assertJsonPath('result.structuredContent.fields.title', 'Transport draft')
                ->json('result.structuredContent');
            $updated = array_replace($fields, [
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'title' => 'Updated transport draft',
            ]) + ['id' => $created['resource_id'], 'expected_version' => $lookup['draft_version']];
            $call('update_draft_pages', $updated)->assertJsonPath('result.isError', false);
            $call('update_draft_pages', array_replace($updated, [
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
            ]))->assertJsonPath('result.structuredContent.http_status', 409);
            $call('create_draft_pages', $fields + ['status' => 'published'])
                ->assertJsonPath('result.isError', true);
            $rejected = $call('publish_draft_pages', ['id' => $created['resource_id']])
                ->assertJsonPath('result.isError', true)->json('result.structuredContent');
            $this->assertDatabaseHas('mcp_action_audits', [
                'id' => $rejected['receipt_id'], 'user_id' => $admin->id,
                'result' => 'failed', 'tool' => 'oauth_draft_transport',
            ]);
            $this->assertDatabaseHas('content_entries', [
                'id' => $created['resource_id'], 'title' => 'Updated transport draft', 'status' => 'draft',
            ]);
            $this->assertDatabaseCount('mcp_oauth_draft_receipts', 3);

            // A missing/mismatched audience, role change or revoked token
            // must stop both combined discovery and draft calls.
            $binding = DB::table('mcp_oauth_token_audiences')->where('user_id', $admin->id);
            $binding->update(['resource' => 'https://example.invalid/mcp']);
            $denied = function () use ($token) {
                $this->app['auth']->forgetGuards();
                return $this->withToken($token)->postJson('/api/mcp/oauth', [
                    'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list',
                ]);
            };
            $denied()->assertForbidden();
            $binding->update(['resource' => config('mcp.oauth_resource')]);
            $admin->forceFill(['two_factor_confirmed_at' => null])->save();
            $denied()->assertForbidden();
            $admin->forceFill(['two_factor_confirmed_at' => now()])->save();
            if ($production) {
                config()->set('mcp.oauth_draft_production_admin_id', (string) ($admin->id + 1));
                $denied()->assertForbidden();
                config()->set('mcp.oauth_draft_production_admin_id', (string) $admin->id);
            }
            $rpc('tools/list')->assertJsonCount(19, 'result.tools');

            // The expanded RPC sequence and token endpoint share a throttle
            // bucket in this isolated HTTP test. Advance the test clock beyond
            // its one-minute window, without disabling any middleware or
            // exceeding the twenty-minute access-token lifetime.
            $this->travel(61)->seconds();

            // Closing the issuance switch blocks refresh and revokes the
            // newly minted disallowed token before it can leave the server.
            config()->set('mcp.oauth_draft_scope_issuance_enabled', false);
            $this->app['auth']->forgetGuards();
            $this->postJson('/api/mcp/oauth/token', [
                'grant_type' => 'refresh_token', 'client_id' => $client,
                'refresh_token' => $refresh, 'resource' => config('mcp.oauth_resource'),
            ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant')
                ->assertJsonMissingPath('access_token');
            $this->assertDatabaseCount('mcp_oauth_token_audiences', 1);
            $this->assertSame(0, DB::table('oauth_access_tokens')
                ->where('user_id', $admin->id)->where('revoked', false)->count());
            $this->assertContains($denied()->getStatusCode(), [401, 403]);
            $this->assertDatabaseCount('mcp_oauth_draft_receipts', 3);
        } finally {
            config()->set('mcp.oauth_draft_scope_issuance_enabled', false);
            config()->set('mcp.oauth_draft_writes_enabled', false);
            config()->set('mcp.oauth_draft_writes_accepted', false);
            config()->set('mcp.oauth_draft_production_accepted', false);
            config()->set('mcp.oauth_draft_production_admin_id', '');
            Passport::tokensCan($policy::scopes());
        }
    }
}
