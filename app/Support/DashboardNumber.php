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
                // Avoid 999,999 rendering as "1,000K".
                if (abs(round($scaled)) >= 1000 && $symbol !== 'B') {
                    return self::compact($number > 0
                        ? $threshold * 1000 : -$threshold * 1000);
                }
                if (abs($scaled) < 10) {
                    return rtrim(rtrim(number_format($scaled, 1, '.', ''), '0'), '.').$symbol;
                }

                return number_format($scaled, 0, '.', '').$symbol;
            }
        }

        return number_format($number, 0);
    }
}
