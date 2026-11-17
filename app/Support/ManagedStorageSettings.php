<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class ManagedStorageSettings
{
    public const PROVIDERS = ['r2' => 'Cloudflare R2', 's3' => 'Amazon S3'];

    public static function diskKey(string $provider): string
    {
        return $provider === 's3' ? 'managed-s3' : $provider;
    }

    public static function saved(string $provider): ?array
    {
        $payload = DB::table('managed_storage_settings')->where('provider', $provider)->value('payload');

        return $payload === null ? null : json_decode(Crypt::decryptString($payload), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function effective(string $provider, ?array $settings): array
    {
        $base = config('filesystems.disks.'.self::diskKey($provider), []);

        return array_replace($base, $settings ?? []);
    }

    public static function configured(string $provider, array $settings): bool
    {
        $required = $provider === 'r2' ? ['bucket', 'endpoint', 'key', 'secret'] : ['bucket', 'region'];

        return collect($required)->every(fn ($key) => filled($settings[$key] ?? null));
    }

    public static function save(string $provider, array $settings): void
    {
        DB::table('managed_storage_settings')->upsert([[
            'provider' => $provider,
            'payload' => Crypt::encryptString(json_encode($settings, JSON_THROW_ON_ERROR)),
            'updated_at' => now(),
        ]], ['provider'], ['payload', 'updated_at']);
    }
}
