<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\OutboundMail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

final class PasswordRecoveryController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        // The initial installation uses MAIL_MAILER=log; links must never be
        // represented as delivered while outgoing mail is disabled.
        if (! OutboundMail::deliveryEnabled()) {
            return back()->with('status', __('auth.email_delivery_disabled'));
        }

        if (User::query()->where('email', $request->input('email'))->where('account_status', 'active')->exists()) {
            try {
                Password::sendResetLink($request->only('email'));
            } catch (\Exception $error) {
                Log::warning('Password reset mail failed.', [
                    'error_type' => get_class($error),
                    'error_code' => (int) $error->getCode(),
                ]);
            }
        }

        // Generic response prevents account enumeration.
        return back()->with('status', __('auth.reset_requested_generic'));
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        if (! User::query()->where('email', $credentials['email'])->where('account_status', 'active')->exists()) {
            return back()->withErrors(['email' => __(Password::INVALID_TOKEN)])->onlyInput('email');
        }

        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)])->onlyInput('email');
        }

        return redirect()->route('login')->with('status', __('auth.password_changed'));
    }
}
