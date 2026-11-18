<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountInvitations;
use App\Support\ManagedAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminUserInvitationController extends Controller
{
    public function resend(Request $request, User $user): RedirectResponse
    {
        abort_unless(AccountInvitations::deliveryEnabled(), 409, __('invitations.delivery_unavailable'));

        [$invitation, $token] = DB::transaction(function () use ($request, $user): array {
            [$actor, $account] = ManagedAccount::lockPair($request, $user);
            abort_unless($account->isInvitedAccount(), 409);

            $locale = $account->invitation()->value('locale') ?? app()->getLocale();

            return AccountInvitations::issue($actor, $account, $locale);
        }, 3);

        return back()->with('status', AccountInvitations::send($invitation, $token));
    }

    public function revoke(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            [$actor, $account] = ManagedAccount::lockPair($request, $user);
            abort_unless($account->isInvitedAccount(), 409);
            AccountInvitations::revoke($actor, $account);
        }, 3);

        return back()->with('status', __('invitations.revoked'));
    }
}
