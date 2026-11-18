<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

final class EmailVerificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_failure_returns_to_the_admin_confirmation_page_without_disclosing_details(): void
    {
        $admin = User::factory()->unverified()->create(['role' => User::ROLE_ADMIN]);
        config(['mail.default' => 'smtp']);
        $dispatcher = \Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('send')->once()->andThrow(new TransportException('Private SMTP error detail', 535));
        $this->app->instance(Dispatcher::class, $dispatcher);
        Log::spy();

        $notice = route('verification.notice');
        $this->actingAs($admin)->from($notice)->post(route('verification.send'))
            ->assertRedirect($notice)
            ->assertSessionHas('verification_delivery_error', __('auth.verification_delivery_failed'));
        $this->get($notice)->assertOk()
            ->assertSee(__('auth.verification_delivery_failed'))
            ->assertDontSee('Private SMTP error detail');
        $this->assertFalse($admin->fresh()->hasVerifiedEmail());

        Log::shouldHaveReceived('warning')->with('Email verification notification failed.', [
            'user_id' => $admin->id,
            'error_type' => TransportException::class,
            'error_code' => 535,
        ])->once();
    }

    public function test_self_service_still_requests_verification_for_a_super_admin(): void
    {
        $admin = User::factory()->unverified()->create(['role' => User::ROLE_SUPERADMIN]);
        config(['mail.default' => 'smtp']);
        Notification::fake();

        $notice = route('verification.notice');
        $this->actingAs($admin)->from($notice)->post(route('verification.send'))
            ->assertRedirect($notice)
            ->assertSessionHas('status', __('auth.verification_link_sent'));

        Notification::assertSentTo($admin, VerifyEmail::class);
        $this->assertFalse($admin->fresh()->hasVerifiedEmail());
    }

    public function test_log_fallback_does_not_write_a_signed_verification_link_to_the_log(): void
    {
        $user = User::factory()->unverified()->create();
        config([
            'mail.default' => 'failover',
            'mail.mailers.failover.mailers' => ['smtp', 'log'],
        ]);
        Notification::fake();

        $notice = route('verification.notice');
        $this->actingAs($user)->from($notice)->post(route('verification.send'))
            ->assertRedirect($notice)
            ->assertSessionHas('status', __('auth.email_delivery_disabled'));

        Notification::assertNothingSent();
    }
}
