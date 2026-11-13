<?php

namespace Tests\Feature;

use App\Models\ApprovalEvent;
use App\Models\ContentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialPublicationReviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>'admin'])->save();
        return $user;
    }

    public function test_editorial_article_requires_different_administrator_before_publication(): void
    {
        $author=$this->admin();
        $reviewer=$this->admin();

        $this->actingAs($author)->post('/dashboard/editorial/posts',[
            'title'=>'Approved English Study Guide',
            'slug'=>'approved-english-guide',
            'summary'=>'Guidance on English language practice.',
            'body'=>'Study every day and practice with verified learning material.',
        ])->assertRedirect();

        $post=ContentEntry::query()->firstOrFail();
        $this->assertSame('draft',$post->status);
        $this->get('/articles')->assertOk()->assertDontSee($post->title);
        $this->get('/articles/'.$post->slug)->assertNotFound();

        $this->post('/dashboard/editorial/posts/'.$post->id.'/submit')
            ->assertRedirect('/dashboard/editorial/posts');
        $this->assertSame('pending_review',$post->fresh()->status);

        $this->patch('/dashboard/editorial/posts/'.$post->id,[
            'title'=>'Unapproved change','slug'=>$post->slug,
            'body'=>'Cannot edit content during editorial review.',
        ])->assertStatus(409);

        $this->post('/dashboard/editorial/posts/reviews/'.$post->id,[
            'decision'=>'approve',
        ])->assertForbidden();

        $this->actingAs($reviewer)
            ->get('/dashboard/editorial/posts/reviews')->assertOk()
            ->assertSee('Approved English Study Guide');
        $this->post('/dashboard/editorial/posts/reviews/'.$post->id,[
            'decision'=>'approve',
        ])->assertRedirect();

        $this->assertSame('published',$post->fresh()->status);
        $this->get('/articles/'.$post->slug)->assertOk()
            ->assertSee('Study every day');
        $this->get('/articles')->assertOk()->assertSee($post->title);
        $this->get('/')->assertOk()->assertSee($post->title);
        $this->patch('/dashboard/editorial/posts/'.$post->id,[
            'title'=>'Change after publishing','slug'=>$post->slug,
            'body'=>'Trying to update published content.',
        ])->assertStatus(409);
        $this->assertDatabaseHas('approval_events',[
            'subject_type'=>'editorial_post','subject_id'=>$post->id,
            'action'=>'approve','actor_id'=>$reviewer->id,
            'to_status'=>'published',
        ]);

        $this->post('/dashboard/editorial/posts/reviews/'.$post->id,[
            'decision'=>'approve',
        ])->assertStatus(409);
    }

    public function test_rejected_article_remains_private_until_revised_and_reapproved(): void
    {
        $author=$this->admin();
        $reviewer=$this->admin();

        $this->actingAs($author)->post('/dashboard/editorial/posts',[
            'title'=>'Early Draft Article','summary'=>'Article under review.',
            'body'=>'Please verify this educational content before publishing.',
        ])->assertRedirect();
        $post=ContentEntry::firstOrFail();

        $this->post('/dashboard/editorial/posts/'.$post->id.'/submit')
            ->assertRedirect();

        $this->actingAs($reviewer)->post('/dashboard/editorial/posts/reviews/'.$post->id,[
            'decision'=>'reject','note'=>'Review language accuracy and examples before publication.',
        ])->assertRedirect();

        $this->assertSame('rejected',$post->fresh()->status);
        $this->get('/articles/'.$post->slug)->assertNotFound();

        $this->actingAs($author)->patch('/dashboard/editorial/posts/'.$post->id,[
            'title'=>'Revised Educational Article','slug'=>$post->slug,
            'summary'=>'Improved educational article.',
            'body'=>'This is the corrected, reviewed material for learners.',
        ])->assertRedirect();
        $this->post('/dashboard/editorial/posts/'.$post->id.'/submit')
            ->assertRedirect();

        $this->actingAs($reviewer)->post('/dashboard/editorial/posts/reviews/'.$post->id,[
            'decision'=>'approve',
        ])->assertRedirect();

        $this->get('/articles/'.$post->slug)->assertOk()
            ->assertSee('Revised Educational Article');
        $this->assertSame(2,ApprovalEvent::where('subject_type','editorial_post')
            ->where('subject_id',$post->id)->where('action','submitted')->count());
    }

    public function test_pages_and_policies_cannot_be_published_through_article_review(): void
    {
        $author=$this->admin();
        $this->actingAs($author)->post('/dashboard/editorial/policies',[
            'title'=>'Draft School Policy',
            'body'=>'A policy draft needing legal review.',
        ])->assertRedirect();
        $entry=ContentEntry::firstOrFail();

        $this->post('/dashboard/editorial/policies/'.$entry->id.'/submit')
            ->assertNotFound();
        $this->post('/dashboard/editorial/policies/reviews/'.$entry->id,[
            'decision'=>'approve',
        ])->assertNotFound();
        $this->get('/dashboard/editorial/policies/reviews')->assertNotFound();
        $this->get('/articles/'.$entry->slug)->assertNotFound();
    }

    public function test_student_cannot_submit_or_approve_editorial_content(): void
    {
        $admin=$this->admin();
        $post=ContentEntry::create([
            'type'=>'post','slug'=>'protected','title'=>'Protected Draft','body'=>'Private editorial content.',
            'status'=>'draft','created_by'=>$admin->id,
        ]);
        $student=User::factory()->create();

        $this->actingAs($student)
            ->post('/dashboard/editorial/posts/'.$post->id.'/submit')->assertForbidden();
        $this->post('/dashboard/editorial/posts/reviews/'.$post->id,[
            'decision'=>'approve',
        ])->assertForbidden();
        $this->assertSame('draft',$post->fresh()->status);
    }
}
