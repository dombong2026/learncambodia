<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class CenteredFocusOperatorScriptTest extends TestCase
{
    public function test_guarded_operator_has_valid_bash_and_pinned_no_migration_release(): void
    {
        $file = base_path('support/operator/learncambodia-centered-focus-cards-upgrade-20261114.sh');
        $this->assertFileExists($file);
        $syntax = new Process(['bash', '-n', $file]);
        $syntax->run();
        $this->assertTrue($syntax->isSuccessful(), $syntax->getErrorOutput());

        $source = file_get_contents($file);
        $this->assertIsString($source);
        foreach ([
            'OLD="64b56d24480de5ba9c74fb45e5f2478db39e763a"',
            'NEW="b22239dca4df29499ad2c61fa2856d17033d7b09"',
            'TESTED="c401ddbf32206b6f10bdc992e5bbb7646bd1bf70"',
            'TREE="aebf9e88def0146f5def1910007bc40034c229d5"',
            'gh run download 43569109686',
            'FRONTEND_V3_BASELINE_AND_CENTERED_FOCUS_SOURCE=PASS',
            'UPDATE LEARN CAMBODIA FOCUS CARDS',
            'PRIVATE_SOURCE_VITE_AND_DATABASE_BACKUP=PASS',
            'BASIC_AUTH=STILL_ENABLED',
            'OTHER_SITES=UNCHANGED',
        ] as $check) {
            $this->assertStringContainsString($check, $source);
        }

        $this->assertStringNotContainsString('artisan migrate', $source);
        $this->assertStringNotContainsString('migrate:fresh', $source);
    }
}
