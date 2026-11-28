<?php

namespace App\Http\Controllers\Auth;

use App\Support\AdminAccess;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
use App\Models\User;
use App\Support\AccountSignIn;
use App\Support\GoogleSignIn;
use App\Support\ManagedSocialAuth;
use App\Support\ReadingReturn;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class GoogleSignInController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        if ($next = ReadingReturn::valid($request->query('next'))) {
            $request->session()->put('auth.reading_return', $next);
        }

        return $this->authorizeWithGoogle($request, 'sign-in');
    }

    public function connect(Request $request): RedirectResponse
    {
        return $this->authorizeWithGoogle($request, 'link');
    }

    private function authorizeWithGoogle(Request $request, string $intent): RedirectResponse
    {
        $settings = ManagedSocialAuth::settings('google');
        abort_unless(ManagedSocialAuth::enabled($settings), 404);
        $state = Str::random(64);
        $verifier = Str::random(64);
        $request->session()->put('auth.google', [
            'intent' => $intent, 'user_id' => $request->user()?->getKey(), 'state' => $state,
            'verifier' => $verifier, 'expires' => now()->addMinutes(5)->timestamp,
            'configuration' => ManagedSocialAuth::flowKey($settings),
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $settings['client_id'],
            'redirect_uri' => GoogleSignIn::callback(),
            'scope' => 'openid email profile',
            'response_type' => 'code',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->query('state');
        $testState = $request->session()->get('auth.social.google.state');
        if (is_string($state) && is_string($testState) && hash_equals($testState, $state)) {
            return app(SocialAuthController::class)->callback($request, 'google');
        }

        $settings = ManagedSocialAuth::settings('google');
        abort_unless(ManagedSocialAuth::enabled($settings), 404);
        $flow = $request->session()->pull('auth.google');
        abort_unless($flow && $flow['expires'] > now()->timestamp && is_string($state)
            && hash_equals($flow['state'], $state) && $flow['user_id'] === $request->user()?->getKey(), 400, 'This sign-in request has expired.');

        $landing = $flow['intent'] === 'link' ? $this->settingsRoute($request) : 'login';
        if (isset($flow['configuration']) && ! hash_equals($flow['configuration'], ManagedSocialAuth::flowKey($settings))) {
            return redirect()->route($landing)->withErrors(['google' => 'Google sign-in settings changed. Start again.']);
        }
        $code = $request->query('code');
        if (!is_string($code) || $code === '' || strlen($code) > 2048) {
            return redirect()->route($landing)->withErrors(['google' => 'Google sign-in was cancelled.']);
        }

        try {
            $token = Http::asForm()->connectTimeout(4)->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'client_id' => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
                'redirect_uri' => GoogleSignIn::callback(),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'code_verifier' => $flow['verifier'],
            ])->throw()->json('access_token');
            if (!is_string($token) || $token === '') {
                return redirect()->route($landing)->withErrors(['google' => 'Google did not return an access token.']);
            }
            $profile = Http::withToken($token)->acceptJson()->connectTimeout(4)->timeout(10)
                ->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
        } catch (ConnectionException|RequestException) {
            return redirect()->route($landing)->withErrors(['google' => 'Google sign-in could not be completed. Try again.']);
        }

        $subject = $profile['sub'] ?? null;
        $email = $profile['email'] ?? null;
        if (!is_string($subject) || !preg_match('/\A[A-Za-z0-9_-]{1,255}\z/', $subject)
            || !is_string($email) || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || ($profile['email_verified'] ?? false) !== true) {
            return redirect()->route($landing)->withErrors(['google' => 'A verified Google account is required.']);
        }

        if ($flow['intent'] === 'link') {
            $account = $request->user();
            abort_unless($account !== null, 403);
            try {
                $account->forceFill(['google_subject' => $subject])->save();
            } catch (UniqueConstraintViolationException) {
                return redirect()->route($landing)->withErrors(['google' => 'That Google account is linked to another account.']);
            }

            return redirect()->route($landing)->with('status', 'Google account connected.');
        }

        if ($user = User::query()->where('google_subject', $subject)->first()) {
            return AccountSignIn::start($request, $user);
        }

        $email = Str::lower($email);
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return redirect()->route('login')->withErrors(['google' => 'Sign in with your current account first to connect Google.']);
        }

        if (!str_ends_with($email, '@gmail.com') && blank($profile['hd'] ?? null)) {
            return redirect()->route('register')->withErrors(['google' => 'Use email registration for this address.']);
        }

        $name = $profile['name'] ?? null;
        $request->session()->put('auth.google_registration', [
            'email' => $email, 'subject' => $subject, 'name' => is_string($name) && trim($name) !== '' ? Str::limit(trim($name), 255, '') : 'LearnCambodia student',
            'expires' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->route('social.google.registration');
    }

    public function registration(Request $request): View
    {
        abort_unless(GoogleSignIn::enabled() && $this->pendingRegistration($request), 404);

        return view('auth.google-registration', [
            'publishedPolicies' => ContentEntry::publishedLegalPolicies(),
        ]);
    }

    public function finishRegistration(Request $request): RedirectResponse
    {
        $pending = $this->pendingRegistration($request);
        abort_unless(GoogleSignIn::enabled() && $pending, 404);
        $request->validate(['terms' => ['required', 'accepted']]);
        $request->session()->forget('auth.google_registration');

        if (User::query()->whereRaw('LOWER(email) = ?', [$pending['email']])->exists()) {
            return redirect()->route('login')->withErrors(['google' => 'That email is already registered. Sign in to the existing account.']);
        }

        try {
            $user = (new User)->forceFill([
                'name' => $pending['name'],
                'email' => $pending['email'],
                'password' => Str::random(64),
                'role' => User::ROLE_STUDENT,
                'google_subject' => $pending['subject'],
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
            ]);
            $user->save();
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('login')->withErrors(['google' => 'That account is already registered. Sign in to continue.']);
        }

        return AccountSignIn::start($request, $user);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['google_subject' => null])->save();

        return redirect()->route($this->settingsRoute($request))->with('status', 'Google account disconnected.');
    }

    private function settingsRoute(Request $request): string
    {
        return AdminAccess::can($request->user(), 'settings') ? 'admin.system-settings.social-auth' : 'security.methods';
    }

    private function pendingRegistration(Request $request): ?array
    {
        $pending = $request->session()->get('auth.google_registration');

        return $pending && $pending['expires'] > now()->timestamp ? $pending : null;
    }
}
