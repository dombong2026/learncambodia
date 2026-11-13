<?php

namespace Tests\Feature;

use App\Models\AppearanceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    public function test_appearance_draft_does_not_affect_public_until_second_admin_approves(): void
    {
        $author = $this->admin();
        $reviewer = $this->admin();
        $this->actingAs($author)->post('/dashboard/appearance/home', [
            'hero_title' => 'Reviewed educational hero',
            'hero_intro' => 'A safe and carefully reviewed learning journey.',
        ])->assertRedirect();

        $revision = AppearanceRevision::query()->firstOrFail();
        $this->assertSame('draft', $revision->status);
        $this->get('/')->assertSee('Explore English learning for every ambition.')
            ->assertDontSee('Reviewed educational hero');

        $this->post('/dashboard/appearance/revisions/'.$revision->id.'/submit')
            ->assertRedirect();
        $this->post('/dashboard/reviews/appearance/'.$revision->id, [
            'decision' => 'approve',
        ])->assertForbidden();

        $this->actingAs($reviewer)
            ->post('/dashboard/reviews/appearance/'.$revision->id, [
                'decision' => 'approve',
            ])->assertRedirect();

        $this->assertSame('approved', $revision->fresh()->status);
        $this->get('/')->assertSee('Reviewed educational hero')
            ->assertDontSee('Explore English learning for every ambition.');

        $this->assertDatabaseHas('approval_events', [
            'subject_type' => 'appearance',
            'subject_id' => $revision->id,
            'action' => 'approve',
            'actor_id' => $reviewer->id,
        ]);
    }

    public function test_rejected_appearance_does_not_activate(): void
    {
        $author = $this->admin();
        $reviewer = $this->admin();
        $this->actingAs($author)->post('/dashboard/appearance/auth', [
            'login_heading' => 'Unapproved sign in',
            'register_heading' => 'Unapproved sign up',
        ])->assertRedirect();

        $revision = AppearanceRevision::firstOrFail();
        $this->post('/dashboard/appearance/revisions/'.$revision->id.'/submit')
            ->assertRedirect();
        $this->actingAs($reviewer)->post('/dashboard/reviews/appearance/'.$revision->id, [
            'decision' => 'reject',
            'note' => 'Needs additional public privacy and accessibility review.',
        ])->assertRedirect();

        $this->post('/logout')->assertRedirect();
        $this->get('/login')->assertOk()->assertSee('Welcome back')->assertDontSee('Unapproved sign in');
        $this->assertSame('rejected', $revision->fresh()->status);
    }

    public function test_appearance_rejects_unlisted_fields_and_script_values_are_escaped(): void
    {
        $author = $this->admin();
        $reviewer = $this->admin();

        $this->actingAs($author)->post('/dashboard/appearance/typography', [
            'font_style' => 'custom-css-url',
            'text_scale' => 'normal',
        ])->assertSessionHasErrors('font_style');

        $this->assertDatabaseCount('appearance_revisions', 0);

        $this->post('/dashboard/appearance/home', [
            'hero_title' => '<script>alert(1)</script>',
            'hero_intro' => 'Text with plain characters',
        ])->assertRedirect();

        $revision = AppearanceRevision::firstOrFail();
        $this->post('/dashboard/appearance/revisions/'.$revision->id.'/submit')->assertRedirect();
        $this->actingAs($reviewer)
            ->post('/dashboard/reviews/appearance/'.$revision->id, ['decision' => 'approve'])
            ->assertRedirect();

        $this->get('/')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_student_cannot_create_appearance_drafts(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/dashboard/appearance/home', [
                'hero_title' => 'Unauthorized',
                'hero_intro' => 'Unauthorized',
            ])->assertForbidden();

        $this->assertDatabaseCount('appearance_revisions', 0);
    }
}
