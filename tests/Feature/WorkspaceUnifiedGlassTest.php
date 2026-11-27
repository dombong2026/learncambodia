<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkspaceUnifiedGlassTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_glass_is_loaded_on_all_role_dashboards_but_not_public_pages(): void
    {
        foreach (['admin'=>'/dashboard','student'=>'/account','instructor'=>'/instructor','developer'=>'/developer'] as $role=>$url) {
            $user = User::factory()->create();
            $user->forceFill(['role'=>$role])->save();

            $this->actingAs($user)->get($url)->assertOk()
                ->assertSee('css/workspace-unified-glass.css?v=2', false)
                ->assertSee('data-dashboard-compact-heading', false)
                ->assertSee('data-dashboard-showcase', false)
                ->assertSee('data-saver-toggle', false)
                ->assertSee('data-glass-sidebar-toggle', false);
        }

        $this->get('/')->assertOk()->assertDontSee('workspace-unified-glass.css', false);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get('/login')->assertOk()->assertDontSee('workspace-unified-glass.css', false);
    }

    public function test_glass_motion_is_scoped_and_has_accessible_static_fallbacks(): void
    {
        $css = file_get_contents(public_path('css/workspace-unified-glass.css'));

        $this->assertStringContainsString('data-dashboard-compact-heading', $css);
        $this->assertStringContainsString('lc-workspace-glass-reflection 4.4s ease-in-out 1 both', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
        $this->assertStringContainsString('[data-saver="on"]', $css);
        $this->assertStringContainsString(':not(:hover):not(:focus-within)', $css);
        $this->assertStringNotContainsString('transform:', $css);
        $this->assertStringNotContainsString('transition:', $css);
    }
}
