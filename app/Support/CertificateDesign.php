<?php

namespace App\Support;

final class CertificateDesign
{
    public const FIELDS = [
        'type' => ['label' => 'Certificate type', 'x' => 50, 'y' => 17, 'size' => 1.7],
        'heading' => ['label' => 'Heading', 'x' => 50, 'y' => 29, 'size' => 5.5],
        'learner' => ['label' => 'Learner name', 'x' => 50, 'y' => 44, 'size' => 4.4],
        'body' => ['label' => 'Certificate text', 'x' => 50, 'y' => 59, 'size' => 2.3],
        'issuer' => ['label' => 'Issuer', 'x' => 50, 'y' => 76, 'size' => 2.6],
        'date' => ['label' => 'Issue date', 'x' => 30, 'y' => 87, 'size' => 1.5],
        'code' => ['label' => 'Certificate ID', 'x' => 70, 'y' => 87, 'size' => 1.2],
        'qr' => ['label' => 'Verification QR code', 'x' => 84, 'y' => 75, 'size' => 11],
    ];

    public static function options(mixed $design): array
    {
        $options = [
            'font' => in_array(data_get($design, 'font'), ['sans', 'serif'], true) ? data_get($design, 'font') : 'serif',
            'ink' => self::color(data_get($design, 'ink'), '#162643'),
            'frame' => filter_var(data_get($design, 'frame', true), FILTER_VALIDATE_BOOL),
        ];

        foreach (self::FIELDS as $key => $field) {
            foreach (self::limits($key) as $axis => [$min, $max]) {
                $options['fields'][$key][$axis] = self::within(data_get($design, "fields.$key.$axis"), $min, $max, $field[$axis]);
            }
        }

        return $options;
    }

    public static function limits(string $field): array
    {
        return $field === 'qr'
            ? ['x' => [12, 88], 'y' => [14, 86], 'size' => [10, 16]]
            : ['x' => [8, 92], 'y' => [8, 92], 'size' => [0.8, 8]];
    }

    public static function color(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[a-f\d]{6}$/iD', $value) ? $value : $fallback;
    }

    private static function within(mixed $value, float $minimum, float $maximum, float $default): float
    {
        return is_numeric($value) ? min($maximum, max($minimum, (float) $value)) : $default;
    }
}
