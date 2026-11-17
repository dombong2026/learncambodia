<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class CatalogFilterScrollOperatorTest extends TestCase
{
    public function test_guarded_filter_scroll_upgrade_has_valid_bash_and_exact_source_pins(): void
    {
        $file = base_path('support/operator/learncambodia-catalog-filter-scroll-upgrade-20261114.sh');
        $this->assertFileExists($file);

        $lint = new Process(['bash', '-n', $file]);
        $lint->run();
        $this->assertTrue($lint->isSuccessful(), $lint->getErrorOutput());

        $script = file_get_contents($file);
        $this->assertIsString($script);
        foreach ([
            'OLD="2ad52b10de8ed9b5aefa6d9bd65235977843d1d7"',
            'NEW="23ae0749a39c851d666d39bfe38f925ceb035fdc"',
            'TESTED="4bd80046f605e173bb8d67f33f35060ce2a497d7"',
            'TREE="f9fc47cd50473c21cb194b7f27785102b7cb4c1f"',
            'gh run download 43604489046',
            'CATALOG_FILTER_NORMAL_SCROLL_AND_FORM_SOURCE=PASS',
            'UPDATE LEARN CAMBODIA FILTER SCROLL',
            '[[ "$count" == 1 ]]',
            'PRIVATE_SOURCE_VITE_AND_DATABASE_BACKUP=PASS',
            'NO_DATABASE_MIGRATIONS=PASS',
            'OTHER_SITES=UNCHANGED',
            'BASIC_AUTH=STILL_ENABLED',
        ] as $expected) {
            $this->assertStringContainsString($expected, $script);
        }
        $this->assertStringNotContainsString('artisan migrate', $script);
        $this->assertStringNotContainsString('migrate:fresh', $script);
        $this->assertStringContainsString('resources/views/courses/index.blade.php) ;;', $script);
        $this->assertStringNotContainsString('resources/views/home.blade.php|', $script);
    }
}
