<?php

namespace App\Http\Controllers\Auth;

use App\Support\AdminAccess;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

final class AccountSecurityController extends Controller
{
    public function methods(Request $request): View
    {
        return view('workspace.account-security', [
            'account' => $request->user(),
            'passkeys' => $request->user()->passkeys()->latest()->get(),
            'socialAccounts' => DB::table('social_identities')->where('user_id', $request->user()->getKey())->pluck('client', 'provider'),
        ]);
    }

    public function passwordForm(): View
    {
        return view('auth.confirm-security-password');
    }

    public function confirmPassword(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $request->session()->passwordConfirmed();

        $route = AdminAccess::can($request->user(), 'settings') ? 'admin.system-settings.security' : 'security.methods';

        return redirect()->intended(route($route));
    }

    public function enable(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        DB::transaction(function () use ($request, $enable): void {
            $enable(User::query()->lockForUpdate()->findOrFail($request->user()->getKey()));
        });

        return back()->with('status', 'Scan the setup code and confirm your authenticator.');
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        DB::transaction(function () use ($request, $confirm, $data): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            abort_unless($user->two_factor_secret !== null && !$user->hasEnabledTwoFactorAuthentication(), 409);
            $confirm($user, $data['code']);
        });

        return back()->with('status', 'Two-factor authentication is enabled.');
    }

    public function regenerate(Request $request, GenerateNewRecoveryCodes $generate, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        DB::transaction(function () use ($request, $generate, $provider, $data): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            abort_unless($user->hasEnabledTwoFactorAuthentication(), 409);
            $this->verify($user, $data['code'], $provider);
            $generate($user);
        });

        return back()->with('status', 'New recovery codes created.');
    }

    public function disable(Request $request, DisableTwoFactorAuthentication $disable, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        DB::transaction(function () use ($request, $disable, $provider): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            if ($user->hasEnabledTwoFactorAuthentication()) {
                $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
                $this->verify($user, $data['code'], $provider);
            }
            $disable($user);
        });

        return back()->with('status', 'Two-factor authentication removed.');
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        return $this->pendingSignIn($request) === null
            ? redirect()->route('login')
            : view('auth.security-challenge');
    }

    public function finishChallenge(Request $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $pending = $this->pendingSignIn($request);
        if ($pending === null) {
            return redirect()->route('login');
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $limit = 'account-factor:'.$pending['user'];
        abort_if(RateLimiter::tooManyAttempts($limit, 8), 429);
        RateLimiter::hit($limit, 300);

        $user = DB::transaction(function () use ($pending, $data, $provider): User {
            $account = User::query()->lockForUpdate()->findOrFail($pending['user']);
            abort_unless($account->hasEnabledTwoFactorAuthentication(), 403);
            $this->verify($account, $data['code'], $provider);

            return $account;
        });
        RateLimiter::clear($limit);

        return AccountSignIn::complete($request, $user, $pending['remember']);
    }

    private function pendingSignIn(Request $request): ?array
    {
        $pending = $request->session()->get('auth.factor');
        if (!$pending || $pending['expires'] <= now()->timestamp) {
            $request->session()->forget('auth.factor');

            return null;
        }

        return $pending;
    }

    private function verify(User $account, string $code, TwoFactorAuthenticationProvider $provider): void
    {
        if (preg_match('/\A[0-9]{6}\z/', $code) && $provider->verify(decrypt($account->two_factor_secret), $code)) {
            return;
        }

        foreach ($account->recoveryCodes() as $recoveryCode) {
            if (hash_equals($recoveryCode, $code)) {
                $account->replaceRecoveryCode($recoveryCode);

                return;
            }
        }

        throw ValidationException::withMessages(['code' => 'Enter a valid authenticator or recovery code.']);
    }
}
