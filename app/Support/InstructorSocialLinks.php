<?php

namespace App\Support;

final class InstructorSocialLinks
{
    public const LABELS = [
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'linkedin' => 'LinkedIn',
        'tiktok' => 'TikTok',
    ];

    private const DOMAINS = [
        'facebook' => ['facebook.com', 'fb.com', 'fb.me'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'linkedin' => ['linkedin.com'],
        'tiktok' => ['tiktok.com'],
    ];

    public static function valid(string $platform, mixed $url): bool
    {
        if (!is_string($url) || strlen($url) > 300 || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        foreach (self::DOMAINS[$platform] ?? [] as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    public static function allowed(?array $links): array
    {
        return array_filter($links ?? [], fn ($url, $platform) => self::valid((string) $platform, $url), ARRAY_FILTER_USE_BOTH);
    }
}
