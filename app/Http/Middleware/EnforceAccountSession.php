<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AccountPreview;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceAccountSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user('web');
        $preview = $request->session()->get(AccountPreview::KEY);
        if ($preview !== null) {
            $actor = User::find($preview['actor_id']);
            $target = $account?->id === $preview['target_id'] ? $account : null;
            if (! $actor || ! $actor->isActiveAccount() || ! $target || ! AccountPreview::allowed($actor, $target)
                || now()->timestamp >= $preview['expires_at']
                || $actor->account_auth_version !== $preview['actor_version']
                || $target->account_auth_version !== $preview['target_version']) {
                return AccountPreview::finish($request, $preview, $actor, $target);
            }

            abort_unless(AccountPreview::permits($request), 403, 'This action is unavailable during a read-only support session.');
        } elseif ($account && (! $account->isActiveAccount()
            || $account->account_auth_version !== (int) $request->session()->get('account_auth_version', 0))) {
            AccountPreview::switchAccount($request, null);

            return $request->expectsJson()
                ? response()->json(['message' => 'Sign in again to continue.'], 401)
                : to_route('login')->with('status', 'Sign in again to continue.');
        }

        return $next($request);
    }
}
