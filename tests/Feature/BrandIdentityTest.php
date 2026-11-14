<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_layout_exposes_approved_logo_variants_and_device_icons(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('brand/logo-light.png', false)
            ->assertSee('brand/logo-dark.png', false)
            ->assertSee('brand/favicon.ico', false)
            ->assertSee('brand/favicon-16.png', false)
            ->assertSee('brand/favicon-32.png', false)
            ->assertSee('brand/apple-touch-icon.png', false)
            ->assertSee('brand/site.webmanifest', false)
            ->assertSee('brand/mstile-144x144.png', false);
    }

    public function test_login_register_and_password_recovery_use_the_same_dark_surface_logo(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/reset-password/example-token'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('brand/logo-dark.png', false)
                ->assertSee('brand/favicon.ico', false)
                ->assertSee('brand/apple-touch-icon.png', false);
        }
    }

    public function test_workspaces_use_the_correct_sidebar_logo_and_global_favicon(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee('brand/logo-dark.png', false)
            ->assertSee('brand/favicon.ico', false)
            ->assertSee('brand/site.webmanifest', false);
    }

    public function test_approved_logo_component_uses_appropriate_light_and_dark_images(): void
    {
        $html = view('components.brand-logo', [
            'surface' => 'adaptive',
            'height' => 'h-9',
        ])->render();

        $this->assertStringContainsString('brand/logo-light.png', $html);
        $this->assertStringContainsString('brand/logo-dark.png', $html);
        $this->assertStringContainsString('dark:hidden', $html);
        $this->assertStringContainsString('dark:block', $html);
    }
}
