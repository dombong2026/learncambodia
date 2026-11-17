<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\WorkspaceMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AdminSystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_links_to_each_setting_in_order_after_analytics(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $groups = collect(WorkspaceMenu::groups('admin'));

        $this->assertSame(['reporting', 'system-settings'], $groups->pluck('key')->slice(-2)->values()->all());
        $this->assertSame(
            ['Branding', 'Security', 'Social Auth', 'Cloud Storage'],
            array_column($groups->last()['items'], 'label')
        );

        foreach (['branding', 'security', 'social-auth', 'cloud-storage'] as $section) {
            $url = route('admin.system-settings.'.$section);
            $this->get($url)->assertOk()->assertSee('href="'.$url.'" aria-current="page"', false);
        }
    }

    public function test_settings_and_probe_are_restricted_to_administrators(): void
    {
        $probe = route('admin.system-settings.cloud-storage.probe');
        $this->get(route('admin.system-settings.branding'))->assertRedirect(route('login'));
        $this->post($probe)->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'student']));
        foreach (['branding', 'security', 'social-auth', 'cloud-storage'] as $section) {
            $this->get(route('admin.system-settings.'.$section))->assertForbidden();
        }
        $this->post($probe)->assertForbidden();
    }

    public function test_cloud_storage_hides_secrets_and_uses_the_existing_probe(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $settings = route('admin.system-settings.cloud-storage');
        $probe = route('admin.system-settings.cloud-storage.probe');
        config(['filesystems.disks.r2.key' => null]);

        $this->get($settings)->assertOk()->assertSee('Settings missing')->assertDontSee('Test R2 connection');
        $this->post($probe)->assertRedirect($settings)->assertSessionHasErrors('probe');

        $r2 = Storage::fake('r2');
        config([
            'filesystems.managed' => 'local',
            'filesystems.disks.r2.driver' => 'local',
            'filesystems.disks.r2.bucket' => 'test-bucket',
            'filesystems.disks.r2.endpoint' => 'https://test.r2.cloudflarestorage.com',
            'filesystems.disks.r2.key' => 'test-access-id',
            'filesystems.disks.r2.secret' => 'test-secret-value',
        ]);

        $this->get($settings)->assertOk()->assertSee('Local server')->assertSee('Settings present')
            ->assertSee('Test R2 connection')->assertDontSee('test-access-id')->assertDontSee('test-secret-value');
        $this->post($probe)->assertRedirect($settings)
            ->assertSessionHas('status', 'R2 write, read, and delete checks passed.');

        $this->assertSame([], $r2->allFiles());
        $this->assertSame('local', config('filesystems.managed'));
    }
}
