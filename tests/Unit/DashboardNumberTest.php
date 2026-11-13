<?php

namespace Tests\Unit;

use App\Support\DashboardNumber;
use PHPUnit\Framework\TestCase;

class DashboardNumberTest extends TestCase
{
    public function test_compact_numbers_keep_human_readable_magnitudes(): void
    {
        $this->assertSame('0', DashboardNumber::compact(0));
        $this->assertSame('999', DashboardNumber::compact(999));
        $this->assertSame('1K', DashboardNumber::compact(1000));
        $this->assertSame('1.5K', DashboardNumber::compact(1500));
        $this->assertSame('10K', DashboardNumber::compact(10000));
        $this->assertSame('12K', DashboardNumber::compact(12000));
        $this->assertSame('1M', DashboardNumber::compact(1000000));
        $this->assertSame('2.5M', DashboardNumber::compact(2500000));
        $this->assertSame('10M', DashboardNumber::compact(10000000));
        $this->assertSame('1M', DashboardNumber::compact(999999));
        $this->assertSame('1B', DashboardNumber::compact(1000000000));
    }
}
