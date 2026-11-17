<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class CoursesCefrRowOperatorTest extends TestCase
{
    public function test_single_row_cefr_operator_has_valid_bash_and_reviewed_release_pins(): void
    {
        $scriptPath = base_path('support/operator/learncambodia-courses-cefr-row-upgrade-20261114.sh');
        $this->assertFileExists($scriptPath);

        $lint = new Process(['bash', '-n', $scriptPath]);
        $lint->run();
        $this->assertTrue($lint->isSuccessful(), $lint->getErrorOutput());

        $script = file_get_contents($scriptPath);
        $this->assertIsString($script);
        foreach ([
            'OLD="23ae0749a39c851d666d39bfe38f925ceb035fdc"',
            'NEW="e71580b36b693eec822c31257354a8a6feb3a445"',
            'TESTED="cfe0c1fba89221e9ab6847eb94a411ceb23cf152"',
            'TREE="ce807a7275c9db9e5364f98f701d36e407bb312a"',
            'gh run download 43608402104',
            'SINGLE_ROW_CEFR_CARDS_AND_SCROLL_CONTAINMENT_SOURCE=PASS',
            'UPDATE LEARN CAMBODIA CEFR ROW',
            '[[ "$count" == 1 ]]',
            'PRIVATE_SOURCE_VITE_AND_DATABASE_BACKUP=PASS',
            'NO_DATABASE_MIGRATIONS=PASS',
            'OTHER_SITES=UNCHANGED',
            'BASIC_AUTH=STILL_ENABLED',
        ] as $marker) {
            $this->assertStringContainsString($marker, $script);
        }
        $this->assertStringNotContainsString('artisan migrate', $script);
        $this->assertStringNotContainsString('migrate:fresh', $script);
        $this->assertStringContainsString('resources/views/courses/index.blade.php) ;;', $script);
    }
}
