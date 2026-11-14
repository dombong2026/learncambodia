<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
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

    public function test_approved_binary_brand_assets_exist_with_exact_provenance(): void
    {
        // This must fail until the operator has uploaded the approved ZIP to
        // the review branch. It prevents a green release with broken image URLs.
        $approved = [
            'logo-dark.png' => '5ca0b912923decf9975e77cb36b437ea9b93ebeb883b8c752cb04159df5d2d80',
            'logo-light.png' => '343b91b564a3a8d8bb6cc8980f5806f67d96295d8feac42f1c6c039b2a51e26c',
            'favicon.ico' => '488ebc01d36686d48e1c932dc6a1f91b566841a218e8c0e7217a19ac48c22b51',
            'favicon-16.png' => '5d53bd7d88052b1eae3d1a9f85b2a67fb2b0959176477bbcc3cd5f468f551eaf',
            'favicon-32.png' => '0825dabb175a9b5aca159ffe23d2c4734d15af87f34ad751952455f182280aa5',
            'apple-touch-icon.png' => 'af432a59faa8976fcd6bc691293274bacc22a9f0fbd73b0bf46a20e46d7ae8f5',
            'android-chrome-192x192.png' => '24272fb41f73efa52abf5e18b0d7ca3151aa875f60f9db4c247006bb4fbd87fa',
            'android-chrome-512x512.png' => '16f778b450fb2a19beb6131365fd682039b3876270a2a480a266ad8356183ce8',
            'maskable-192x192.png' => 'd8c08a62d889138fe0d55439e9b583f39c1b4ebc0d8a113ee8dacc7cda4848fe',
            'maskable-512x512.png' => 'bc329d0eb87c0cb8d992f6eac55a2924f49340a8b6d538b0374185287beac9b6',
            'site.webmanifest' => '68608f41ba7d0ee4e26b8b7f1603d9bd5ea50eb8a869267ff1885adbb547635c',
        ];

        foreach ($approved as $filename => $sha256) {
            $path = public_path('brand/'.$filename);
            $this->assertFileExists($path, 'Missing approved brand asset: '.$filename);
            $this->assertSame($sha256, hash_file('sha256', $path), $filename);
        }

        $this->assertSame(
            hash_file('sha256', public_path('brand/favicon.ico')),
            hash_file('sha256', public_path('favicon.ico')),
        );
        $this->assertSame(
            hash_file('sha256', public_path('brand/apple-touch-icon.png')),
            hash_file('sha256', public_path('apple-touch-icon.png')),
        );
        $manifest = json_decode(file_get_contents(public_path('brand/site.webmanifest')), true);
        $this->assertSame('LearnCambodia', $manifest['name']);
        $this->assertSame(['any', 'maskable'], array_values(array_unique(array_column($manifest['icons'], 'purpose'))));
    }

    public function test_approved_logo_component_uses_appropriate_light_and_dark_images(): void
    {
        $html = Blade::render('<x-brand-logo surface="adaptive" height="h-9" />');

        $this->assertStringContainsString('brand/logo-light.png', $html);
        $this->assertStringContainsString('brand/logo-dark.png', $html);
        $this->assertStringContainsString('dark:hidden', $html);
        $this->assertStringContainsString('dark:block', $html);
    }
}
