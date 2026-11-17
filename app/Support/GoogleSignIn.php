<?php

namespace App\Support;

final class GoogleSignIn
{
    public static function enabled(): bool
    {
        return (bool) config('services.google.enabled')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public static function callback(): string
    {
        return rtrim(config('app.url'), '/').route('social.google.callback', absolute: false);
    }
}
