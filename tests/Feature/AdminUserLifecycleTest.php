<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccountPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminUserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Test password for Admin 47';

    public function test_user_manager_lists_row_actions_for_an_active_student(): void
    {
        $admin = $this->administrator();
        $student = User::factory()->create(['name' => 'Learning Test Account']);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('Actions for Learning Test Account')
            ->assertSee(route('admin.users.access.edit', $student))
            ->assertSee('Deactivate')
            ->assertSee('Sign In As')
            ->assertSee('Verify')
            ->assertSee('Delete');
    }

    public function test_admin_can_record_confirmed_contacts_and_edit_requires_reverification(): void
    {
        $admin = $this->administrator();
        $details = [
            'name' => 'New Learner',
            'email' => 'new-learner@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => User::ROLE_STUDENT,
            'verify_email' => '1',
            'verify_phone' => '1',
        ];

        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()
            ->assertSee('Mark email verified')->assertSee('Mark phone verified');
        $this->post(route('admin.users.store'), $details)->assertSessionHasErrors('phone');
        $this->post(route('admin.users.store'), $details + ['phone' => '+855 12 345 678'])->assertRedirect();

        $student = User::where('email', 'new-learner@example.com')->firstOrFail();
        $this->assertSame('+85512345678', $student->phone);
        $this->assertTrue($student->hasVerifiedEmail());
        $this->assertNotNull($student->phone_verified_at);
        foreach (['email_verified', 'phone_verified'] as $action) {
            $this->assertDatabaseHas('user_account_events', [
                'user_id' => $student->id, 'actor_user_id' => $admin->id, 'action' => $action,
            ]);
        }
        $this->get(route('admin.users.access.edit', $student))->assertOk()->assertSee('Edit User');
        $this->patch(route('admin.users.access.update', $student), [
            'name' => 'Updated Learner',
            'email' => 'updated-learner@example.com',
            'phone' => '+85512345679',
            'role' => User::ROLE_STUDENT,
        ])->assertRedirect(route('admin.users.show', $student));
        $student->refresh();
        $this->assertSame('Updated Learner', $student->name);
        $this->assertFalse($student->hasVerifiedEmail());
        $this->assertNull($student->phone_verified_at);

        $this->get(route('admin.users.actions', [$student, 'verify']))->assertOk();
        $this->patch(route('admin.users.verify', $student), ['channels' => ['email', 'phone']])
            ->assertRedirect(route('admin.users.show', $student));
        $student->refresh();
        $this->assertTrue($student->hasVerifiedEmail());
        $this->assertNotNull($student->phone_verified_at);
    }

    public function test_deactivation_revokes_existing_sessions_and_delete_can_be_undone(): void
    {
        $admin = $this->administrator();
        $student = User::factory()->create(['name' => 'Lifecycle Test Student']);
        $student->createToken('test-token');

        $this->actingAs($admin)->patch(route('admin.users.status', $student), ['status' => 'deactivated'])
            ->assertRedirect(route('admin.users.index'));
        $student->refresh();
        $this->assertFalse($student->isActiveAccount());
        $this->assertSame(1, $student->account_auth_version);
        $this->assertSame(0, $student->tokens()->count());
        $this->actingAs($student)->get(route('account.overview'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($admin)->patch(route('admin.users.status', $student), ['status' => 'active'])
            ->assertRedirect(route('admin.users.index'));
        $this->actingAs($student->fresh())->withSession(['account_auth_version' => 0])
            ->get(route('account.overview'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $student), ['current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->delete(route('admin.users.destroy', $student), ['current_password' => self::PASSWORD])
            ->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'deleted']);
        $this->get(route('admin.users.index'))->assertOk()->assertDontSee('Lifecycle Test Student');
        $this->get(route('admin.users.index', ['status' => 'deleted']))->assertOk()->assertSee('Lifecycle Test Student');
        $this->patch(route('admin.users.status', $student), ['status' => 'restore'])
            ->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $student->id, 'account_status' => 'deactivated']);
    }

    public function test_sign_in_as_is_read_only_and_can_restore_the_admin_session(): void
    {
        $admin = $this->administrator();
        $otherAdmin = $this->administrator();
        $student = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.users.sign-in-as', $otherAdmin), ['current_password' => self::PASSWORD])
            ->assertForbidden();
        $this->post(route('admin.users.sign-in-as', $student), ['current_password' => self::PASSWORD])
            ->assertRedirect(route('account.overview'))->assertSessionHas(AccountPreview::KEY);
        $this->assertAuthenticatedAs($student);
        $this->get(route('account.overview'))->assertOk()->assertSee('Return to Admin');
        $this->patch(route('account.profile.update'), ['name' => 'Unwanted edit'])->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->post(route('account-preview.stop'))->assertRedirect(route('admin.users.index'))
            ->assertSessionMissing(AccountPreview::KEY);
        $this->assertAuthenticatedAs($admin);
    }

    private function administrator(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'password' => Hash::make(self::PASSWORD)]);
    }
}
