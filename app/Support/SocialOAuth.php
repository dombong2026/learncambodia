<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class SocialOAuth
{
    private const FACEBOOK_VERSION = 'v26.0';

    public static function authorizationUrl(string $provider, array $settings, string $callback, string $state, string $verifier): string
    {
        [$endpoint, $scope] = match ($provider) {
            'google' => ['https://accounts.google.com/o/oauth2/v2/auth', 'openid profile email'],
            'facebook' => ['https://www.facebook.com/'.self::FACEBOOK_VERSION.'/dialog/oauth', 'public_profile,email'],
            'github' => ['https://github.com/login/oauth/authorize', 'read:user user:email'],
            'linkedin' => ['https://www.linkedin.com/oauth/v2/authorization', 'openid profile email'],
        };

        $query = [
            'client_id' => $settings['client_id'],
            'redirect_uri' => $callback,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
        ];

        if (in_array($provider, ['google', 'github'], true)) {
            $query['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            $query['code_challenge_method'] = 'S256';
        }
        if ($provider === 'google') {
            $query['prompt'] = 'select_account';
        }

        return $endpoint.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public static function profile(string $provider, array $settings, string $callback, string $code, string $verifier): array
    {
        $parameters = [
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'redirect_uri' => $callback,
            'grant_type' => 'authorization_code',
            'code' => $code,
        ];

        if (in_array($provider, ['google', 'github'], true)) {
            $parameters['code_verifier'] = $verifier;
        }

        $tokenResponse = match ($provider) {
            'google' => self::http()->asForm()->post('https://oauth2.googleapis.com/token', $parameters),
            'facebook' => self::http()->get('https://graph.facebook.com/'.self::FACEBOOK_VERSION.'/oauth/access_token', $parameters),
            'github' => self::http()->asForm()->post('https://github.com/login/oauth/access_token', $parameters),
            'linkedin' => self::http()->asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', $parameters),
        };

        $token = $tokenResponse->throw()->json('access_token');
        if (! is_string($token) || ! preg_match('/\A[A-Za-z0-9._~+\/=-]{1,4096}\z/', $token)) {
            throw new RuntimeException('Provider did not issue an access token.');
        }

        $client = self::http()->withToken($token);
        $profile = match ($provider) {
            'google' => $client->get('https://openidconnect.googleapis.com/v1/userinfo'),
            'facebook' => $client->get('https://graph.facebook.com/'.self::FACEBOOK_VERSION.'/me', [
                'fields' => 'id,name,email',
                'appsecret_proof' => hash_hmac('sha256', $token, $settings['client_secret']),
            ]),
            'github' => $client->withHeaders(['User-Agent' => 'LearnCambodia'])->get('https://api.github.com/user'),
            'linkedin' => $client->get('https://api.linkedin.com/v2/userinfo'),
        };

        $data = $profile->throw()->json();
        if (! is_array($data)) {
            throw new RuntimeException('Provider did not return an account profile.');
        }

        $subject = $data[$provider === 'google' || $provider === 'linkedin' ? 'sub' : 'id'] ?? null;
        if ((! is_string($subject) && ! is_int($subject)) || ! preg_match('/\A[A-Za-z0-9_-]{1,255}\z/', (string) $subject)) {
            throw new RuntimeException('Provider did not return a stable account identifier.');
        }

        $email = $data['email'] ?? null;
        $verified = in_array($provider, ['linkedin', 'google'], true) && ($data['email_verified'] ?? false) === true;
        if ($provider === 'github') {
            $email = null;
            $emails = $client->get('https://api.github.com/user/emails')->throw()->json();
            foreach (is_array($emails) ? $emails : [] as $candidate) {
                if (($candidate['verified'] ?? false) === true && ($candidate['primary'] ?? false) === true) {
                    $email = $candidate['email'] ?? null;
                    $verified = true;
                    break;
                }
            }
        }

        $email = is_string($email) && strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL)
            ? strtolower($email) : null;
        $name = $data['name'] ?? $data['login'] ?? null;

        return [
            'subject' => (string) $subject,
            'email' => $email,
            'verified' => $verified && $email !== null,
            'name' => is_string($name) && trim($name) !== '' ? mb_substr(trim($name), 0, 255) : 'LearnCambodia student',
        ];
    }

    private static function http(): PendingRequest
    {
        return Http::acceptJson()->connectTimeout(4)->timeout(8)->withoutRedirecting();
    }
}
