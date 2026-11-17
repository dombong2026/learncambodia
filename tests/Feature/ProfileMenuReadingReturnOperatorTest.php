<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ProfileMenuReadingReturnOperatorTest extends TestCase
{
    public function test_operator_is_valid_bash_and_deploys_only_reviewed_role_menu_and_auth_changes(): void
    {
        $file = base_path('support/operator/learncambodia-profile-menu-reading-return-upgrade-20261114.sh');
        $this->assertFileExists($file);
        $lint = new Process(['bash', '-n', $file]);
        $lint->run();
        $this->assertTrue($lint->isSuccessful(), $lint->getErrorOutput());

        $script = file_get_contents($file);
        $this->assertIsString($script);
        foreach ([
            'OLD="9314a73173780ca43718817bce759bb12fcd86ca"',
            'NEW="a228b5e93bef878a5f31634d44c134f21860e201"',
            'TESTED="f56067ad650535a2f617dcfceafe54fb76048b58"',
            'TREE="4551f6302065fd693bc21129e7455754cacbcbdf"',
            'gh run download 43592046308',
            'PROFILE_MENU_AND_SAFE_READING_RETURN_SOURCE=PASS',
            'UPDATE LEARN CAMBODIA PROFILE MENU',
            '[[ "$count" == 9 ]]',
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
