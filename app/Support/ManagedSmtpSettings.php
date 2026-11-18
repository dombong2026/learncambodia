<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class ManagedSmtpSettings
{
    public const MAILER = 'dashboard-smtp';

    public static function saved(): ?array
    {
        if (! Schema::hasTable('managed_smtp_settings')) {
            return null;
        }

        $payload = DB::table('managed_smtp_settings')->where('id', 1)->value('payload');

        return $payload === null ? null : json_decode(Crypt::decryptString($payload), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function save(array $settings): void
    {
        DB::table('managed_smtp_settings')->upsert([[
            'id' => 1,
            'payload' => Crypt::encryptString(json_encode($settings, JSON_THROW_ON_ERROR)),
            'updated_at' => now(),
        ]], ['id'], ['payload', 'updated_at']);
    }

    public static function reset(): void
    {
        DB::table('managed_smtp_settings')->where('id', 1)->delete();
    }

    public static function refresh(): void
    {
        $resolved = app()->resolved('mail.manager');
        $manager = app('mail.manager');
        if ($resolved && $manager instanceof MailManager) {
            self::apply($manager);
        }
    }

    public static function apply(MailManager $manager): void
    {
        $path = 'mail.mailers.'.self::MAILER;
        $current = config($path);
        try {
            $settings = self::saved();
            $next = $settings === null ? null : [
                'transport' => 'smtp',
                'host' => $settings['host'],
                'port' => $settings['port'],
                'scheme' => $settings['scheme'],
                'username' => $settings['username'],
                'password' => $settings['password'],
                'timeout' => 12,
                'require_tls' => $settings['scheme'] === 'smtp',
                'from' => ['address' => $settings['from_address'], 'name' => $settings['from_name']],
            ];
        } catch (DecryptException|\JsonException $error) {
            Log::warning('Dashboard SMTP settings could not be read.', ['error_type' => get_class($error)]);
            // Fail closed rather than using a different sender when the saved profile is unreadable.
            $next = ['transport' => null];
        }

        if ($next === $current) {
            return;
        }

        if ($current === null && $next !== null) {
            config(['mail.smtp_server_default' => config('mail.default')]);
        }

        config([
            $path => $next,
            'mail.default' => $next === null ? config('mail.smtp_server_default') : self::MAILER,
        ]);
        $manager->purge(self::MAILER);
    }
}
