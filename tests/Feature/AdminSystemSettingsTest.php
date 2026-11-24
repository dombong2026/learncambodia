<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\WorkspaceMenu;
use App\Support\ManagedFiles;
use App\Support\ManagedStorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AdminSystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_sidebar_links_to_each_setting_in_order_after_analytics(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $groups = collect(WorkspaceMenu::groups('admin'));

        $this->assertSame(['reporting', 'system-settings'], $groups->pluck('key')->slice(-2)->values()->all());
        $this->assertSame(
            ['Branding', 'Security', 'Social Auth', 'Cloud Storage', 'MCP Management'],
            array_column($groups->last()['items'], 'label')
        );

        foreach (['branding', 'security', 'social-auth', 'cloud-storage', 'mcp'] as $section) {
            $url = route('admin.system-settings.'.$section);
            $response = $this->get($url)->assertOk();
            $this->assertMatchesRegularExpression(
                '~href="'.preg_quote($url, '~').'"\s+aria-current="page"~',
                $response->getContent()
            );
        }
    }

    public function test_settings_and_probe_are_restricted_to_administrators(): void
    {
        $probe = route('admin.system-settings.cloud-storage.probe');
        $this->get(route('admin.system-settings.branding'))->assertRedirect(route('login'));
        $this->post($probe)->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'student']));
        foreach (['branding', 'security', 'social-auth', 'cloud-storage', 'mcp'] as $section) {
            $this->get(route('admin.system-settings.'.$section))->assertForbidden();
        }
        $this->post($probe)->assertForbidden();
        $this->patch(route('admin.system-settings.cloud-storage.save'), [])->assertForbidden();
    }

    public function test_admin_can_save_encrypted_provider_settings_without_switching_storage(): void
    {
        config(['filesystems.managed' => 'local']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $settings = route('admin.system-settings.cloud-storage');
        $save = route('admin.system-settings.cloud-storage.save');
        $configure = route('admin.system-settings.cloud-storage.configure', ['provider' => 'r2']);
        $destination = route('admin.system-settings.cloud-storage', ['configure' => 'r2']);
        $s3Destination = route('admin.system-settings.cloud-storage', ['configure' => 's3']);
        $r2 = [
            'provider' => 'r2',
            'bucket' => 'dashboard-private-bucket',
            'endpoint' => 'https://'.str_repeat('a', 32).'.eu.r2.cloudflarestorage.com/',
            'access_key_id' => 'R2PrivateKey01',
            'secret_access_key' => 'PrivateTokenOneNotForHtml',
        ];

        $this->get($settings)->assertOk()->assertSee('Storage providers')->assertDontSee('<dialog', false);
        $this->get($destination)->assertOk()->assertDontSee('<dialog', false);
        $this->get($configure)->assertRedirect(route('password.confirm'));
        $this->patchJson($save, $r2)->assertStatus(423);

        $this->withSession(['auth.password_confirmed_at' => time()]);
        $this->get($configure)->assertRedirect($destination);
        $this->get($settings)->assertOk()->assertDontSee('<dialog', false);
        $this->get($destination)->assertSee('<dialog id="cloud-storage-configuration-dialog"', false)
            ->assertSee('Save Cloudflare R2 settings')->assertDontSee('Save Amazon S3 settings')->assertSeeText('Not set');
        $this->get($s3Destination)->assertSee('Save Amazon S3 settings')->assertDontSee('Save Cloudflare R2 settings');
        $this->patch($save, array_replace($r2, ['endpoint' => 'https://objects.example.test']))
            ->assertRedirect($destination)->assertSessionHasErrors('endpoint', null, 'r2Storage');
        $this->assertNull(session()->getOldInput('secret_access_key'));
        $this->assertDatabaseCount('managed_storage_settings', 0);

        $this->patch($save, $r2)->assertRedirect($destination)->assertSessionHas('status')
            ->assertSessionHas('cloud_storage_saved_provider', 'r2');
        $payload = DB::table('managed_storage_settings')->where('provider', 'r2')->value('payload');
        $this->assertStringNotContainsString($r2['secret_access_key'], $payload);
        $this->assertSame($r2['secret_access_key'], ManagedStorageSettings::saved('r2')['secret']);
        $this->assertSame($r2['bucket'], ManagedFiles::disk('r2')->getConfig()['bucket']);
        $this->assertSame('local', config('filesystems.managed'));

        $view = $this->get($destination)->assertOk()->assertSee('Test R2 connection')->assertSee($r2['bucket'])
            ->assertDontSee($r2['access_key_id'])->assertDontSee($r2['secret_access_key']);
        foreach (['access_key_id', 'secret_access_key'] as $field) {
            $this->assertMatchesRegularExpression(
                '/id="r2-'.$field.'-status"[^>]*>Saved<\//',
                $view->getContent()
            );
        }

        $this->patch($save, ['provider' => 's3', 'bucket' => 'dashboard-private-s3', 'region' => 'ap-southeast-1'])
            ->assertRedirect($s3Destination)->assertSessionHas('status');
        $this->assertSame('ap-southeast-1', ManagedStorageSettings::saved('s3')['region']);
        $this->get($settings)->assertOk()->assertSee('Test S3 connection');

        config(['filesystems.managed' => 'r2']);
        $this->patch($save, array_replace($r2, ['bucket' => 'replacement-bucket']))
            ->assertRedirect($destination)->assertSessionHasErrors('settings', null, 'r2Storage');
        $this->assertSame($r2['bucket'], ManagedStorageSettings::saved('r2')['bucket']);
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

        $this->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.system-settings.cloud-storage', ['configure' => 'r2']))
            ->assertOk()->assertSee('Local server')->assertSee('Settings present')->assertSeeText('Set on server')
            ->assertSee('Test R2 connection')->assertDontSee('test-access-id')->assertDontSee('test-secret-value');
        $this->post($probe)->assertRedirect($settings)
            ->assertSessionHas('status', 'R2 write, read, and delete checks passed.');

        $this->assertSame([], $r2->allFiles());
        $this->assertSame('local', config('filesystems.managed'));
    }
}
