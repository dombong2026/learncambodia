<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class HomeFeatureThemeOperatorTest extends TestCase
{
    public function test_operator_has_valid_bash_and_exact_reviewed_release_guards(): void
    {
        $path = base_path('support/operator/learncambodia-home-feature-theme-upgrade-20261114.sh');
        $this->assertFileExists($path);
        $lint = new Process(['bash', '-n', $path]);
        $lint->run();
        $this->assertTrue($lint->isSuccessful(), $lint->getErrorOutput());

        $script = file_get_contents($path);
        $this->assertIsString($script);
        foreach ([
            'OLD="e48bab634d9a40740ba40e4d9e425901fbda8fd4"',
            'NEW="2ad52b10de8ed9b5aefa6d9bd65235977843d1d7"',
            'TESTED="f4b86c2cc902da73d38e50aa2bf608f13810d55f"',
            'TREE="bf929b46819f96da6c9e993c50a0e6ca6e3ec58e"',
            'gh run download 43601496121',
            'HOME_FEATURE_ICONS_THEME_AND_TRANSLATIONS_SOURCE=PASS',
            'UPDATE LEARN CAMBODIA HOME THEME',
            '[[ "$count" == 4 ]]',
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
