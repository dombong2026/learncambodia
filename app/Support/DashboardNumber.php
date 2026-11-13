<?php

namespace App\Support;

final class DashboardNumber
{
    public static function compact(int|float $number): string
    {
        $absolute = abs($number);
        if ($absolute < 1000) return number_format($number, 0);

        foreach ([1_000_000_000 => 'B', 1_000_000 => 'M', 1_000 => 'K'] as $threshold => $symbol) {
            if ($absolute >= $threshold) {
                $scaled = $number / $threshold;
                return rtrim(rtrim(number_format($scaled, abs($scaled) < 10 ? 1 : 0), '0'), '.').$symbol;
            }
        }

        return number_format($number);
    }
}
