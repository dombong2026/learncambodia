<?php

namespace App\Support;

final class CourseSupportChannels
{
    public const EXAMPLES = [
        'Telegram' => 'https://t.me/username',
        'Messenger' => 'https://m.me/username',
        'LINE' => 'https://line.me/ti/p/~lineid',
        'WhatsApp' => 'https://wa.me/85512345678',
    ];

    private const PATHS = [
        'Telegram' => ['t.me' => '~^/[A-Za-z0-9_]{1,32}/?$~D'],
        'Messenger' => ['m.me' => '~^/[A-Za-z0-9][A-Za-z0-9.]{0,99}/?$~D'],
        'LINE' => [
            'line.me' => '~^/(?:ti/p/[\~@A-Za-z0-9_.-]+|R/ti/p/(?:%40|@)?[A-Za-z0-9_.-]+)/?$~D',
            'lin.ee' => '~^/[A-Za-z0-9]+/?$~D',
        ],
        'WhatsApp' => ['wa.me' => '~^/[1-9][0-9]{6,14}/?$~D'],
    ];

    public static function accepts(string $platform, string $url): bool
    {
        if (preg_match('/[^\x21-\x7e]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || array_diff(array_keys($parts), ['scheme', 'host', 'path']) !== []) {
            return false;
        }

        $pattern = self::PATHS[$platform][strtolower($parts['host'] ?? '')] ?? null;

        return $pattern !== null && preg_match($pattern, $parts['path'] ?? '') === 1;
    }

    public static function enabled(?array $settings): array
    {
        $links = [];

        foreach (array_keys(self::EXAMPLES) as $platform) {
            $entry = $settings[$platform] ?? [];
            $url = $entry['url'] ?? null;
            if (($entry['enabled'] ?? false) === true && is_string($url) && self::accepts($platform, $url)) {
                $links[$platform] = $url;
            }
        }

        return $links;
    }
}
