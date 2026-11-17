<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDOException;

final class PlatformBranding
{
    public const ASSETS = [
        'logo-light' => ['file' => 'logo-light.png', 'width' => 1924, 'height' => 383, 'label' => 'Light logo'],
        'logo-dark' => ['file' => 'logo-dark.png', 'width' => 1902, 'height' => 375, 'label' => 'Dark logo'],
        'favicon-16' => ['file' => 'favicon-16.png', 'width' => 16, 'height' => 16, 'label' => 'Favicon'],
        'favicon-32' => ['file' => 'favicon-32.png', 'width' => 32, 'height' => 32, 'label' => 'Favicon'],
        'touch-icon' => ['file' => 'apple-touch-icon.png', 'width' => 180, 'height' => 180, 'label' => 'Apple touch icon'],
        'app-icon-192' => ['file' => 'android-chrome-192x192.png', 'width' => 192, 'height' => 192, 'label' => 'App icon'],
        'app-icon-512' => ['file' => 'android-chrome-512x512.png', 'width' => 512, 'height' => 512, 'label' => 'App icon'],
        'share-image' => ['file' => null, 'width' => 1200, 'height' => 630, 'label' => 'Default sharing image'],
    ];

    public static function settings(): array
    {
        $request = app('request');

        if (! $request->attributes->has(self::class)) {
            try {
                $stored = DB::table('platform_branding')->where('id', 1)->value('settings');
            } catch (PDOException) {
                // The bundled identity is available during installation.
                $stored = null;
            }

            $request->attributes->set(self::class, json_decode($stored ?? '{}', true) ?? []);
        }

        return $request->attributes->get(self::class);
    }

    public static function name(): string
    {
        return self::settings()['name'] ?? config('learning.site_name', 'Learn Cambodia');
    }

    public static function shortName(): string
    {
        return self::settings()['short_name'] ?? 'LearnCambodia';
    }

    public static function text(string $key, ?string $locale = null): ?string
    {
        return self::settings()['translations'][$locale ?? app()->getLocale()][$key] ?? null;
    }

    public static function asset(string $slot): array
    {
        $custom = self::settings()['assets'][$slot] ?? null;
        $default = self::ASSETS[$slot];
        $url = $custom
            ? route('brand.asset', ['slot' => $slot, 'version' => $custom['version']])
            : ($default['file'] ? asset('brand/'.$default['file']).'?v=20261114' : null);

        return ['url' => $url, 'custom' => $custom !== null] + ($custom ?? []) + $default;
    }

    public static function usesCustomManifest(): bool
    {
        $settings = self::settings();

        return isset($settings['name'])
            || isset($settings['short_name'])
            || isset($settings['assets']['app-icon-192'])
            || isset($settings['assets']['app-icon-512']);
    }

    public static function assetPath(string $slot, string $version): string
    {
        return 'site-branding/'.$slot.'/'.$version;
    }

    public static function pageMeta(?string $pageTitle, ?string $pageDescription, ?string $pageImage = null): array
    {
        $name = self::name();
        $defaultTitle = self::text('meta_title');
        $defaultDescription = self::text('meta_description');
        $pageTitle = self::plainText($pageTitle);
        $pageDescription = self::plainText($pageDescription);
        $home = request()->routeIs('home');

        return [
            'title' => match (true) {
                $home && $defaultTitle !== null => $defaultTitle,
                $pageTitle !== '' => $pageTitle.' · '.$name,
                default => $defaultTitle ?? $name,
            },
            'description' => match (true) {
                $home && $defaultDescription !== null => $defaultDescription,
                $pageDescription !== '' => $pageDescription,
                default => $defaultDescription ?? $name.' — English learning, international exam preparation and teacher development.',
            },
            'image' => filled($pageImage) ? $pageImage : self::asset('share-image')['url'],
        ];
    }

    private static function plainText(?string $value): string
    {
        return trim(strip_tags(html_entity_decode($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
