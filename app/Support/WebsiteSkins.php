<?php
declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Pure, allowlisted website skin registry and deterministic calendar resolver.
 * Integration must explicitly opt in; the legacy frontend remains the default.
 */
final class WebsiteSkins
{
    public const TIMEZONE = 'Asia/Phnom_Penh';

    public const WEEKLY = [
        0 => 'natural-premium',
        1 => 'sorya-sunrise',
        2 => 'preah-forest',
        3 => 'water-flow',
        4 => 'emerald-jungle',
        5 => 'golden-khemara',
        6 => 'peaceful-jasmine',
    ];

    /** Palette order: background, surface, foreground, primary, accent. */
    public const CATALOG = [
        'natural-premium' => [
            'day' => 'Sunday', 'name' => 'Natural Premium',
            'hero' => 'editorial-split', 'header' => 'floating-search',
            'sections' => 'garden-grid', 'footer' => 'open-columns',
            'art_direction' => 'ivory paper, botanical linework, quiet learning garden',
            'light' => ['#F8F6EF', '#FFFFFF', '#243B30', '#365F45', '#B99B60'],
            'dark' => ['#13221B', '#1E3026', '#F2F4E8', '#B5D7AA', '#E5C990'],
        ],
        'sorya-sunrise' => [
            'day' => 'Monday', 'name' => 'Sorya Sunrise',
            'hero' => 'horizon-split', 'header' => 'sunrise-band',
            'sections' => 'pathway-steps', 'footer' => 'horizon-footer',
            'art_direction' => 'morning hills, soft sunlight, open book',
            'light' => ['#FFF3DF', '#FFFCF5', '#4B3526', '#8B4328', '#EBA652'],
            'dark' => ['#2A211B', '#392C23', '#FFF1DF', '#FFD09A', '#EBA652'],
        ],
        'preah-forest' => [
            'day' => 'Tuesday', 'name' => 'Preah Forest',
            'hero' => 'forest-retreat', 'header' => 'quiet-minimal',
            'sections' => 'reading-columns', 'footer' => 'forest-canopy',
            'art_direction' => 'layered forest, ferns, dappled light',
            'light' => ['#EEF4EA', '#FAFCF7', '#243D30', '#315A43', '#9CB58C'],
            'dark' => ['#101E17', '#1A2C22', '#EAF4E8', '#B2D2A6', '#8CA981'],
        ],
        'water-flow' => [
            'day' => 'Wednesday', 'name' => 'Water Flow',
            'hero' => 'fluid-panorama', 'header' => 'streamlined',
            'sections' => 'flowing-bands', 'footer' => 'wave-footer',
            'art_direction' => 'river ripples, lotus water, clear sky',
            'light' => ['#EAF7F8', '#FFFFFF', '#163A45', '#176477', '#78B9C4'],
            'dark' => ['#10252E', '#193541', '#E6F9FC', '#8EDBE6', '#6BB8C8'],
        ],
        'emerald-jungle' => [
            'day' => 'Thursday', 'name' => 'Emerald Jungle',
            'hero' => 'immersive-canopy', 'header' => 'deep-green',
            'sections' => 'bento-catalog', 'footer' => 'layered-canopy',
            'art_direction' => 'lush jungle foliage, emerald light, tropical leaves',
            'light' => ['#EAF2E7', '#F9FCF6', '#173A2B', '#126146', '#B8B970'],
            'dark' => ['#0E251A', '#173929', '#E9F6E8', '#98DEB3', '#D2C985'],
        ],
        'golden-khemara' => [
            'day' => 'Friday', 'name' => 'Golden Khemara',
            'hero' => 'gold-editorial', 'header' => 'gold-ribbon',
            'sections' => 'featured-mosaic', 'footer' => 'festive-columns',
            'art_direction' => 'woven Khmer textile-inspired geometry, warm golden light',
            'light' => ['#FFF5DF', '#FFFCF4', '#48351D', '#875813', '#D9A843'],
            'dark' => ['#2B2114', '#3B2E1D', '#FFF2DA', '#F6D18A', '#D9A843'],
        ],
        'peaceful-jasmine' => [
            'day' => 'Saturday', 'name' => 'Peaceful Jasmine',
            'hero' => 'airy-floral', 'header' => 'soft-rounded',
            'sections' => 'quiet-card-grid', 'footer' => 'floral-footer',
            'art_direction' => 'white jasmine, pale sage, soft morning light',
            'light' => ['#FAF7F7', '#FFFFFF', '#3C3C37', '#52684D', '#C5A7B2'],
            'dark' => ['#262723', '#333630', '#F8F3F0', '#C5DAB5', '#DEBBC9'],
        ],
        'angkor-heritage' => [
            'day' => 'Extra', 'name' => 'Angkor Heritage',
            'hero' => 'heritage-monumental', 'header' => 'heritage-arch',
            'sections' => 'museum-editorial', 'footer' => 'heritage-columns',
            'art_direction' => 'sandstone relief-inspired geometry, historic stone, muted moss',
            'light' => ['#F3EBDF', '#FFFAF0', '#3E3025', '#735233', '#B08A54'],
            'dark' => ['#27221D', '#3A3026', '#F5E9D6', '#E1BC89', '#BC9866'],
        ],
        'phumi-spirit' => [
            'day' => 'Holiday', 'name' => 'Phumi Spirit',
            'hero' => 'village-welcome', 'header' => 'warm-community',
            'sections' => 'community-story', 'footer' => 'village-footer',
            'art_direction' => 'rice fields, rural Cambodian landscape, woven textures',
            'light' => ['#F9EEDC', '#FFF9EE', '#42352A', '#80512F', '#BD9258'],
            'dark' => ['#2B241B', '#3D3022', '#FFF2D9', '#F0C894', '#B7A06C'],
        ],
    ];

    public static function valid(mixed $skin): bool
    {
        return is_string($skin) && isset(self::CATALOG[$skin]);
    }

    /**
     * Settings contract:
     * mode: legacy | automatic | manual
     * manual_skin: a catalog key
     * weekly: optional map indexed by weekday (0 = Sunday, 6 = Saturday)
     * periods: ordered list of {start: YYYY-MM-DD, end: YYYY-MM-DD, skin: key}.
     * First matching valid period wins; dates inclusive in Cambodia local time.
     * Null means preserve the current frontend/homepage configuration.
     */
    public static function resolve(array $settings, DateTimeInterface $instant): ?string
    {
        $mode = $settings['mode'] ?? 'legacy';
        if ($mode === 'manual') {
            $skin = $settings['manual_skin'] ?? null;

            return self::valid($skin) ? $skin : null;
        }

        if ($mode !== 'automatic') {
            return null;
        }

        $local = DateTimeImmutable::createFromInterface($instant)
            ->setTimezone(new DateTimeZone(self::TIMEZONE));
        $day = $local->format('Y-m-d');
        $periods = $settings['periods'] ?? [];
        if (is_array($periods)) {
            foreach ($periods as $period) {
                if (!is_array($period)) {
                    continue;
                }
                $start = $period['start'] ?? null;
                $end = $period['end'] ?? null;
                $skin = $period['skin'] ?? null;
                if (self::date($start) && self::date($end)
                    && $start <= $end && $start <= $day && $day <= $end
                    && self::valid($skin)) {
                    return $skin;
                }
            }
        }

        $weekday = (int) $local->format('w');
        $weekly = $settings['weekly'] ?? [];
        $skin = is_array($weekly) ? ($weekly[$weekday] ?? null) : null;

        return self::valid($skin) ? $skin : self::WEEKLY[$weekday];
    }

    private static function date(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
