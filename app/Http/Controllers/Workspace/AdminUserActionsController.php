<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountInvitations;
use App\Support\AccountPreview;
use App\Support\AdminAccess;
use App\Support\ManagedAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminUserActionsController extends Controller
{
    public function confirm(Request $request, User $user, string $action): View
    {
        abort_unless(AdminAccess::canEdit($request->user(), $user) && $user->account_status !== 'deleted', 403);
        abort_if($action === 'verify' && $user->isInvitedAccount(), 409);
        abort_if($action === 'sign-in-as' && ! AccountPreview::allowed($request->user(), $user), 403);

        return view('workspace.admin.user-action', ['account' => $user, 'action' => $action]);
    }

    public function verify(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'channels' => ['required', 'array', 'list', 'min:1', 'max:2'],
            'channels.*' => ['required', 'distinct', Rule::in(['email', 'phone'])],
        ]);

        DB::transaction(function () use ($request, $user, $data): void {
            [$actor, $account] = ManagedAccount::lockPair($request, $user);
            abort_if($account->account_status === 'deleted' || $account->isInvitedAccount(), 403);
            foreach ($data['channels'] as $channel) {
                if ($channel === 'phone' && ! $account->phone) {
                    throw ValidationException::withMessages(['channels' => 'Add a phone number before verifying it.']);
                }

                $column = $channel.'_verified_at';
                if ($account->{$column} === null) {
                    $account->forceFill([$column => now()]);
                    ManagedAccount::audit($actor, $account, $channel.'_verified');
                }
            }
            $account->save();
        });

        return to_route('admin.users.show', $user)->with('status', 'Verification updated.');
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'deactivated', 'restore'])]]);

        DB::transaction(function () use ($request, $user, $data): void {
            [$actor, $account] = ManagedAccount::lockPair($request, $user);
            $requested = $data['status'];
            if ($requested === 'restore') {
                abort_unless($account->account_status === 'deleted', 409);
                $pending = $account->invitation()->whereNull('accepted_at')->exists();
                $account->forceFill(['account_status' => $pending ? 'invited' : 'deactivated'])->save();
                ManagedAccount::audit($actor, $account, 'restored');
            } else {
                abort_if($account->account_status === 'deleted' || $account->isInvitedAccount(), 409);
                if ($requested !== $account->account_status) {
                    if ($requested === 'deactivated') {
                        ManagedAccount::revokeSessions($account);
                    }
                    $account->forceFill(['account_status' => $requested])->save();
                    ManagedAccount::audit($actor, $account, $requested === 'active' ? 'activated' : 'deactivated');
                }
            }
        });

        return to_route('admin.users.index')->with('status', 'Account status updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:web']]);

        DB::transaction(function () use ($request, $user): void {
            [$actor, $account] = ManagedAccount::lockPair($request, $user);
            if ($account->account_status !== 'deleted') {
                ManagedAccount::revokeSessions($account);
                if ($account->isInvitedAccount()) {
                    AccountInvitations::revoke($actor, $account);
                }
                $account->forceFill(['account_status' => 'deleted'])->save();
                ManagedAccount::audit($actor, $account, 'deleted');
            }
        });

        return to_route('admin.users.index')->with('status', 'User moved to Deleted.');
    }

    public function signInAs(Request $request, User $user): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:web']]);
        [$actor, $account] = DB::transaction(function () use ($request, $user): array {
            [$actor, $account] = ManagedAccount::lockPair($request, $user);
            abort_unless(AccountPreview::allowed($actor, $account), 403);
            ManagedAccount::audit($actor, $account, 'preview_started');

            return [$actor, $account];
        });
        AccountPreview::switchAccount($request, $account);
        $request->session()->put(AccountPreview::KEY, [
            'actor_id' => $actor->id,
            'actor_version' => $actor->account_auth_version,
            'target_id' => $account->id,
            'target_version' => $account->account_auth_version,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        return to_route($account->homeRouteName());
    }

    public function stop(Request $request): RedirectResponse
    {
        $preview = $request->session()->get(AccountPreview::KEY);
        abort_unless(is_array($preview), 403);

        return AccountPreview::finish($request, $preview, User::find($preview['actor_id']), $request->user());
    }
}
