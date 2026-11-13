<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PremiumAuthLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_five_auth_pages_are_real_routes_and_login_has_floating_labels(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('auth-field-label', false)
            ->assertSee(route('password.request'), false)
            ->assertDontSee('name="locale"', false);

        $this->get('/register')->assertOk()
            ->assertSee('auth-field-label', false)
            ->assertSee('register_password_confirmation', false);

        $this->get('/forgot-password')->assertOk()
            ->assertSee('recovery_email', false);

        $this->get('/reset-password/invalid-token?email=student@example.test')->assertOk()
            ->assertSee('reset_password_confirmation', false);

        $this->get('/email/verify')->assertRedirect('/login');
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/email/verify')->assertOk()
            ->assertSee(__('auth.resend_confirmation'));
    }

    public function test_shared_auth_header_is_centered_and_home_icon_is_at_top_not_bottom(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/reset-password/not-a-real-token'] as $url) {
            $response = $this->get($url.'?lang=en')->assertOk()
                ->assertSee('auth-modal', false)
                ->assertSee('text-center text-white', false)
                ->assertSee('aria-label="Back to home"', false)
                ->assertSee('absolute left-3 top-4', false)
                ->assertDontSee('Secure access · Learn Cambodia')
                ->assertDontSee('auth.secure_session')
                ->assertDontSee('mt-6 flex items-center justify-between gap-3 border-t', false);
            $this->assertSame(1, substr_count($response->getContent(), 'aria-label="Back to home"'));
        }

        $this->get('/login?lang=en')->assertSee('Learning and Managing Your Account');
        $this->get('/register?lang=en')
            ->assertSee('Create a student learning account. Instructor access is granted by an administrator.')
            ->assertDontSee('Create a student or instructor account');
        $this->get('/forgot-password?lang=en')
            ->assertSee('Enter your email address to request a password reset link.');

        $this->actingAs(User::factory()->unverified()->create())
            ->get('/email/verify?lang=en')->assertOk()
            ->assertSee('text-center text-white', false)
            ->assertSee('aria-label="Back to home"', false)
            ->assertDontSee('Secure access · Learn Cambodia');
    }

    public function test_auth_header_copy_is_translated_for_khmer_and_chinese(): void
    {
        $this->get('/login?lang=km')->assertOk()
            ->assertSee('សិក្សា និងគ្រប់គ្រងគណនីរបស់អ្នក');
        $this->get('/register?lang=km')->assertOk()
            ->assertSee('សិទ្ធិជាគ្រូបង្រៀនត្រូវបានផ្ដល់ដោយអ្នកគ្រប់គ្រង');
        $this->get('/forgot-password?lang=zh')->assertOk()
            ->assertSee('输入您的电子邮箱地址，以申请密码重置链接。');
        $this->get('/register?lang=zh')->assertOk()
            ->assertSee('教师权限由管理员授予。');
    }

    public function test_password_reset_is_not_falsely_reported_delivered_with_log_mail(): void
    {
        config(['mail.default' => 'log']);
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])
            ->assertSessionHas('status', __('auth.email_delivery_disabled'));
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_valid_password_reset_token_updates_credentials_without_logging_user_in(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'SecureNewPass123!',
            'password_confirmation' => 'SecureNewPass123!',
        ])->assertRedirect('/login');

        $this->assertGuest();
        $this->assertTrue(Hash::check('SecureNewPass123!', $user->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_invalid_password_reset_token_cannot_change_password(): void
    {
        $user = User::factory()->create(['password' => 'ValidOriginal123!']);
        $this->post('/reset-password', [
            'email' => $user->email,
            'token' => 'bad-token',
            'password' => 'SecureNewPass123!',
            'password_confirmation' => 'SecureNewPass123!',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('ValidOriginal123!', $user->fresh()->password));
    }

    public function test_signed_email_confirmation_marks_only_the_authenticated_user(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)
            ->assertRedirect(route($user->homeRouteName()));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_confirmation_resend_is_explicitly_disabled_without_smtp(): void
    {
        $user = User::factory()->unverified()->create();
        config(['mail.default' => 'log']);
        Notification::fake();

        $this->actingAs($user)->post('/email/verification-notification')
            ->assertSessionHas('status', __('auth.email_delivery_disabled'));

        Notification::assertNothingSent();
    }

    public function test_url_locale_overrides_session_and_browser_header_is_used_when_new(): void
    {
        $this->withHeader('Accept-Language', 'km-KH,km;q=0.9,en;q=0.5')
            ->get('/login')->assertOk()->assertSee('lang="km"', false);

        $this->get('/login?lang=zh')->assertOk()->assertSee('lang="zh"', false);
        $this->get('/login')->assertOk()->assertSee('lang="zh"', false);

        $this->post('/language', ['locale' => 'en'])->assertRedirect('/?lang=en');
    }

    public function test_language_switch_retains_current_path_and_query_without_open_redirect(): void
    {
        $this->withHeader('referer', config('app.url').'/courses?q=english&lang=en')
            ->post('/language', ['locale' => 'km'])
            ->assertRedirect('/courses?q=english&lang=km');

        $this->withHeader('referer', 'https://evil.invalid/private')
            ->post('/language', ['locale' => 'zh'])
            ->assertRedirect('/?lang=zh');
    }
}
