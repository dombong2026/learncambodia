<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Support\WebsiteSkins;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WebsiteSkinsTest extends TestCase
{
    public function test_catalog_has_nine_distinct_design_contracts(): void
    {
        self::assertCount(9, WebsiteSkins::CATALOG);
        foreach (['hero', 'header', 'sections', 'footer'] as $variant) {
            self::assertCount(9, array_unique(array_column(WebsiteSkins::CATALOG, $variant)));
        }
        foreach (WebsiteSkins::CATALOG as $skin) {
            foreach (['light', 'dark'] as $mode) {
                self::assertCount(5, $skin[$mode]);
                foreach ($skin[$mode] as $color) {
                    self::assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $color);
                }
            }
        }
    }

    public function test_weekly_rotation_uses_cambodian_midnight(): void
    {
        $settings = ['mode' => 'automatic'];
        $sunday = new DateTimeImmutable('2026-11-29T00:00:00+07:00');
        foreach (WebsiteSkins::WEEKLY as $day => $skin) {
            self::assertSame($skin, WebsiteSkins::resolve($settings, $sunday->modify("+{$day} days")));
        }
        self::assertSame('natural-premium', WebsiteSkins::resolve($settings, new DateTimeImmutable('2026-11-29T16:59:59Z')));
        self::assertSame('sorya-sunrise', WebsiteSkins::resolve($settings, new DateTimeImmutable('2026-11-29T17:00:00Z')));
    }

    public function test_explicit_period_and_manual_precedence(): void
    {
        $settings = [
            'mode' => 'automatic',
            'periods' => [
                ['start' => '2026-12-01', 'end' => '2026-12-03', 'skin' => 'phumi-spirit'],
                ['start' => '2026-12-02', 'end' => '2026-12-10', 'skin' => 'angkor-heritage'],
            ],
        ];
        self::assertSame('phumi-spirit', WebsiteSkins::resolve($settings, new DateTimeImmutable('2026-12-01T00:00:00+07:00')));
        self::assertSame('phumi-spirit', WebsiteSkins::resolve($settings, new DateTimeImmutable('2026-12-03T23:59:59+07:00')));
        self::assertSame('angkor-heritage', WebsiteSkins::resolve($settings, new DateTimeImmutable('2026-12-04T00:00:00+07:00')));
        $settings['mode'] = 'manual';
        $settings['manual_skin'] = 'emerald-jungle';
        self::assertSame('emerald-jungle', WebsiteSkins::resolve($settings, new DateTimeImmutable('2026-12-02T00:00:00+07:00')));
        $settings['mode'] = 'automatic';
        self::assertSame('phumi-spirit', WebsiteSkins::resolve($settings, new DateTimeImmutable('2026-12-02T00:00:00+07:00')));
    }

    public function test_invalid_configuration_fails_safely(): void
    {
        $now = new DateTimeImmutable('2026-11-29T12:00:00+07:00');
        self::assertNull(WebsiteSkins::resolve([], $now));
        self::assertNull(WebsiteSkins::resolve(['mode' => 'manual', 'manual_skin' => '../unsafe'], $now));
        self::assertNull(WebsiteSkins::resolve(['mode' => 'unknown'], $now));
        self::assertSame('natural-premium', WebsiteSkins::resolve([
            'mode' => 'automatic',
            'weekly' => [0 => 'invalid'],
            'periods' => [['start' => '2026-02-30', 'end' => '2026-12-31', 'skin' => 'phumi-spirit']],
        ], $now));
        self::assertSame('angkor-heritage', WebsiteSkins::resolve(['mode' => 'automatic', 'weekly' => [0 => 'angkor-heritage']], $now));
    }
}
