<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class ManagedSocialAuth
{
    public const PROVIDERS = [
        'google' => 'Google',
        'facebook' => 'Facebook',
        'github' => 'GitHub',
        'linkedin' => 'LinkedIn',
    ];

    public static function settings(string $provider): array
    {
        $row = DB::table('managed_social_auth')->where('provider', $provider)->first();
        if ($row !== null) {
            try {
                return json_decode(Crypt::decryptString($row->credentials), true, 512, JSON_THROW_ON_ERROR)
                    + ['enabled' => (bool) $row->enabled, 'source' => 'Dashboard'];
            } catch (DecryptException|\JsonException) {
                return ['client_id' => '', 'client_secret' => '', 'enabled' => false, 'source' => 'Credentials unreadable'];
            }
        }

        return [
            'client_id' => (string) config("services.$provider.client_id"),
            'client_secret' => (string) config("services.$provider.client_secret"),
            'enabled' => (bool) config("services.$provider.enabled"),
            'source' => 'Server configuration',
        ];
    }

    public static function configured(array $settings): bool
    {
        return filled($settings['client_id']) && filled($settings['client_secret']);
    }

    public static function enabled(array $settings): bool
    {
        return $settings['enabled'] && self::configured($settings);
    }

    public static function save(string $provider, array $settings): void
    {
        DB::table('managed_social_auth')->upsert([
            'provider' => $provider,
            'enabled' => $settings['enabled'],
            'credentials' => Crypt::encryptString(json_encode([
                'client_id' => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
            ], JSON_THROW_ON_ERROR)),
        ], ['provider'], ['enabled', 'credentials']);
    }

    public static function callback(string $provider): string
    {
        $path = $provider === 'google'
            ? route('social.google.callback', absolute: false)
            : route('social.provider.callback', ['provider' => $provider], absolute: false);

        return rtrim(config('app.url'), '/').$path;
    }

    public static function clientKey(array $settings): string
    {
        return hash('sha256', $settings['client_id']);
    }

    public static function flowKey(array $settings): string
    {
        return hash_hmac('sha256', $settings['client_id'], $settings['client_secret']);
    }
}
