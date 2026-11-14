<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProfileThemeToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_profile_has_a_shared_accessible_theme_switch(): void
    {
        $this->get('/?lang=en')->assertOk()
            ->assertSee('id="profile-menu"', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('role="switch"', false)
            ->assertSee('aria-label="Dark mode"', false)
            ->assertSee('learncambodia.theme.v1', false)
            ->assertSee('brand/logo-light.png', false)
            ->assertSee('brand/logo-dark.png', false);
    }

    public function test_authenticated_admin_has_the_same_switch_in_workspace_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee('id="workspace-profile"', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('role="switch"', false)
            ->assertSee('aria-label="Dark mode"', false)
            ->assertSee('learncambodia.theme.v1', false);
    }

    public function test_auth_pages_use_saved_theme_without_reintroducing_language_selector(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $this->get($path.'?lang=en')->assertOk()
                ->assertSee('learncambodia.theme.v1', false)
                ->assertDontSee('data-theme-toggle', false)
                ->assertDontSee('name="locale"', false);
        }
    }

    public function test_theme_switch_label_is_localized(): void
    {
        $this->get('/?lang=km')->assertOk()->assertSee('aria-label="មុខងារពណ៌ងងឹត"', false);
        $this->get('/?lang=zh')->assertOk()->assertSee('aria-label="深色模式"', false);
    }
}
