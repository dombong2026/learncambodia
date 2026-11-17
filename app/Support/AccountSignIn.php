<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AccountSignIn
{
    public static function start(Request $request, User $user, bool $remember = false): RedirectResponse
    {
        abort_unless($user->isActiveAccount(), 403);
        $request->session()->forget('auth.factor');

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->regenerate();
            $request->session()->put('auth.factor', [
                'user' => $user->getKey(), 'remember' => $remember, 'expires' => now()->addMinutes(5)->timestamp,
                'version' => $user->account_auth_version,
            ]);

            return redirect()->route('security.challenge');
        }

        return self::complete($request, $user, $remember);
    }

    public static function complete(Request $request, User $user, bool $remember): RedirectResponse
    {
        $factor = $request->session()->get('auth.factor');
        abort_unless($user->isActiveAccount()
            && ($factor === null || $user->account_auth_version === (int) ($factor['version'] ?? 0)), 403);
        $request->session()->forget('auth.factor');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $returnTo = ReadingReturn::valid($request->session()->pull('auth.reading_return'));

        return $returnTo !== null ? redirect()->to($returnTo) : redirect()->route($user->homeRouteName());
    }
}
