<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class PromoPlayer
{
    public const DIRECTORY = 'editor-media/promo-player';

    public static function settings(bool $lock = false): array
    {
        $query = DB::table('promo_player_settings')->where('id', 1);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();

        return ['revision' => (int) ($row->revision ?? 0)] +
            json_decode($row->configuration ?? '{}', true, flags: JSON_THROW_ON_ERROR) + [
                'title' => 'Promo Player',
                'accent' => '#ffb459',
                'volume' => 75,
                'loop' => false,
                'homepage' => false,
                'sidebar' => false,
                'background' => null,
                'tracks' => [],
                'link_label' => null,
                'link_url' => null,
            ];
    }

    public static function visible(array $settings, string $placement): bool
    {
        return $settings[$placement] && $settings['tracks'] !== [];
    }

    public static function paths(array $settings): array
    {
        return array_values(array_filter([
            $settings['background']['path'] ?? null,
            ...array_column($settings['tracks'], 'path'),
        ]));
    }

    public static function mediaUrl(string $id, bool $preview): string
    {
        return route($preview ? 'admin.system-settings.player.promo.media' : 'promo-player.media', ['media' => $id], false);
    }
}
