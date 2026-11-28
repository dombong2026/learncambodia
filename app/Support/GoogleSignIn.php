<?php

namespace App\Support;

final class GoogleSignIn
{
    public static function enabled(): bool
    {
        return ManagedSocialAuth::enabled(ManagedSocialAuth::settings('google'));
    }

    public static function callback(): string
    {
        return ManagedSocialAuth::callback('google');
    }
}
