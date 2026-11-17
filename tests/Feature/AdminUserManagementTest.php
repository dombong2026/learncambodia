<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAccess;
use App\Support\WorkspaceMenu;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_account_and_persist_only_selected_permissions(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($actor)->get(route('admin.users.create'))->assertOk()
            ->assertSee('Admin permissions')->assertDontSee('value="superadmin"', false);

        $response = $this->post(route('admin.users.store'), $this->account([
            'role' => User::ROLE_ADMIN, 'permissions' => ['learning', 'media'],
        ]))->assertSessionHasNoErrors();
        $created = User::where('email', 'new-user@example.test')->firstOrFail();
        $response->assertRedirect(route('admin.users.show', $created));
        $this->assertTrue(Hash::check('Temporary-Pass-1234', $created->password));
        $this->assertFalse($created->hasVerifiedEmail());
        $this->assertSame(['learning', 'media'], $created->admin_permissions);
        $this->assertDatabaseHas('account_role_changes', [
            'actor_user_id' => $actor->id, 'user_id' => $created->id, 'new_role' => User::ROLE_ADMIN,
        ]);
        $this->get(route('admin.users.index', ['role' => 'admin', 'q' => 'new-user']))->assertOk()
            ->assertSee('new-user@example.test');
        $this->actingAs($created)->get(route('admin.courses.index'))->assertOk();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.system-settings.branding'))->assertForbidden();
        $this->get(route('admin.overview'))->assertOk()->assertDontSee(route('admin.users.index'), false);
    }

    public function test_only_super_admin_can_grant_super_admin_and_other_roles_can_sign_in(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $super = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->actingAs($admin)->post(route('admin.users.store'), $this->account(['role' => User::ROLE_SUPERADMIN]))
            ->assertSessionHasErrors('role');
        $this->actingAs($super)->post(route('admin.users.store'), $this->account([
            'role' => User::ROLE_SUPERADMIN, 'email' => 'new-super@example.test',
        ]))->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'new-super@example.test', 'role' => User::ROLE_SUPERADMIN]);

        $this->post(route('admin.users.store'), $this->account(['role' => User::ROLE_INSTRUCTOR]))->assertRedirect();
        auth()->logout();
        $this->post(route('login'), ['email' => 'new-user@example.test', 'password' => 'Temporary-Pass-1234'])
            ->assertRedirect(route('instructor.overview'));
        $this->assertAuthenticated();
    }

    public function test_scoped_admin_cannot_grant_more_access_or_edit_their_own_role(): void
    {
        $scoped = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $scoped->forceFill(['admin_permissions' => ['users']])->save();
        $full = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create();

        $this->actingAs($scoped)->post(route('admin.users.store'), $this->account([
            'role' => User::ROLE_ADMIN, 'permissions' => ['settings'],
        ]))->assertForbidden();
        $this->patch(route('admin.users.access.update', $full), ['role' => 'student'])->assertForbidden();
        $this->patch(route('admin.users.access.update', $scoped), ['role' => 'student'])->assertForbidden();
        $this->patch(route('admin.users.access.update', $student), ['role' => 'developer'])
            ->assertRedirect(route('admin.users.show', $student));
        $this->assertSame('developer', $student->fresh()->role);

        $this->actingAs(User::factory()->create())->get(route('admin.users.create'))->assertForbidden();
        $this->post(route('admin.users.store'), $this->account())->assertForbidden();
    }

    public function test_access_changes_revoke_tokens_and_unknown_permissions_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $account = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $account->createToken('Read access');
        $this->actingAs($admin)->patch(route('admin.users.access.update', $account), [
            'role' => 'admin', 'permissions' => ['unknown-module'],
        ])->assertSessionHasErrors('permissions.0');
        $this->patch(route('admin.users.access.update', $account), [
            'role' => 'admin', 'permissions' => ['editorial'],
        ])->assertRedirect(route('admin.users.show', $account));
        $this->assertSame(['editorial'], AdminAccess::grants($account->fresh()));
        $this->assertSame(0, $account->tokens()->count());
    }

    public function test_admin_alerts_only_go_to_accounts_that_can_open_the_destination(): void
    {
        $full = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $scoped = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $scoped->forceFill(['admin_permissions' => ['users']])->save();

        \App\Support\WorkspaceNotifications::administrators('Academic evidence pending', 'admin.academic.evidence');
        $this->assertSame(1, $full->notifications()->count());
        $this->assertSame(0, $scoped->notifications()->count());
    }

    public function test_each_admin_navigation_page_has_its_own_header_action(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin);
        $courseCreate = route('admin.course-builder.create');
        $checked = [];
        foreach (WorkspaceMenu::groups('admin') as $group) {
            foreach ($group['items'] as $item) {
                $url = WorkspaceMenu::href('admin', $group, $item);
                if (isset($checked[$url])) {
                    continue;
                }

                $response = $this->get($url)->assertOk();
                $checked[$url] = true;
                $document = new DOMDocument;
                $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
                $actions = (new DOMXPath($document))->query('//*[@data-workspace-heading-actions]//a');
                $targets = [];
                foreach ($actions as $action) {
                    $targets[$action->getAttribute('href')] = trim($action->textContent);
                }

                if ($item['route'] === 'admin.courses.index') {
                    $this->assertArrayHasKey($courseCreate, $targets, $url);
                } else {
                    $this->assertArrayNotHasKey($courseCreate, $targets, $url);
                }

                if ($item['route'] === 'admin.users.index') {
                    $this->assertSame('Add User', $targets[route('admin.users.create')] ?? null);
                }
            }
        }
        $this->assertGreaterThan(35, count($checked));
    }

    private function account(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'New account',
            'email' => 'NEW-USER@example.test',
            'password' => 'Temporary-Pass-1234',
            'password_confirmation' => 'Temporary-Pass-1234',
            'role' => 'student',
        ];
    }
}
