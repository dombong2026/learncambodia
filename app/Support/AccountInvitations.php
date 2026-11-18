<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class AccountInvitations
{
    public static function deliveryEnabled(?string $mailer = null, array $visited = []): bool
    {
        $mailer ??= config('mail.default');
        if (! is_string($mailer) || in_array($mailer, $visited, true)) {
            return false;
        }

        $configuration = config("mail.mailers.{$mailer}", []);
        $transport = $configuration['transport'] ?? null;
        if ($transport === null || in_array($transport, ['log', 'array'], true)) {
            return false;
        }
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $configuration['mailers'] ?? [];
            foreach ($children as $child) {
                if (! self::deliveryEnabled($child, [...$visited, $mailer])) {
                    return false;
                }
            }

            return $children !== [];
        }

        return true;
    }

    /** Issue while holding the recipient account lock. Send only after commit. */
    public static function issue(User $actor, User $user, string $locale): array
    {
        $token = Str::random(64);
        $invitation = $user->invitation()->firstOrNew();
        $invitation->forceFill([
            'user_id' => $user->id,
            'invited_by' => $actor->id,
            'email' => $user->email,
            'locale' => $locale,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(3),
            'sent_at' => null,
            'accepted_at' => null,
            'revoked_at' => null,
        ])->save();

        ManagedAccount::audit($actor, $user, 'invitation_issued');

        return [$invitation, $token];
    }

    public static function send(UserInvitation $invitation, string $token): string
    {
        if (! self::deliveryEnabled()) {
            return __('invitations.delivery_failed');
        }

        $url = rtrim(config('app.url'), '/').route('invitations.show', ['token' => $token, 'lang' => $invitation->locale], false);
        $brand = PlatformBranding::name();
        $body = __('invitations.mail_body', ['brand' => $brand, 'url' => $url], $invitation->locale);
        $subject = __('invitations.mail_subject', ['brand' => $brand], $invitation->locale);

        try {
            $sent = Mail::raw($body, fn ($message) => $message->to($invitation->email)->subject($subject));
        } catch (\Exception $error) {
            Log::warning('Invitation mail transport failed.', [
                'user_id' => $invitation->user_id,
                'error_type' => get_class($error),
            ]);

            return __('invitations.delivery_failed');
        }

        if ($sent === null) {
            return __('invitations.delivery_failed');
        }

        $invitation->newQuery()->whereKey($invitation->user_id)
            ->where('token_hash', $invitation->token_hash)->update(['sent_at' => now()]);

        return __('invitations.sent');
    }

    public static function revoke(User $actor, User $user): void
    {
        $revoked = $user->invitation()->whereNull('accepted_at')->whereNull('revoked_at')
            ->update(['token_hash' => null, 'revoked_at' => now()]);

        if ($revoked) {
            ManagedAccount::audit($actor, $user, 'invitation_revoked');
        }
    }
}
