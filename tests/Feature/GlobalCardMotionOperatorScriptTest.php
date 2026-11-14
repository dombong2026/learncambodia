<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class GlobalCardMotionOperatorScriptTest extends TestCase
{
    public function test_guarded_card_motion_operator_is_syntactically_valid_and_review_pinned(): void
    {
        $path = base_path('support/operator/learncambodia-global-premium-card-motion-upgrade-20261114.sh');
        $this->assertFileExists($path);
        $syntax = new Process(['bash', '-n', $path]);
        $syntax->run();
        $this->assertTrue($syntax->isSuccessful(), $syntax->getErrorOutput());

        $source = file_get_contents($path);
        $this->assertIsString($source);
        foreach ([
            'OLD="b22239dca4df29499ad2c61fa2856d17033d7b09"',
            'NEW="19b0b0ab2e8f87e73c8b628829087f938dc9bcf0"',
            'TESTED="10b273878e0ab79c776c18f1010e9b8ac696289d"',
            'TREE="71290a8a6581edc82a13d7249fdbcee280ec7e74"',
            'gh run download 43571660817',
            'FRONTEND_V3_BASELINE_AND_GLOBAL_CARD_MOTION_SOURCE=PASS',
            'UPDATE LEARN CAMBODIA CARD MOTION',
            'PRIVATE_SOURCE_VITE_AND_DATABASE_BACKUP=PASS',
            'NO_DATABASE_MIGRATIONS=PASS',
            'BASIC_AUTH=STILL_ENABLED',
            'OTHER_SITES=UNCHANGED',
            '[[ "$count" == 9 ]]',
        ] as $required) {
            $this->assertStringContainsString($required, $source);
        }

        $this->assertStringNotContainsString('artisan migrate', $source);
        $this->assertStringNotContainsString('migrate:fresh', $source);
    }
}
