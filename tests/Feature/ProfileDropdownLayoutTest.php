<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProfileDropdownLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_has_one_visible_divider_with_compact_menu_on_public_header(): void
    {
        $html = $this->get('/?lang=en')->assertOk()
            ->assertSee('data-profile-divider', false)
            ->assertSee('data-profile-sign-in-icon', false)
            ->assertSee('data-profile-register-icon', false)
            ->assertSee('data-theme-toggle', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-profile-divider'));
        $this->assertStringContainsString('w-[min(16.5rem,calc(100vw-1.5rem))]', $html);
        $this->assertStringNotContainsString('w-[min(22rem,calc(100vw-2rem))]', $html);
    }

    public function test_authenticated_roles_have_two_dividers_and_narrow_menus_in_both_headers(): void
    {
        foreach ([
            'admin' => '/dashboard',
            'instructor' => '/instructor',
            'student' => '/account',
            'developer' => '/developer',
        ] as $role => $workspace) {
            $user = User::factory()->create();
            $user->forceFill(['role' => $role])->save();

            foreach (['/?lang=en', $workspace.'?lang=en'] as $url) {
                $html = $this->actingAs($user)->get($url)
                    ->assertOk()
                    ->assertSee('data-profile-role-menu="'.$role.'"', false)
                    ->assertSee('data-profile-link-icon', false)
                    ->assertSee('data-theme-toggle', false)
                    ->getContent();

                $this->assertSame(2, substr_count($html, 'data-profile-divider'));
                $this->assertStringContainsString('w-[min(18rem,calc(100vw-1.5rem))]', $html);
                $this->assertStringNotContainsString('w-[min(22rem,calc(100vw-2rem))]', $html);
            }
        }
    }

    public function test_shared_theme_control_has_no_extra_divider_inside_profile_dropdown(): void
    {
        $profile = file_get_contents(resource_path('views/partials/profile-menu-content.blade.php'));
        $theme = file_get_contents(resource_path('views/partials/theme-toggle.blade.php'));
        $this->assertIsString($profile);
        $this->assertIsString($theme);
        $this->assertStringContainsString("@include('partials.theme-toggle', ['showDivider' => false])", $profile);
        $this->assertStringContainsString("($showDivider ?? true)", $theme);
        $this->assertStringNotContainsString('data-profile-nav-group="{{ $group[\'key\'] }}" class="mt-2 border-t', $profile);
    }
}
