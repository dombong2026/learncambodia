<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Local test password 839!';

    public function test_invitation_links_activate_and_verify_once(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $mail = [];
        $this->captureMail($mail);

        $form = $this->actingAs($admin)->get(route('admin.users.create', ['method' => 'invite']))->assertOk();
        $form->assertSee('Invitation language')->assertDontSee('Initial password');

        $this->post(route('admin.users.store'), $this->details(['invite_locale' => 'zh']))->assertRedirect();
        $account = $this->account();
        $this->assertSame('invited', $account->account_status);
        $this->assertNull($account->email_verified_at);
        $this->assertNotNull($account->invitation->sent_at);
        $this->assertSame('invitee@example.test', $mail[0]['email']);
        $this->assertStringContainsString('您受邀加入', $mail[0]['subject']);
        $this->assertStringContainsString('lang=zh', $mail[0]['url']);
        $this->assertSame(hash('sha256', basename(parse_url($mail[0]['url'], PHP_URL_PATH))), $account->invitation->token_hash);

        $this->get(route('admin.users.index', ['status' => 'invited', 'verification' => 'email_unverified']))
            ->assertOk()->assertSee('Invitee')->assertSee('Email unverified')->assertSee('Resend invitation');
        $this->patch(route('admin.users.status', $account), ['status' => 'active'])->assertConflict();
        $this->get(route('admin.users.index', ['verification' => 'email_verified']))->assertDontSee('Invitee');

        Auth::logout();
        $url = $mail[0]['url'];
        $this->get($url)->assertOk()->assertSee('接受邀请')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->from($url)->post($url, ['password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertSessionHasErrors('terms');
        $this->post($url, $this->acceptance())->assertRedirect(route('login'));

        $account->refresh();
        $this->assertTrue($account->isActiveAccount());
        $this->assertTrue($account->hasVerifiedEmail());
        $this->assertTrue(Hash::check(self::PASSWORD, $account->password));
        $this->assertNotNull($account->terms_accepted_at);
        $this->assertNull($account->invitation->token_hash);
        $this->assertDatabaseHas('user_account_events', ['user_id' => $account->id, 'action' => 'invitation_accepted']);
        $this->post($url, $this->acceptance())->assertNotFound();
    }

    public function test_resend_replaces_links_and_revoked_or_expired_links_cannot_activate(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $mail = [];
        $this->captureMail($mail);

        $this->actingAs($admin)->post(route('admin.users.store'), $this->details())->assertRedirect();
        $account = $this->account();
        $this->post(route('admin.users.invitation.resend', $account))->assertRedirect();
        $this->assertCount(2, $mail);
        $this->post(route('admin.users.invitation.revoke', $account))->assertRedirect();
        $this->post(route('admin.users.invitation.resend', $account))->assertRedirect();
        $this->assertCount(3, $mail);

        Auth::logout();
        $this->get($mail[0]['url'])->assertNotFound();
        $this->post($mail[1]['url'], $this->acceptance())->assertNotFound();
        $this->get($mail[2]['url'])->assertOk();
        $this->travel(4)->days();
        $this->post($mail[2]['url'], $this->acceptance())->assertNotFound();
        $this->assertTrue($account->fresh()->isInvitedAccount());
    }

    public function test_editing_the_email_revokes_its_link_and_a_sender_who_lost_access_cannot_activate_it(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $mail = [];
        $this->captureMail($mail);

        $this->actingAs($admin)->post(route('admin.users.store'), $this->details())->assertRedirect();
        $account = $this->account();
        $this->patch(route('admin.users.access.update', $account), [
            'email' => 'updated@example.test', 'role' => User::ROLE_STUDENT,
        ])->assertRedirect();
        $this->assertNotNull($account->invitation->revoked_at);
        $this->post(route('admin.users.invitation.resend', $account))->assertRedirect();
        $this->assertSame('updated@example.test', $mail[1]['email']);

        Auth::logout();
        $this->get($mail[0]['url'])->assertNotFound();
        $admin->forceFill(['admin_permissions' => []])->save();
        $this->post($mail[1]['url'], $this->acceptance())->assertNotFound();
    }

    public function test_disabled_delivery_does_not_create_an_account_and_transport_failure_can_be_retried(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        config(['mail.default' => 'failover', 'mail.mailers.failover.mailers' => ['smtp', 'log']]);
        $this->actingAs($admin)->post(route('admin.users.store'), $this->details())->assertSessionHasErrors('method');
        $this->assertDatabaseMissing('users', ['email' => 'invitee@example.test']);

        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('test delivery failure'));
        $this->post(route('admin.users.store'), $this->details())->assertRedirect()->assertSessionHas('status', __('invitations.delivery_failed'));
        $account = $this->account();
        $this->assertTrue($account->isInvitedAccount());
        $this->assertNull($account->invitation->sent_at);
        $this->get(route('admin.users.index'))->assertOk()->assertSee('Email not sent');
    }

    private function captureMail(array &$mail): void
    {
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('raw')->andReturnUsing(function (string $body, \Closure $callback) use (&$mail): object {
            $email = new Email;
            $callback(new Message($email));
            preg_match('~https?://\S+~', $body, $matches);
            $mail[] = [
                'email' => $email->getTo()[0]->getAddress(),
                'subject' => $email->getSubject(),
                'url' => $matches[0],
            ];

            return new \stdClass;
        });
    }

    private function details(array $overrides = []): array
    {
        return $overrides + [
            'method' => 'invite', 'name' => 'Invitee', 'email' => 'invitee@example.test',
            'role' => User::ROLE_STUDENT, 'invite_locale' => 'en',
        ];
    }

    private function acceptance(): array
    {
        return ['password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'terms' => '1'];
    }

    private function account(): User
    {
        return User::query()->where('email', 'invitee@example.test')->firstOrFail();
    }
}
