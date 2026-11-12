<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    public function test_guests_are_redirected_from_all_three_workspaces(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/account')->assertRedirect('/login');
        $this->get('/developer')->assertRedirect('/login');
    }

    public function test_students_can_only_access_student_workspace(): void
    {
        $this->actingAs($this->user('student'));
        $this->get('/account')->assertOk()->assertSee('My Learning Overview');
        $this->get('/dashboard')->assertForbidden();
        $this->get('/developer')->assertForbidden();
        $this->get('/dashboard/users')->assertForbidden();
        $this->get('/developer/tokens')->assertForbidden();
    }

    public function test_administrators_cannot_open_other_roles_workspaces(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get('/dashboard')->assertOk()->assertSee('Administration Overview');
        $this->get('/account')->assertForbidden();
        $this->get('/developer')->assertForbidden();
    }

    public function test_developers_cannot_open_admin_or_student_workspace(): void
    {
        $this->actingAs($this->user('developer'));
        $this->get('/developer')->assertOk()->assertSee('Developer Overview');
        $this->get('/account')->assertForbidden();
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_admin_section_registry_and_student_section_registry_are_isolated(): void
    {
        $this->actingAs($this->user('admin'));
        $this->get('/dashboard/manage/appearance/typography')->assertOk()->assertSee('Not yet activated');
        $this->get('/dashboard/manage/advertisement/google-ads')->assertOk();
        $this->get('/dashboard/manage/fiction/fake')->assertNotFound();
    }

    public function test_public_header_resolves_role_appropriate_workspace_link(): void
    {
        foreach ([
            'student' => '/account',
            'admin' => '/dashboard',
            'developer' => '/developer',
        ] as $role => $target) {
            $user = $this->user($role);
            $this->actingAs($user)->get('/')
                ->assertOk()->assertSee('href="'.url($target).'"', false);
        }
    }

    public function test_public_registration_cannot_set_admin_role(): void
    {
        $this->post('/register', [
            'name' => 'Test Attacker',
            'email' => 'attacker@example.test',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
            'terms' => '1',
            'role' => 'admin',
        ])->assertRedirect('/account');

        $this->assertDatabaseHas('users', [
            'email' => 'attacker@example.test',
            'role' => 'student',
        ]);
    }
}
