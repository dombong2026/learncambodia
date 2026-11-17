<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class CompactCategoryNavOperatorTest extends TestCase
{
    public function test_guarded_category_nav_operator_has_valid_bash_and_reviewed_release_pins(): void
    {
        $path = base_path('support/operator/learncambodia-compact-category-nav-upgrade-20261114.sh');
        $this->assertFileExists($path);
        $lint = new Process(['bash', '-n', $path]);
        $lint->run();
        $this->assertTrue($lint->isSuccessful(), $lint->getErrorOutput());

        $script = file_get_contents($path);
        $this->assertIsString($script);
        foreach ([
            'OLD="aebc256b8fa36d221fc78205afc1de70f30d9823"',
            'NEW="e48bab634d9a40740ba40e4d9e425901fbda8fd4"',
            'TESTED="737fa539473af4ff794a111fe7fb9254f3627897"',
            'TREE="a7167745eab8060e3a4e890aaf0609cdb0075c76"',
            'gh run download 43598174399',
            'COMPACT_CATEGORY_SHORT_LABELS_AND_FULL_NAMES_SOURCE=PASS',
            'UPDATE LEARN CAMBODIA CATEGORY NAV',
            '[[ "$count" == 1 ]]',
            'PRIVATE_SOURCE_VITE_AND_DATABASE_BACKUP=PASS',
            'NO_DATABASE_MIGRATIONS=PASS',
            'OTHER_SITES=UNCHANGED',
            'BASIC_AUTH=STILL_ENABLED',
        ] as $required) {
            $this->assertStringContainsString($required, $script);
        }
        $this->assertStringNotContainsString('artisan migrate', $script);
        $this->assertStringNotContainsString('migrate:fresh', $script);
    }
}
