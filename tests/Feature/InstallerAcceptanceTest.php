<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CefrLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstallerAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $lockPath;
    private string $mutexPath;

    protected function setUp(): void
    {
        parent::setUp();
        $directory = storage_path('framework/testing/installer-'.Str::random(18));
        $this->lockPath = $directory.'/installed';
        $this->mutexPath = $directory.'/mutex';
        config()->set('installer.lock_path',$this->lockPath);
        config()->set('installer.mutex_path',$this->mutexPath);
        config()->set('installer.enabled',false);
        config()->set('installer.token',str_repeat('s',48));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->lockPath));
        parent::tearDown();
    }

    public function test_installer_routes_are_not_public_by_default(): void
    {
        $this->get('/install')->assertNotFound();
        $this->post('/install/unlock',['setup_token'=>str_repeat('s',48)])->assertNotFound();
        $this->post('/install/complete',[
            'name'=>'Admin','email'=>'admin@example.test',
            'password'=>'StrongInstall123!','password_confirmation'=>'StrongInstall123!',
            'confirm_fresh_install'=>'1',
        ])->assertNotFound();

        $this->assertDatabaseCount('users',0);
    }

    public function test_private_token_is_required_and_never_rendered_in_form(): void
    {
        config()->set('installer.enabled',true);
        $this->get('/install')->assertOk()
            ->assertSee('Private setup token')
            ->assertDontSee(str_repeat('s',48));

        $this->post('/install/complete',[
            'name'=>'Admin','email'=>'admin@example.test',
            'password'=>'StrongInstall123!','password_confirmation'=>'StrongInstall123!',
            'confirm_fresh_install'=>'1',
        ])->assertForbidden();

        $this->post('/install/unlock',['setup_token'=>'invalid'])
            ->assertSessionHasErrors('setup_token');
        $this->assertDatabaseCount('users',0);

        $this->post('/install/unlock',['setup_token'=>str_repeat('s',48)])
            ->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee('Create the first administrator')
            ->assertDontSee(str_repeat('s',48));
    }

    public function test_one_click_finish_seeds_reference_data_creates_admin_and_locks_installer(): void
    {
        config()->set('installer.enabled',true);
        $this->post('/install/unlock',['setup_token'=>str_repeat('s',48)])
            ->assertRedirect('/install');

        $this->post('/install/complete',[
            'name'=>'School Director',
            'email'=>'director@learncambodia.test',
            'password'=>'StrongInstall123!',
            'password_confirmation'=>'StrongInstall123!',
            'confirm_fresh_install'=>'1',
        ])->assertRedirect('/dashboard');

        $admin=User::query()->firstOrFail();
        $this->assertSame('admin',$admin->role);
        $this->assertTrue(Hash::check('StrongInstall123!',$admin->password));
        $this->assertDatabaseCount('users',1);
        $this->assertTrue(File::exists($this->lockPath));
        $this->assertGreaterThanOrEqual(20,Category::count());
        $this->assertSame(6,CefrLevel::count());
        $this->assertAuthenticatedAs($admin);

        $this->get('/install')->assertNotFound();
        $this->post('/install/complete',[
            'name'=>'Attack','email'=>'attack@example.test',
            'password'=>'StrongInstall123!','password_confirmation'=>'StrongInstall123!',
            'confirm_fresh_install'=>'1',
        ])->assertNotFound();
        $this->assertDatabaseCount('users',1);
    }

    public function test_existing_school_account_blocks_reinstallation_even_without_lock_file(): void
    {
        config()->set('installer.enabled',true);
        User::factory()->create(['email'=>'existing@example.test']);

        $this->get('/install')->assertNotFound();
        $this->post('/install/unlock',['setup_token'=>str_repeat('s',48)])
            ->assertNotFound();
        $this->assertDatabaseCount('users',1);
    }

    public function test_installer_rejects_missing_confirmation_and_weak_administrator_password(): void
    {
        config()->set('installer.enabled',true);
        $this->post('/install/unlock',['setup_token'=>str_repeat('s',48)])
            ->assertRedirect('/install');

        $this->post('/install/complete',[
            'name'=>'Admin','email'=>'admin@example.test',
            'password'=>'weak','password_confirmation'=>'weak',
        ])->assertSessionHasErrors(['password','confirm_fresh_install']);
        $this->assertDatabaseCount('users',0);
    }

    public function test_installer_without_strong_server_secret_remains_closed(): void
    {
        config()->set('installer.enabled',true);
        config()->set('installer.token','short');
        $this->get('/install')->assertNotFound();
    }
}
