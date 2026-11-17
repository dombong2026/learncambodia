<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class CompactProfileDropdownOperatorTest extends TestCase
{
    public function test_reviewed_aa_panel_operator_is_valid_bash_with_exact_safety_pins(): void
    {
        $path = base_path('support/operator/learncambodia-compact-profile-dropdown-upgrade-20261114.sh');
        $this->assertFileExists($path);
        $lint = new Process(['bash', '-n', $path]);
        $lint->run();
        $this->assertTrue($lint->isSuccessful(), $lint->getErrorOutput());

        $script = file_get_contents($path);
        $this->assertIsString($script);
        foreach ([
            'OLD="a228b5e93bef878a5f31634d44c134f21860e201"',
            'NEW="aebc256b8fa36d221fc78205afc1de70f30d9823"',
            'TESTED="de2e6284d2cab50bed1e50ab45235dd30d10ada4"',
            'TREE="0aabaed8f19f6a98d7b9c16de3a1e0e1768221a5"',
            'gh run download 43594694068',
            'COMPACT_PROFILE_DIVIDERS_AND_WIDTHS_SOURCE=PASS',
            'UPDATE LEARN CAMBODIA COMPACT PROFILE',
            '[[ "$count" == 4 ]]',
            'PRIVATE_SOURCE_VITE_AND_DATABASE_BACKUP=PASS',
            'NO_DATABASE_MIGRATIONS=PASS',
            'OTHER_SITES=UNCHANGED',
            'BASIC_AUTH=STILL_ENABLED',
        ] as $expected) {
            $this->assertStringContainsString($expected, $script);
        }
        $this->assertStringNotContainsString('artisan migrate', $script);
        $this->assertStringNotContainsString('migrate:fresh', $script);
    }
}
