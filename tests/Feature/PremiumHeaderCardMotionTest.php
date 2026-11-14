<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PremiumHeaderCardMotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_language_button_is_flag_only_with_three_valid_working_options(): void
    {
        $html = $this->get('/?lang=en')->assertOk()
            ->assertSee('aria-controls="locale-menu"', false)
            ->assertSee('premium-language-trigger', false)
            ->assertSee('data-header-control', false)
            ->assertSee('premium-header-panel', false)
            ->assertSee('data-dropdown-panel', false)
            ->assertSee('name="locale" value="en"', false)
            ->assertSee('name="locale" value="km"', false)
            ->assertSee('name="locale" value="zh"', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*aria-controls="locale-menu"[^>]*>[^<]*<span[^>]*>[^<]*<\/span>\s*<\/button>/su',
            $html,
            'Only the active flag should appear on the language trigger (no chevron).'
        );
        $this->assertStringContainsString('aria-controls="profile-menu"', $html);
    }

    public function test_shared_workspace_profile_and_language_dropdowns_for_every_role(): void
    {
        foreach (['admin' => '/dashboard', 'instructor' => '/instructor',
            'student' => '/account', 'developer' => '/developer'] as $role => $url) {
            $user = User::factory()->create();
            $user->forceFill(['role' => $role])->save();

            $this->actingAs($user)->get($url.'?lang=en')->assertOk()
                ->assertSee('aria-controls="locale-menu"', false)
                ->assertSee('premium-language-trigger', false)
                ->assertSee('aria-controls="workspace-profile"', false)
                ->assertSee('premium-header-trigger', false)
                ->assertSee('name="locale" value="km"', false)
                ->assertSee('data-theme-toggle', false);
        }
    }

    public function test_global_card_art_is_decorative_and_home_tiles_are_still_real_links(): void
    {
        $html = $this->get('/?lang=en')->assertOk()
            ->assertSee('premium-card', false)
            ->assertSee('premium-corner-icon', false)
            ->assertSee('aria-hidden="true"', false)
            ->assertSee('href="'.route('courses.index').'"', false)
            ->getContent();
        $this->assertGreaterThanOrEqual(4, substr_count($html, 'premium-corner-icon'));
        $this->assertStringNotContainsString('Browse courses →', $html);
    }

    public function test_course_and_profile_titles_are_real_links_without_trailing_arrow_icons(): void
    {
        $course = file_get_contents(resource_path('views/components/public-course-card.blade.php'));
        $person = file_get_contents(resource_path('views/components/public-person-card.blade.php'));
        $learning = file_get_contents(resource_path('views/components/learning-card.blade.php'));

        $this->assertStringContainsString('<a href="{{ route(', $course);
        $this->assertStringContainsString('{{ $course->title }}</a>', $course);
        $this->assertStringContainsString('{{ $profile->display_name }}</a>', $person);
        $this->assertStringContainsString('premium-corner-icon', $course);
        $this->assertStringContainsString('premium-corner-icon', $person);
        $this->assertStringNotContainsString('arrow-up-right', $course);
        $this->assertStringNotContainsString('arrow-up-right', $learning);
        $this->assertStringNotContainsString(' →', $learning);
    }
}
