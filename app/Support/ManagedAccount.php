<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ManagedAccount
{
    public static function lockPair(Request $request, User $target): array
    {
        $actorId = $request->user()->id;
        $accounts = User::query()->whereKey([$actorId, $target->id])->orderBy('id')
            ->lockForUpdate()->get()->keyBy('id');
        $actor = $accounts->get($actorId);
        $account = $accounts->get($target->id);
        abort_unless($actor && $actor->isActiveAccount() && $account && AdminAccess::canEdit($actor, $account), 403);

        return [$actor, $account];
    }

    public static function revokeSessions(User $user): void
    {
        $user->forceFill([
            'account_auth_version' => $user->account_auth_version + 1,
            'remember_token' => Str::random(60),
        ]);
        $user->tokens()->delete();
    }

    public static function audit(User $actor, User $account, string $action): void
    {
        DB::table('user_account_events')->insert([
            'user_id' => $account->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'created_at' => now(),
        ]);
    }
}
