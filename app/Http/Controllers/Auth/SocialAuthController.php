<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
use App\Models\User;
use App\Support\AccountSignIn;
use App\Support\AdminAccess;
use App\Support\ManagedSocialAuth;
use App\Support\OutboundMail;
use App\Support\ReadingReturn;
use App\Support\SocialOAuth;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

final class SocialAuthController extends Controller
{
    public function start(Request $request, string $provider): RedirectResponse
    {
        $request->session()->put('auth.reading_return', ReadingReturn::valid($request->query('next')));

        return $this->authorize($request, $provider, 'sign-in');
    }

    public function connect(Request $request, string $provider): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            return to_route('verification.notice');
        }

        return $this->authorize($request, $provider, 'link');
    }

    public function test(Request $request, string $provider): RedirectResponse
    {
        return $this->authorize($request, $provider, 'test');
    }

    private function authorize(Request $request, string $provider, string $intent): RedirectResponse
    {
        $settings = ManagedSocialAuth::settings($provider);
        abort_unless(
            $intent === 'test' ? ManagedSocialAuth::configured($settings) : ManagedSocialAuth::enabled($settings),
            404
        );

        $state = Str::random(64);
        $verifier = Str::random(64);
        $request->session()->put('auth.social.'.$provider, [
            'state' => $state,
            'verifier' => $verifier,
            'intent' => $intent,
            'user_id' => $request->user()?->getKey(),
            'expires' => now()->addMinutes(5)->timestamp,
            'configuration' => ManagedSocialAuth::flowKey($settings),
        ]);

        return redirect()->away(SocialOAuth::authorizationUrl(
            $provider, $settings, ManagedSocialAuth::callback($provider), $state, $verifier
        ));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $flow = $request->session()->pull('auth.social.'.$provider);
        $state = $request->query('state');
        abort_unless($flow && $flow['expires'] > now()->timestamp && is_string($state)
            && hash_equals($flow['state'], $state) && $flow['user_id'] === $request->user()?->getKey(),
            400, 'This sign-in request has expired.');

        $intent = $flow['intent'];
        $landing = match ($intent) {
            'test' => 'admin.system-settings.social-auth',
            'link' => 'security.methods',
            default => 'login',
        };
        $parameters = $intent === 'test' ? ['configure' => $provider] : [];
        if ($intent !== 'sign-in') {
            abort_unless($request->user() && time() - (int) $request->session()->get('auth.password_confirmed_at', 0)
                < config('auth.password_timeout', 10800), 403);
        }
        if ($intent === 'test') {
            abort_unless(AdminAccess::can($request->user(), 'settings'), 403);
        }

        $settings = ManagedSocialAuth::settings($provider);
        if (! ManagedSocialAuth::configured($settings) || ($intent !== 'test' && ! ManagedSocialAuth::enabled($settings))
            || ! hash_equals($flow['configuration'], ManagedSocialAuth::flowKey($settings))) {
            return $this->problem($landing, 'Social sign-in settings changed. Start again.', $parameters);
        }

        $label = ManagedSocialAuth::PROVIDERS[$provider];
        $code = $request->query('code');
        if ($request->has('error') || ! is_string($code) || $code === '' || strlen($code) > 4096) {
            return $this->problem($landing, "$label authorization was cancelled or denied.", $parameters);
        }

        try {
            $profile = SocialOAuth::profile($provider, $settings, ManagedSocialAuth::callback($provider), $code, $flow['verifier']);
        } catch (ConnectionException|RequestException|RuntimeException) {
            return $this->problem($landing, "$label sign-in could not be completed. Check the app credentials, callback URL, and requested permissions.", $parameters);
        }

        if ($intent === 'test') {
            if ($provider === 'google' && ! $profile['verified']) {
                return $this->problem($landing, 'The Google test needs a verified Google email address.', $parameters);
            }

            return to_route($landing, $parameters)->with('status', "$label authorization and profile lookup passed.");
        }

        $identity = [
            'provider' => $provider,
            'client' => ManagedSocialAuth::clientKey($settings),
            'identity' => hash('sha256', $profile['subject']),
        ];

        if ($intent === 'link') {
            abort_unless($request->user()->hasVerifiedEmail(), 403);

            return $this->link($request->user(), $identity, $label);
        }

        $existing = DB::table('social_identities')->where($identity)->value('user_id');
        if ($existing !== null && ($account = User::find($existing))) {
            return AccountSignIn::start($request, $account);
        }

        if ($profile['verified'] && $this->emailExists($profile['email'])) {
            return $this->problem('login', "Sign in to your existing LearnCambodia account, then connect $label in Sign-in and security.");
        }

        $request->session()->put('auth.social_registration.'.$provider, [
            'identity' => $identity,
            'configuration' => $flow['configuration'],
            'name' => $profile['name'],
            'email' => $profile['email'],
            'verified' => $profile['verified'],
            'expires' => now()->addMinutes(10)->timestamp,
        ]);

        return to_route('social.provider.registration', ['provider' => $provider]);
    }

    public function registration(Request $request, string $provider): View
    {
        return view('auth.social-registration', [
            'provider' => $provider,
            'label' => ManagedSocialAuth::PROVIDERS[$provider],
            'pending' => $this->pendingRegistration($request, $provider),
            'publishedPolicies' => ContentEntry::publishedLegalPolicies(),
        ]);
    }

    public function finishRegistration(Request $request, string $provider): RedirectResponse
    {
        $pending = $this->pendingRegistration($request, $provider);
        $rules = ['terms' => ['required', 'accepted']];
        if (! $pending['verified']) {
            $rules['email'] = ['required', 'email:rfc', 'max:255'];
        }
        $validated = $request->validate($rules);
        $email = $pending['verified'] ? $pending['email'] : Str::lower($validated['email']);

        if ($this->emailExists($email)) {
            return $this->problem('login', 'Sign in to the existing account, then connect your provider in Sign-in and security.');
        }

        if ($pending['verified']) {
            return $this->createAccount($request, $provider, $pending, $email);
        }

        $redirect = to_route('social.provider.registration', ['provider' => $provider]);
        if (! OutboundMail::deliveryEnabled()) {
            return $redirect->with('social_auth_error', 'Email confirmation is temporarily unavailable. Try again later.');
        }

        $nonce = Str::random(64);
        $link = rtrim(config('app.url'), '/').URL::temporarySignedRoute(
            'social.provider.confirm-email',
            now()->addMinutes(15),
            ['provider' => $provider, 'challenge' => $nonce],
            absolute: false
        );

        try {
            $sent = Mail::raw("Open this link in the browser where you started creating your LearnCambodia account:\n\n".$link,
                fn ($message) => $message->to($email)->subject('Confirm your LearnCambodia registration'));
        } catch (\Exception) {
            $sent = null;
        }

        if ($sent === null) {
            return $redirect->with('social_auth_error', 'We could not send your confirmation email. Try again later.');
        }

        $request->session()->put('auth.social_registration.'.$provider, array_replace($pending, [
            'email' => $email,
            'challenge' => hash('sha256', $nonce),
            'expires' => now()->addMinutes(15)->timestamp,
        ]));

        return $redirect->with('social_registration_sent', true);
    }

    public function confirmRegistrationEmail(Request $request, string $provider): RedirectResponse
    {
        $pending = $this->pendingRegistration($request, $provider);
        $nonce = $request->query('challenge');
        abort_unless(isset($pending['challenge']) && is_string($nonce)
            && hash_equals($pending['challenge'], hash('sha256', $nonce)), 400,
            'Open the confirmation link in the browser where you started registration.');

        return $this->createAccount($request, $provider, $pending, $pending['email']);
    }

    private function createAccount(Request $request, string $provider, array $pending, string $email): RedirectResponse
    {
        $request->session()->forget('auth.social_registration.'.$provider);
        if ($this->emailExists($email)) {
            return $this->problem('login', 'Sign in to the existing account, then connect your provider in Sign-in and security.');
        }

        try {
            $account = DB::transaction(function () use ($pending, $email): User {
                $account = (new User)->forceFill([
                    'name' => $pending['name'],
                    'email' => $email,
                    'password' => Str::random(64),
                    'role' => User::ROLE_STUDENT,
                    'email_verified_at' => now(),
                    'terms_accepted_at' => now(),
                ]);
                $account->save();
                DB::table('social_identities')->insert($pending['identity'] + ['user_id' => $account->getKey()]);

                return $account;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->problem('login', 'That account is already registered. Sign in to continue.');
        }

        return AccountSignIn::start($request, $account);
    }

    public function disconnect(Request $request, string $provider): RedirectResponse
    {
        DB::table('social_identities')->where('user_id', $request->user()->getKey())->where('provider', $provider)->delete();

        return to_route('security.methods')->with('status', ManagedSocialAuth::PROVIDERS[$provider].' account disconnected.');
    }

    private function link(User $account, array $identity, string $label): RedirectResponse
    {
        try {
            DB::table('social_identities')->insert($identity + ['user_id' => $account->getKey()]);
        } catch (UniqueConstraintViolationException) {
            if (! DB::table('social_identities')->where($identity)->where('user_id', $account->getKey())->exists()) {
                return $this->problem('security.methods', "Disconnect the existing $label link first, or choose an account that is not linked elsewhere.");
            }
        }

        return to_route('security.methods')->with('status', "$label account connected.");
    }

    private function pendingRegistration(Request $request, string $provider): array
    {
        $pending = $request->session()->get('auth.social_registration.'.$provider);
        $settings = ManagedSocialAuth::settings($provider);
        abort_unless($pending && $pending['expires'] > now()->timestamp && ManagedSocialAuth::enabled($settings)
            && hash_equals($pending['configuration'], ManagedSocialAuth::flowKey($settings)), 404);

        return $pending;
    }

    private function emailExists(string $email): bool
    {
        return User::query()->whereRaw('LOWER(email) = ?', [$email])->exists();
    }

    private function problem(string $route, string $message, array $parameters = []): RedirectResponse
    {
        return to_route($route, $parameters)->with('social_auth_error', $message);
    }
}
