<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class UnboxedSocialFooterOperatorTest extends TestCase
{
    public function test_operator_is_valid_bash_and_only_deploys_a_single_reviewed_footer_template(): void
    {
        $path = base_path('support/operator/learncambodia-unboxed-footer-social-upgrade-20261114.sh');
        $this->assertFileExists($path);

        $lint = new Process(['bash', '-n', $path]);
        $lint->run();
        $this->assertTrue($lint->isSuccessful(), $lint->getErrorOutput());

        $script = file_get_contents($path);
        $this->assertIsString($script);
        foreach ([
            'OLD="ac913068ef69d1ce9a480b91d7a3011d87f3f423"',
            'NEW="9314a73173780ca43718817bce759bb12fcd86ca"',
            'TESTED="3d4a046d1bc1b4b8f1c528e62700749c461c227f"',
            'TREE="9e6f1ebdf1c75e0af0ce32f38a7be626824649e9"',
            'gh run download 43584301319',
            'PREVIOUS_HOME_FOOTER_AND_UNBOXED_SOCIAL_SOURCE=PASS',
            'UPDATE LEARN CAMBODIA SOCIAL ICONS',
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
