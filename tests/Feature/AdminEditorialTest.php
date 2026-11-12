<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEditorialTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();

        return $user;
    }

    public function test_admin_can_create_all_three_kinds_as_private_drafts(): void
    {
        $this->actingAs($this->admin());

        foreach (['pages' => 'page', 'posts' => 'post', 'policies' => 'policy'] as $kind => $type) {
            $this->post('/dashboard/editorial/'.$kind, [
                'title' => 'Draft '.$type,
                'summary' => 'Internal editorial test',
                'body' => 'This is draft content for internal review.',
                'status' => 'published',
                'type' => 'page',
            ])->assertRedirect();

            $this->assertDatabaseHas('content_entries', [
                'type' => $type, 'title' => 'Draft '.$type, 'status' => 'draft',
            ]);
        }
    }

    public function test_student_cannot_access_editors(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard/editorial/policies')->assertForbidden();

        $this->post('/dashboard/editorial/pages', [
            'title' => 'Unauthorized', 'body' => 'Attempted publication',
        ])->assertForbidden();
    }

    public function test_draft_kind_cannot_be_changed_via_cross_section_link(): void
    {
        $admin = $this->admin();
        $entry = ContentEntry::create([
            'type' => 'policy',
            'title' => 'Private Legal Draft',
            'slug' => 'private-legal-draft',
            'body' => 'Unapproved draft',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard/editorial/pages/'.$entry->id.'/edit')->assertNotFound();

        $this->patch('/dashboard/editorial/posts/'.$entry->id, [
            'title' => 'Unauthorized Kind Change', 'body' => 'Change',
        ])->assertNotFound();
    }
}
