<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use RuntimeException;

final class PublicId
{
    public static function allocate(Connection $connection, int $digits): int
    {
        $minimum = 10 ** ($digits - 1);
        $maximum = 10 ** $digits - 1;

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $candidate = random_int($minimum, $maximum);
            if ($connection->table('public_record_ids')->insertOrIgnore(['id' => $candidate]) === 1) {
                return $candidate;
            }
        }

        throw new RuntimeException('Could not reserve a public ID.');
    }

    public static function path(int $id, string $title): string
    {
        return $id.'-'.(rtrim(substr(Str::slug($title), 0, 100), '-') ?: 'details');
    }

    public static function fromPath(mixed $value, int $digits = 6): ?string
    {
        return preg_match('/^([1-9][0-9]{'.($digits - 1).'})-[a-z0-9][a-z0-9-]*$/D', (string) $value, $matches)
            ? $matches[1] : null;
    }

    public static function lookup($query, string $value)
    {
        $id = self::fromPath($value);
        if ($id !== null && ($record = (clone $query)->where('public_id', $id)->first())) {
            return $record;
        }

        return $query->where('slug', $value)->firstOrFail();
    }

    public static function availableSlug($query, string $value, int $limit): string
    {
        if (!(clone $query)->where('slug', $value)->exists()) {
            return $value;
        }

        return rtrim(substr($value, 0, $limit - 21), '-').'-'.strtolower((string) Str::random(20));
    }
}
