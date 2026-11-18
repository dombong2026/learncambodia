<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ManagedSocialAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class ManagedSocialAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_configures_credentials_and_tests_a_disabled_provider(): void
    {
        $save = route('admin.system-settings.social-auth.save', 'github');
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs(User::factory()->create())->put($save)->assertForbidden();
        $this->actingAs($admin)->get(route('admin.system-settings.social-auth.configure', 'github'))
            ->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => time()])->put($save, [
            'client_id' => 'github-public-id', 'client_secret' => 'fixture-private-secret',
        ])->assertRedirect(route('admin.system-settings.social-auth', ['configure' => 'github']));

        $this->get(route('admin.system-settings.social-auth', ['configure' => 'github']))
            ->assertOk()->assertSee('Test GitHub sign-in')->assertDontSee('fixture-private-secret');
        $this->assertStringNotContainsString('fixture-private-secret', DB::table('managed_social_auth')->value('credentials'));
        $this->assertFalse(ManagedSocialAuth::enabled(ManagedSocialAuth::settings('github')));

        $this->fakeGitHub('test@example.test');
        $start = $this->post(route('admin.system-settings.social-auth.test', 'github'))->assertRedirect();
        $state = $this->state($start->headers->get('Location'));

        $this->get(route('social.provider.callback', ['provider' => 'github', 'state' => $state, 'code' => 'test-code']))
            ->assertRedirect(route('admin.system-settings.social-auth'))
            ->assertSessionHas('status', 'GitHub authorization and profile lookup passed.');
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_provider_controls_require_confirmation_and_keep_dashboard_credentials(): void
    {
        $page = route('admin.system-settings.social-auth');
        $status = route('admin.system-settings.social-auth.status', 'github');
        $configure = route('admin.system-settings.social-auth.configure', 'github');

        $this->actingAs(User::factory()->create())->patch($status, ['enabled' => '1'])->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPERADMIN]));
        $this->get($page)->assertOk()->assertSee('Configure GitHub')->assertSee('Setup required')
            ->assertSee('role="switch" aria-checked="false"', false);
        $this->patchJson($status, ['enabled' => '1'])->assertStatus(423);
        $this->get(route('admin.system-settings.social-auth.confirm'))->assertRedirect(route('password.confirm'));

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->patch($status, ['enabled' => '1'])->assertRedirect($page)->assertSessionHas('social_auth_error');
        $this->assertDatabaseCount('managed_social_auth', 0);
        $this->get($configure)->assertRedirect(route('admin.system-settings.social-auth', ['configure' => 'github']).'#social-auth-settings');

        $this->put(route('admin.system-settings.social-auth.save', 'github'), [
            'client_id' => 'github-client', 'client_secret' => 'fixture-secret',
        ])->assertRedirect();
        $payload = DB::table('managed_social_auth')->where('provider', 'github')->value('credentials');
        $this->patch($status, ['enabled' => '1'])->assertRedirect($page)->assertSessionHas('status', 'GitHub sign-in enabled.');
        $this->get($page)->assertOk()->assertSee('role="switch" aria-checked="true" aria-label="GitHub sign-in"', false);
        $this->assertSame($payload, DB::table('managed_social_auth')->where('provider', 'github')->value('credentials'));

        $this->put(route('admin.system-settings.social-auth.save', 'github'), [
            'client_id' => 'github-client', 'client_secret' => 'rotated-secret',
        ])->assertRedirect();
        $this->assertTrue(ManagedSocialAuth::enabled(ManagedSocialAuth::settings('github')));

        $this->patch($status, ['enabled' => '0'])->assertRedirect($page)->assertSessionHas('status', 'GitHub sign-in disabled.');
        $this->assertFalse(ManagedSocialAuth::enabled(ManagedSocialAuth::settings('github')));
        $this->assertSame('rotated-secret', ManagedSocialAuth::settings('github')['client_secret']);
    }

    public function test_provider_switch_can_override_the_server_status_without_copying_credentials(): void
    {
        config([
            'services.google.enabled' => true,
            'services.google.client_id' => 'server-client',
            'services.google.client_secret' => 'server-secret',
        ]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPERADMIN]))
            ->withSession(['auth.password_confirmed_at' => time()]);
        $status = route('admin.system-settings.social-auth.status', 'google');
        $this->patch($status, ['enabled' => '0'])->assertRedirect();
        $this->assertFalse(ManagedSocialAuth::enabled(ManagedSocialAuth::settings('google')));
        $this->assertSame('', DB::table('managed_social_auth')->where('provider', 'google')->value('credentials'));

        config(['services.google.client_secret' => 'rotated-server-secret']);
        $this->patch($status, ['enabled' => '1'])->assertRedirect();
        $settings = ManagedSocialAuth::settings('google');
        $this->assertTrue(ManagedSocialAuth::enabled($settings));
        $this->assertSame('rotated-server-secret', $settings['client_secret']);
        $this->assertSame('Server configuration', $settings['source']);
    }

    public function test_saved_google_credentials_drive_legacy_sign_in_route(): void
    {
        $this->configure('google');
        $this->get(route('login'))->assertSee('Continue with Google')->assertDontSee('Continue with Facebook');
        $this->get(route('social.data-deletion'))->assertOk()->assertSee('Contact LearnCambodia');
        $response = $this->get(route('social.google.start'))->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/', $response->headers->get('Location'));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);
        $this->assertSame('client-google', $parameters['client_id']);
        $this->assertSame('S256', $parameters['code_challenge_method']);
    }

    public function test_github_sign_up_checks_state_and_terms_and_reuses_the_identity(): void
    {
        $this->configure('github');
        $this->fakeGitHub('new-student@example.test');
        $this->get(route('register'))->assertSee('Continue with GitHub');

        $callback = $this->start('github');
        $this->get($callback)->assertRedirect(route('social.provider.registration', 'github'));
        $this->get($callback)->assertStatus(400);
        $this->post(route('social.provider.register', 'github'))->assertSessionHasErrors('terms');
        $this->assertDatabaseCount('users', 0);
        $this->post(route('social.provider.register', 'github'), ['terms' => '1'])->assertRedirect(route('account.overview'));
        $this->assertDatabaseCount('social_identities', 1);
        $user = User::query()->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('new-student@example.test', $user->email);

        $this->post(route('logout'));
        $this->get($this->start('github'))->assertRedirect(route('account.overview'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_email_match_cannot_sign_in_or_attach_an_existing_account(): void
    {
        $this->configure('github');
        $user = User::factory()->create();
        $this->fakeGitHub($user->email);
        $this->get($this->start('github'))->assertRedirect(route('login'))->assertSessionHas('social_auth_error');
        $this->assertGuest();
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_linkedin_link_unlink_and_sign_in_respect_password_confirmation_and_two_factor(): void
    {
        $this->configure('linkedin');
        Http::fake([
            'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'fixture-token']),
            'api.linkedin.com/v2/userinfo' => Http::response(['sub' => 'linkedin-user', 'name' => 'Learner']),
        ]);
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('test-totp-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['fixture-recovery-code'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->actingAs($user);
        $this->post(route('social.provider.connect', 'linkedin'))->assertRedirect(route('password.confirm'));
        $start = $this->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('social.provider.connect', 'linkedin'))->assertRedirect();
        $this->get($this->providerCallback('linkedin', $this->state($start->headers->get('Location'))))
            ->assertRedirect(route('security.methods'));
        $this->get(route('security.methods'))->assertOk()->assertSee('Disconnect LinkedIn');
        $this->post(route('logout'));

        $this->get($this->start('linkedin'))->assertRedirect(route('security.challenge'));
        $this->assertGuest();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('social.provider.disconnect', 'linkedin'))->assertRedirect(route('security.methods'));
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_facebook_registration_creates_the_account_only_after_email_confirmation(): void
    {
        $this->configure('facebook');
        config(['mail.default' => 'smtp']);
        Http::fake([
            'graph.facebook.com/v26.0/oauth/access_token*' => Http::response(['access_token' => 'fixture-token']),
            'graph.facebook.com/v26.0/me*' => Http::response(['id' => 'facebook-user', 'email' => 'new-learner@example.test']),
        ]);

        $link = null;
        Mail::shouldReceive('raw')->once()->andReturnUsing(function (string $body, \Closure $configure) use (&$link): object {
            $email = new Email;
            $configure(new Message($email));
            $this->assertSame('new-learner@example.test', $email->getTo()[0]->getAddress());
            $link = substr($body, strpos($body, "\n\n") + 2);

            return new \stdClass;
        });

        $this->get($this->start('facebook'))->assertRedirect(route('social.provider.registration', 'facebook'));
        $this->post(route('social.provider.register', 'facebook'), ['terms' => '1', 'email' => 'new-learner@example.test'])
            ->assertSessionHas('social_registration_sent');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->get($link.'&extra=untrusted')->assertForbidden();
        $this->get($link)->assertRedirect(route('account.overview'));
        $this->assertTrue(User::query()->sole()->hasVerifiedEmail());
        $this->assertDatabaseHas('social_identities', ['provider' => 'facebook']);
    }

    private function configure(string $provider): void
    {
        ManagedSocialAuth::save($provider, [
            'client_id' => 'client-'.$provider,
            'client_secret' => 'fixture-secret',
            'enabled' => true,
        ]);
    }

    private function fakeGitHub(string $email): void
    {
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response(['access_token' => 'fixture-token']),
            'api.github.com/user/emails' => Http::response([['primary' => true, 'verified' => true, 'email' => $email]]),
            'api.github.com/user' => Http::response(['id' => 12345, 'name' => 'LearnCambodia Student']),
        ]);
    }

    private function start(string $provider): string
    {
        $response = $this->get(route('social.provider.start', $provider))->assertRedirect();

        return $this->providerCallback($provider, $this->state($response->headers->get('Location')));
    }

    private function state(string $location): string
    {
        parse_str(parse_url($location, PHP_URL_QUERY), $parameters);

        return $parameters['state'];
    }

    private function providerCallback(string $provider, string $state): string
    {
        return route('social.provider.callback', compact('provider', 'state') + ['code' => 'fixture-code']);
    }
}
