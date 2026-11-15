<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\PostTerm;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\WorkspaceMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EditorialSupportManagersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>'admin'])->save();
        return $user;
    }

    public function test_manager_sections_are_not_duplicated_and_have_real_routes(): void
    {
        $sections=collect(WorkspaceMenu::groups('admin'));
        foreach (['page-manager','post-manager','policy-center','support-center'] as $section) {
            $this->assertSame(1,$sections->where('key',$section)->count());
            foreach ($sections->firstWhere('key',$section)['items'] as $item) {
                $this->assertTrue(\Illuminate\Support\Facades\Route::has($item['route']),$item['label']);
            }
        }
        $this->assertSame(['all','create','category','tag'],
            array_column($sections->firstWhere('key','post-manager')['items'],'key'));
        $this->assertSame(['contact','faqs','help-articles','live-chat','ticketing'],
            array_column($sections->firstWhere('key','support-center')['items'],'key'));
        $this->assertSame(0,collect($sections->firstWhere('key','content')['items'])
            ->where('route','admin.editorial.index')->count());
    }

    public function test_admin_can_manage_post_categories_tags_and_safe_editorial_deletion(): void
    {
        $this->actingAs($admin=$this->admin());
        $this->post('/dashboard/post-terms/categories',[
            'name'=>'Study Skills','slug'=>'study-skills','description'=>'Reading and study.',
        ])->assertRedirect();
        $this->post('/dashboard/post-terms/tags',[
            'name'=>'Vocabulary','slug'=>'vocabulary',
        ])->assertRedirect();
        $category=PostTerm::where('kind','category')->firstOrFail();
        $tag=PostTerm::where('kind','tag')->firstOrFail();

        $this->get('/dashboard/post-terms/categories')->assertOk()->assertSee('Study Skills');
        $this->get('/dashboard/post-terms/tags')->assertOk()->assertSee('Vocabulary');
        $this->get('/dashboard/post-terms/categories/create')->assertOk()->assertSee('Save');
        $this->get('/dashboard/post-terms/categories/'.$category->id.'/edit')->assertOk();
        $this->patch('/dashboard/post-terms/categories/'.$category->id,[
            'name'=>'Study Methods','slug'=>'study-methods',
        ])->assertRedirect();
        $this->assertSame('Study Methods',$category->fresh()->name);

        $this->post('/dashboard/editorial/posts',[
            'title'=>'Study Techniques','body'=>'How to build a sustainable learning routine.',
            'category_ids'=>[$category->id], 'tag_ids'=>[$tag->id],
        ])->assertRedirect();
        $post=ContentEntry::where('type','post')->firstOrFail();
        $this->assertCount(2,$post->terms);
        $this->get('/dashboard/editorial/posts/'.$post->id.'/edit')->assertOk()->assertSee('Study Methods');
        $this->patch('/dashboard/editorial/posts/'.$post->id,[
            'title'=>'Study Techniques Revised','slug'=>$post->slug,
            'body'=>'Updated and verified study techniques.',
            'category_ids'=>[$category->id],'tag_ids'=>[],
        ])->assertRedirect();
        $this->assertCount(1,$post->fresh()->terms);
        $this->post('/dashboard/editorial/posts',[
            'title'=>'Invalid Term','body'=>'Invalid term ownership.',
            'category_ids'=>[$tag->id],
        ])->assertSessionHasErrors('category_ids.0');

        $this->delete('/dashboard/post-terms/tags/'.$tag->id)->assertRedirect();
        $this->assertDatabaseMissing('post_terms',['id'=>$tag->id]);
        $this->delete('/dashboard/editorial/posts/'.$post->id)->assertRedirect('/dashboard/editorial/posts');
        $this->assertSoftDeleted('content_entries',['id'=>$post->id]);
        $this->assertDatabaseHas('approval_events',[
            'subject_type'=>'editorial_post','subject_id'=>$post->id,'action'=>'deleted',
        ]);
    }

    public function test_faqs_and_help_articles_require_independent_review_before_publication(): void
    {
        $this->actingAs($author=$this->admin());
        $reviewer=$this->admin();
        foreach (['faqs'=>'faq','help-articles'=>'help_article'] as $kind=>$type) {
            $this->post('/dashboard/editorial/'.$kind,[
                'title'=>'Approved '.$type,'body'=>'Answers reviewed by a different administrator.',
            ])->assertRedirect();
            $entry=ContentEntry::where('type',$type)->firstOrFail();
            $this->get('/help-center')->assertOk()->assertDontSee('Approved '.$type);
            $this->post('/dashboard/editorial/'.$kind.'/'.$entry->id.'/submit')->assertRedirect();
            $this->post('/dashboard/editorial/'.$kind.'/reviews/'.$entry->id,['decision'=>'approve'])
                ->assertForbidden();
            $this->actingAs($reviewer)->get('/dashboard/editorial/'.$kind.'/reviews')
                ->assertOk()->assertSee($entry->title);
            $this->post('/dashboard/editorial/'.$kind.'/reviews/'.$entry->id,['decision'=>'approve'])
                ->assertRedirect();
            $this->get('/help-center')->assertOk()->assertSee($entry->title);
            $this->get('/help-center/'.($kind==='faqs'?'faqs':'articles').'/'.$entry->slug)
                ->assertOk()->assertSee('Answers reviewed');
            $this->actingAs($author);
        }
    }

    public function test_private_support_ticket_chat_crud_and_admin_reply_permissions(): void
    {
        $owner=User::factory()->create();
        $other=User::factory()->create();
        $admin=$this->admin();
        $this->actingAs($owner);
        $this->get('/support/tickets')->assertOk();
        $this->get('/support/tickets/create')->assertOk();
        $this->post('/support/tickets',[
            'subject'=>'Enrollment assistance','priority'=>'normal',
            'body'=>'Please help with my course enrollment.',
        ])->assertRedirect();
        $ticket=SupportTicket::where('kind','ticket')->firstOrFail();
        $this->assertSame($owner->id,$ticket->user_id);
        $this->assertCount(1,$ticket->messages);
        $this->get('/support/tickets/'.$ticket->id)->assertOk()->assertSee('Enrollment assistance');
        $this->post('/support/tickets/'.$ticket->id.'/messages',['body'=>'Any update?'])->assertRedirect();
        $this->get('/support/tickets/'.$ticket->id.'/messages?after=0')->assertOk()
            ->assertJsonCount(2,'messages');
        $this->patch('/support/tickets/'.$ticket->id,[
            'subject'=>'Enrollment update','priority'=>'high','status'=>'open',
        ])->assertRedirect();
        $this->assertSame('Enrollment update',$ticket->fresh()->subject);

        $this->actingAs($other)->get('/support/tickets/'.$ticket->id)->assertForbidden();
        $this->get('/support/tickets/'.$ticket->id.'/messages')->assertForbidden();
        $this->post('/support/tickets/'.$ticket->id.'/messages',['body'=>'Unauthorized'])->assertForbidden();
        $this->patch('/support/tickets/'.$ticket->id,[
            'subject'=>'Hijacked','priority'=>'high','status'=>'open',
        ])->assertForbidden();
        $this->get('/dashboard/support/tickets')->assertForbidden();

        $this->actingAs($admin)->get('/dashboard/support/tickets')->assertOk()
            ->assertSee('Enrollment update');
        $this->patch('/dashboard/support/tickets/'.$ticket->id,[
            'status'=>'in_progress','priority'=>'high',
        ])->assertRedirect();
        $this->post('/support/tickets/'.$ticket->id.'/messages',[
            'body'=>'An administrator is reviewing your enrollment.',
        ])->assertRedirect();
        $this->actingAs($owner)->get('/support/tickets/'.$ticket->id)->assertOk()
            ->assertSee('An administrator is reviewing');
        $this->delete('/support/tickets/'.$ticket->id)->assertRedirect();
        $this->assertSoftDeleted('support_tickets',['id'=>$ticket->id]);

        $this->post('/support/chat/start')->assertRedirect();
        $chat=SupportTicket::where('kind','chat')->firstOrFail();
        $this->post('/support/chat/start')->assertRedirect();
        $this->assertSame(1,SupportTicket::where('kind','chat')->count());
        $this->post('/support/tickets/'.$chat->id.'/messages',['body'=>'Hello support team'])->assertRedirect();
        $this->actingAs($admin)->get('/dashboard/support/chats')->assertOk()
            ->assertSee('Live chat');
        $this->patch('/dashboard/support/chats/'.$chat->id,[
            'status'=>'closed','priority'=>'normal',
        ])->assertRedirect();
        $this->post('/support/tickets/'.$chat->id.'/messages',[
            'body'=>'Late admin message',
        ])->assertStatus(409);
    }

    public function test_page_and_policy_crud_remain_private_and_are_not_silently_published(): void
    {
        $this->actingAs($admin=$this->admin());
        foreach (['pages'=>'page','policies'=>'policy'] as $kind=>$type) {
            $this->get('/dashboard/editorial/'.$kind)->assertOk();
            $this->get('/dashboard/editorial/'.$kind.'/create')->assertOk();
            $this->post('/dashboard/editorial/'.$kind,[
                'title'=>'Private '.$type.' entry','body'=>'Controlled page or policy content.',
            ])->assertRedirect();
            $entry=ContentEntry::where('type',$type)->firstOrFail();
            $this->get('/dashboard/editorial/'.$kind.'/'.$entry->id.'/edit')->assertOk();
            $this->patch('/dashboard/editorial/'.$kind.'/'.$entry->id,[
                'title'=>'Updated '.$type.' entry','slug'=>$entry->slug,
                'body'=>'Revised content in its original private state.',
            ])->assertRedirect();
            $this->assertSame('draft',$entry->fresh()->status);
            $this->delete('/dashboard/editorial/'.$kind.'/'.$entry->id)->assertRedirect();
            $this->assertSoftDeleted('content_entries',['id'=>$entry->id]);
        }
    }

    public function test_contact_messages_use_existing_inbox_and_support_private_archiving(): void
    {
        $this->post('/contact',[
            'name'=>'Example Learner','email'=>'learner@example.test','topic'=>'general',
            'subject'=>'Enrollment question','message'=>'Please assist with course information.',
            'consent'=>'1',
        ])->assertRedirect();
        $inquiry=\App\Models\ContactInquiry::firstOrFail();

        $this->actingAs(User::factory()->create())
            ->get('/dashboard/contact-inquiries')->assertForbidden();
        $this->actingAs($this->admin())
            ->get('/dashboard/contact-inquiries')->assertOk()->assertSee('Enrollment question');
        $this->delete('/dashboard/contact-inquiries/'.$inquiry->id)->assertRedirect();
        $this->assertSoftDeleted('contact_inquiries',['id'=>$inquiry->id]);
        $this->get('/dashboard/contact-inquiries')->assertOk()->assertDontSee('Enrollment question');
    }

}
