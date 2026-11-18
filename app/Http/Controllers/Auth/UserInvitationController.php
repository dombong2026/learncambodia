<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\ManagedAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class UserInvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->lookup($token);
        abort_unless($invitation->canBeAccepted($invitation->user, $invitation->inviter), 404);

        return view('auth.accept-invitation', [
            'token' => $token,
            'publishedPolicies' => ContentEntry::publishedLegalPolicies(),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->lookup($token);
        abort_unless($invitation->canBeAccepted($invitation->user, $invitation->inviter), 404);

        $data = $request->validate([
            'password' => [
                'bail', 'required', 'string', 'confirmed', 'max:72', Password::min(12),
                function (string $attribute, string $value, \Closure $fail): void {
                    if (strlen($value) > 72 || str_contains($value, "\0")) {
                        $fail(__('invitations.password_bytes'));
                    }
                },
            ],
            'terms' => ['required', 'accepted'],
        ]);

        DB::transaction(function () use ($invitation, $data): void {
            $accounts = User::query()->whereKey([$invitation->user_id, $invitation->invited_by])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $account = $accounts->get($invitation->user_id);
            $inviter = $accounts->get($invitation->invited_by);
            $current = UserInvitation::query()->whereKey($invitation->user_id)
                ->where('token_hash', $invitation->token_hash)->lockForUpdate()->firstOrFail();
            abort_unless($account && $current->canBeAccepted($account, $inviter), 404);

            $acceptedAt = now();
            $account->forceFill([
                'password' => $data['password'],
                'account_status' => 'active',
                'email_verified_at' => $acceptedAt,
                'terms_accepted_at' => $acceptedAt,
            ])->save();
            $current->forceFill(['token_hash' => null, 'accepted_at' => $acceptedAt])->save();
            ManagedAccount::audit($account, $account, 'invitation_accepted');
        }, 3);

        return to_route('login')->with('status', __('invitations.accepted'));
    }

    private function lookup(string $token): UserInvitation
    {
        return UserInvitation::query()->with(['user', 'inviter'])
            ->where('token_hash', hash('sha256', $token))->firstOrFail();
    }
}
