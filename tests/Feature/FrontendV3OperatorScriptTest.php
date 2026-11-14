<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class FrontendV3OperatorScriptTest extends TestCase
{
    public function test_operator_script_is_syntactically_valid_and_pins_reviewed_release(): void
    {
        $path = base_path('support/operator/learncambodia-frontend-experience-v3-upgrade-20261114.sh');
        $this->assertFileExists($path);
        $check = new Process(['bash', '-n', $path]);
        $check->run();
        $this->assertTrue($check->isSuccessful(), $check->getErrorOutput());

        $script = file_get_contents($path);
        $this->assertIsString($script);
        foreach ([
            'OLD="aed4b8a911166fe7364fc0d3ed3357636c411133"',
            'NEW="64b56d24480de5ba9c74fb45e5f2478db39e763a"',
            'TESTED="0d614c0f8323ec6612b8c3ee1e454b4c65af77be"',
            'TREE="d69e50ddff62054e00dc121642fd3e83e44aba19"',
            'gh run download 43566841922',
            'FRONTEND_V2_BASELINE_AND_V3_SOURCE=PASS',
            'UPDATE LEARN CAMBODIA FRONTEND V3',
            'PRIVATE_SOURCE_VITE_AND_DATABASE_BACKUP=PASS',
            'BASIC_AUTH=STILL_ENABLED',
            "NO_DATABASE_MIGRATIONS=PASS",
            'OTHER_SITES=UNCHANGED',
        ] as $guard) {
            $this->assertStringContainsString($guard, $script);
        }
        $this->assertStringNotContainsString('artisan migrate', $script);
        $this->assertStringNotContainsString('migrate:fresh', $script);
        $this->assertStringNotContainsString('nginx -s reload', $script);
    }
}
