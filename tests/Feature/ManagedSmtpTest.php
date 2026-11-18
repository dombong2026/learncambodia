<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ManagedSmtpSettings;
use App\Support\OutboundMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class ManagedSmtpTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'synthetic-test-mail-password';

    public function test_smtp_settings_require_settings_permission_and_password_confirmation(): void
    {
        $page = route('admin.system-settings.smtp');
        $update = route('admin.system-settings.smtp.update');

        $this->get($page)->assertRedirect(route('login'));
        $staff = User::factory()->create()->forceFill(['role' => User::ROLE_ADMIN, 'admin_permissions' => ['users']]);
        $staff->save();
        $this->actingAs($staff)->get($page)->assertForbidden();
        $this->put($update, $this->settings())->assertForbidden();

        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->actingAs($admin)->get($page)->assertRedirect(route('password.confirm'));
        $this->confirmPassword()->get($page)->assertOk()->assertSee('SMTP connection')->assertSee('Send a test email');
    }

    public function test_dashboard_settings_are_encrypted_and_drive_verification_until_reset(): void
    {
        config(['mail.default' => 'log']);
        $admin = User::factory()->unverified()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->actingAs($admin)->confirmPassword();
        $page = route('admin.system-settings.smtp');
        $this->put(route('admin.system-settings.smtp.update'), $this->settings())->assertRedirect($page);

        $this->assertStringNotContainsString(self::PASSWORD, DB::table('managed_smtp_settings')->value('payload'));
        $this->assertSame(self::PASSWORD, ManagedSmtpSettings::saved()['password']);
        $this->get($page)->assertOk()->assertSee('Dashboard SMTP')->assertDontSee(self::PASSWORD);
        $this->assertTrue(OutboundMail::deliveryEnabled());
        $this->assertSame(ManagedSmtpSettings::MAILER, app('mail.manager')->getDefaultDriver());
        $this->assertSame('sender@example.test', config('mail.mailers.dashboard-smtp.from.address'));
        $this->assertSame('smtps', config('mail.mailers.dashboard-smtp.scheme'));

        Notification::fake();
        $this->from(route('verification.notice'))->post(route('verification.send'))
            ->assertSessionHas('status', __('auth.verification_link_sent'));
        Notification::assertSentTo($admin, VerifyEmail::class);

        $this->delete(route('admin.system-settings.smtp.reset'))->assertRedirect($page);
        $this->assertFalse(OutboundMail::deliveryEnabled());
        $this->assertSame('log', config('mail.default'));
    }

    public function test_credentials_are_reused_for_the_same_connection_only_and_are_never_flashed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->actingAs($admin)->confirmPassword();
        $update = route('admin.system-settings.smtp.update');
        $this->put($update, $this->settings())->assertRedirect();
        $this->put($update, ['password' => null, 'from_name' => 'Updated name'] + $this->settings())->assertRedirect();
        $this->assertSame(self::PASSWORD, ManagedSmtpSettings::saved()['password']);
        $this->assertSame('Updated name', ManagedSmtpSettings::saved()['from_name']);

        $this->put($update, ['password' => null, 'host' => 'other.example.test'] + $this->settings())
            ->assertSessionHasErrors('password');
        $this->put($update, ['host' => 'smtp://wrong.example.test'] + $this->settings())
            ->assertSessionHasErrors('host')->assertSessionMissing('_old_input.password');
        $this->assertSame('smtp.example.test', ManagedSmtpSettings::saved()['host']);
    }

    public function test_smtp_test_uses_the_entered_recipient_and_reports_provider_acceptance(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('raw')->once()->andReturnUsing(function (string $body, \Closure $configure): object {
            $email = new Email;
            $configure(new Message($email));
            $this->assertSame('recipient@example.test', $email->getTo()[0]->getAddress());
            $this->assertSame('LearnCambodia SMTP test', $email->getSubject());
            $this->assertStringContainsString('System Settings', $body);

            return new \stdClass;
        });

        $this->actingAs($admin)->confirmPassword()->post(route('admin.system-settings.smtp.test'), [
            'recipient' => 'recipient@example.test',
        ])->assertRedirect(route('admin.system-settings.smtp'))
            ->assertSessionHas('status', 'The mail server accepted the test email. Check the recipient inbox and spam folder.');
    }

    public function test_smtp_test_shows_a_safe_authentication_error(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('raw')->once()->andThrow(new TransportException('SMTP reply 535. Private server response.'));

        $this->actingAs($admin)->confirmPassword()->post(route('admin.system-settings.smtp.test'), [
            'recipient' => 'recipient@example.test',
        ])->assertRedirect()->assertSessionHasErrors([
            'recipient' => 'SMTP authentication was rejected. Check the username and provider app password.',
        ]);
        $this->get(route('admin.system-settings.smtp'))->assertOk()->assertDontSee('Private server response.');
    }

    private function confirmPassword(): static
    {
        return $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function settings(): array
    {
        return [
            'host' => 'smtp.example.test', 'port' => 465, 'scheme' => 'smtps',
            'username' => 'mailer@example.test', 'password' => self::PASSWORD,
            'from_address' => 'sender@example.test', 'from_name' => 'LearnCambodia',
        ];
    }
}
