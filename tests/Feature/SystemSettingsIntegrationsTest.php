<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GoogleSignIn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SystemSettingsIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_requires_second_factor_and_a_recovery_code_is_consumed(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode(['saved-recovery-code'])),
        ])->save();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('security.challenge'));
        $this->assertGuest();
        $this->get(route('admin.overview'))->assertRedirect(route('login'));
        $this->post(route('security.challenge.finish'), ['code' => 'wrong'])
            ->assertSessionHasErrors('code');
        $this->post(route('security.challenge.finish'), ['code' => 'saved-recovery-code'])
            ->assertRedirect(route('admin.overview'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains('saved-recovery-code', $user->fresh()->recoveryCodes());
    }

    public function test_google_registration_requires_consent_and_always_creates_a_student(): void
    {
        config([
            'services.google.enabled' => true,
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
        ]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-test-identity', 'name' => 'Student', 'email' => 'student@gmail.com', 'email_verified' => true,
            ]),
        ]);

        $start = $this->get(route('social.google.start'))->assertRedirect();
        parse_str(parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame(GoogleSignIn::callback(), $query['redirect_uri']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->get(route('social.google.callback', ['state' => $query['state'], 'code' => 'test-code']))
            ->assertRedirect(route('social.google.registration'));
        $this->assertGuest();
        $this->post(route('social.google.register'), ['terms' => 1, 'role' => 'admin'])
            ->assertRedirect(route('account.overview'));
        $this->assertDatabaseHas('users', ['email' => 'student@gmail.com', 'role' => 'student', 'google_subject' => 'google-test-identity']);
    }

    public function test_managed_s3_copy_and_verify_keep_the_local_source(): void
    {
        $local = Storage::fake('local');
        $s3 = Storage::fake('managed-s3');
        $local->put('editor-media/example.bin', 'sample');

        $this->artisan('storage:managed-transfer', ['--provider' => 's3', '--mode' => 'probe'])->assertSuccessful();
        $this->artisan('storage:managed-transfer', ['--provider' => 's3', '--mode' => 'copy'])->assertSuccessful();
        $this->artisan('storage:managed-transfer', ['--provider' => 's3', '--mode' => 'verify'])->assertSuccessful();
        $this->assertSame('sample', $s3->get('editor-media/example.bin'));
        $this->assertSame('sample', $local->get('editor-media/example.bin'));
    }
}
