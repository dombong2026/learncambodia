<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class HomeFooterOperatorScriptTest extends TestCase
{
    public function test_home_footer_operator_has_valid_bash_and_exact_approved_release_guards(): void
    {
        $path = base_path('support/operator/learncambodia-home-footer-responsive-upgrade-20261114.sh');
        $this->assertFileExists($path);
        $lint = new Process(['bash', '-n', $path]);
        $lint->run();
        $this->assertTrue($lint->isSuccessful(), $lint->getErrorOutput());

        $script = file_get_contents($path);
        $this->assertIsString($script);
        foreach ([
            'OLD="19b0b0ab2e8f87e73c8b628829087f938dc9bcf0"',
            'NEW="ac913068ef69d1ce9a480b91d7a3011d87f3f423"',
            'TESTED="a8c370ecb54122589c6b69017717a2af1b240e86"',
            'TREE="bc80f66a625ffe1e68355cf509d3a43cd5dcf3f1"',
            'gh run download 43582752330',
            'PREVIOUS_CARD_MOTION_AND_HOME_FOOTER_SOURCE=PASS',
            'UPDATE LEARN CAMBODIA HOME FOOTER',
            '[[ "$count" == 2 ]]',
            'PRIVATE_SOURCE_VITE_AND_DATABASE_BACKUP=PASS',
            'NO_DATABASE_MIGRATIONS=PASS',
            'OTHER_SITES=UNCHANGED',
            'BASIC_AUTH=STILL_ENABLED',
        ] as $marker) {
            $this->assertStringContainsString($marker, $script);
        }

        $this->assertStringNotContainsString('artisan migrate', $script);
        $this->assertStringNotContainsString('migrate:fresh', $script);
    }
}
