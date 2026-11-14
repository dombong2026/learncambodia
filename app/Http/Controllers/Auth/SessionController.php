<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ReadingReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function loginForm(Request $request): View
    {
        $returnTo = ReadingReturn::valid($request->query('next'));
        // Clear stale targets when opening sign-in from an ordinary page.
        $request->session()->forget('auth.reading_return');
        if ($returnTo !== null) {
            $request->session()->put('auth.reading_return', $returnTo);
        }

        return view('auth.login', ['authReturnTo' => $returnTo]);
    }

    public function registerForm(Request $request): View
    {
        $returnTo = ReadingReturn::valid($request->query('next'));
        $request->session()->forget('auth.reading_return');
        if ($returnTo !== null) {
            $request->session()->put('auth.reading_return', $returnTo);
        }

        return view('auth.register', ['authReturnTo' => $returnTo]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $key = hash('sha256', Str::lower($credentials['email']).'|'.$request->ip());
        // Shared IP throttle stops password spraying across many distinct
        // accounts; the per-account throttle remains active independently.
        $ipKey = 'login-ip:'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)
            || RateLimiter::tooManyAttempts($ipKey, 20)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Please try again later.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            RateLimiter::hit($ipKey, 60);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        // Explicit public reading journeys return to the same article/exam.
        // All other successful sign-ins go to the user's own role dashboard.
        $returnTo = ReadingReturn::valid($request->session()->pull('auth.reading_return'));

        return $returnTo !== null
            ? redirect()->to($returnTo)
            : redirect()->route($request->user()->homeRouteName());
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['required', 'accepted'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->forceFill(['terms_accepted_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        $returnTo = ReadingReturn::valid($request->session()->pull('auth.reading_return'));

        return $returnTo !== null
            ? redirect()->to($returnTo)
            : redirect()->route($user->homeRouteName());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
