<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_assignment_requires_explicit_operator_approval(): void
    {
        $user = User::factory()->create(['email' => 'student@example.test']);

        $this->artisan('accounts:assign-role', [
            'email' => $user->email,
            'role' => 'admin',
            '--operator' => 'QA Operator',
            '--reason' => 'Approved audit trail test',
        ])->assertExitCode(1);

        $this->assertSame('student', $user->fresh()->role);
        $this->assertDatabaseCount('account_role_changes', 0);
    }

    public function test_role_assignment_is_audited_and_revokes_prior_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'developer@example.test',
            'password' => Hash::make('SecretPassword123!'),
        ]);
        $user->forceFill(['role' => 'developer'])->save();
        $token = $user->createToken('QA token', ['catalog:read'], now()->addDay());

        $this->artisan('accounts:assign-role', [
            'email' => $user->email,
            'role' => 'student',
            '--operator' => 'Approved Operator',
            '--reason' => 'End of approved developer assignment',
            '--confirm' => true,
        ])->assertExitCode(0);

        $this->assertSame('student', $user->fresh()->role);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('account_role_changes', [
            'user_id' => $user->id,
            'previous_role' => 'developer',
            'new_role' => 'student',
            'operator_name' => 'Approved Operator',
        ]);
    }

    public function test_authenticated_account_cannot_modify_its_own_role_through_profile(): void
    {
        $user = User::factory()->create();

        $this->assertSame('student', $user->role);

        $this->actingAs($user)->patch('/account/profile', [
            'name' => 'Changed Name',
            'role' => 'admin',
        ])->assertRedirect();

        $this->assertSame('student', $user->fresh()->role);
        $this->assertSame('Changed Name', $user->fresh()->name);
    }

    public function test_developer_password_change_revokes_api_tokens(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);
        $user->forceFill(['role' => 'developer'])->save();
        $user->createToken('Read token', ['catalog:read'], now()->addDay());

        $this->actingAs($user)->patch('/developer/profile/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_developer_language_switch_stays_in_own_workspace(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'developer'])->save();

        $this->actingAs($user)->post('/language', ['locale' => 'km'])
            ->assertRedirect('/developer');

        $this->assertSame('km', session('locale'));
    }
}
