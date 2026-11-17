<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class WorkspaceProfileNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_can_customize_its_private_profile_and_avatar(): void
    {
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jqmUAAAAASUVORK5CYII=');
        foreach (['admin' => 'admin', 'superadmin' => 'admin', 'student' => 'account', 'instructor' => 'instructor', 'developer' => 'developer'] as $role => $prefix) {
            $account = User::factory()->create(['role' => $role]);
            $edit = route("$prefix.profile.edit");
            $this->actingAs($account)->get($edit)->assertOk()->assertSee('Nationality')
                ->assertSee('Cambodia')->assertSee('First name')->assertSee('Social media');
            $this->from($edit)->post(route("$prefix.profile.update"), [
                '_method' => 'PATCH', 'name' => 'Display Name', 'first_name' => 'First', 'last_name' => 'Last',
                'gender' => 'prefer_not_to_say', 'nationality' => 'KH', 'address' => 'Private Profile Address',
                'social' => ['facebook' => 'https://facebook.com/example'],
                'avatar' => UploadedFile::fake()->createWithContent('photo.png', $png),
                'role' => 'superadmin', 'email' => 'untrusted@example.test',
            ])->assertSessionHasNoErrors()->assertRedirect($edit);
            $saved = $account->fresh();
            $this->assertSame('KH', $saved->profile_details['nationality']);
            $this->assertSame('https://facebook.com/example', $saved->profile_details['social']['facebook']);
            $this->assertSame($role, $saved->role);
            $this->assertSame($account->email, $saved->email);
            $this->assertArrayNotHasKey('profile_details', $saved->toArray());
            $this->assertStringNotContainsString('Private Profile Address', DB::table('users')->where('id', $saved->id)->value('profile_details'));
            Storage::disk('local')->assertExists($saved->avatar_path);
            $this->actingAs($saved)->get(route('workspace.avatar'))->assertOk()->assertHeader('Content-Type', 'image/png');
            $this->patch(route("$prefix.profile.update"), ['name' => 'Updated Name', 'remove_avatar' => '1'])->assertSessionHasNoErrors();
            Storage::disk('local')->assertMissing($saved->avatar_path);
            $this->assertSame('KH', $saved->fresh()->profile_details['nationality']);
            $this->assertNull($saved->fresh()->avatar_path);
        }
        $this->patch(route('developer.profile.update'), [
            'name' => 'Updated Name', 'nationality' => 'ZZ', 'social' => ['facebook' => 'javascript:alert(1)'],
            'avatar' => UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ])->assertSessionHasErrors(['nationality', 'social.facebook', 'avatar']);
    }

    public function test_support_workflow_delivers_private_notifications_and_can_mark_them_read(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $staff = User::factory()->create(['role' => 'superadmin']);
        $this->get(route('workspace.notifications'))->assertRedirect(route('login'));
        $this->actingAs($student)->post(route('support.tickets.store'), [
            'subject' => 'Course question', 'body' => 'Could you help with my course?', 'priority' => 'normal',
        ])->assertSessionHasNoErrors();
        $ticket = SupportTicket::sole();
        $alert = $staff->notifications()->sole();
        $this->assertSame('New support ticket', $alert->data['message']);
        $this->actingAs($staff)->get(route('workspace.notifications.unread'))->assertOk()->assertJsonPath('unread', 1);
        $url = route('workspace.notifications.open', $alert->id);
        $this->actingAs($other)->post($url)->assertNotFound();
        $this->actingAs($staff)->post($url)->assertRedirect(route('support.tickets.show', $ticket));
        $this->post(route('support.messages.store', $ticket), ['body' => 'We can help.'])->assertSessionHasNoErrors();
        $this->actingAs($student)->get(route('workspace.notifications.unread', ['preview' => 1]))
            ->assertOk()->assertJsonPath('unread', 1)->assertJsonStructure(['unread', 'preview']);
        $this->get(route('workspace.notifications'))->assertOk()->assertSee('New support reply')->assertDontSee('New support ticket');
        $this->post(route('workspace.notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $student->unreadNotifications()->count());
        $this->assertSame(1, $student->readNotifications()->count());
    }
}
