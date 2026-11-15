<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\EditorMedia;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\EditorHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class RichEditorAndMediaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();
        return $user;
    }

    public function test_all_six_create_forms_have_working_shared_text_html_editor_and_sidebars(): void
    {
        $this->actingAs($this->admin());
        foreach (['pages','posts','policies','faqs','help-articles'] as $kind) {
            $this->get('/dashboard/editorial/'.$kind.'/create')
                ->assertOk()
                ->assertSee('data-rich-editor', false)
                ->assertSee('data-editor-mode="text"', false)
                ->assertSee('data-editor-mode="html"', false)
                ->assertSee('data-editor-upload', false)
                ->assertSee('data-editor-color', false)
                ->assertSee('Document settings')
                ->assertSee('Search appearance')
                ->assertSee('data-editor-words-summary', false);
        }
        $this->get('/support/tickets/create')->assertOk()
            ->assertSee('data-rich-editor', false)
            ->assertSee('Ticket settings')
            ->assertSee('data-editor-mode="html"', false)
            ->assertSee('Support resources');
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('editor-media.store'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('editor-media.show'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.editorial.preview'));
    }

    public function test_sanitizer_preserves_rich_elements_but_removes_executable_content(): void
    {
        $html = '<h2 style="text-align:center;position:absolute">Grammar</h2>'.
            '<p><strong>Learn</strong> <a href="javascript:alert(1)" onclick="evil()">here</a></p>'.
            '<img src="https://external.invalid/tracker" onerror="evil()">'.
            '<script>alert(2)</script><iframe src="https://external.invalid"></iframe>'.
            '<table><tr><td colspan="2">A</td></tr></table>'.
            '<span style="color:rgb(255,0,0);background-image:url(javascript:evil())">Red</span>';

        $clean = EditorHtml::clean($html);
        $this->assertStringContainsString('<h2 style="text-align:center">Grammar</h2>', $clean);
        $this->assertStringContainsString('<strong>Learn</strong>', $clean);
        $this->assertStringContainsString('<table>', $clean);
        $this->assertStringContainsString('color:#ff0000', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('tracker', $clean);
        $this->assertStringNotContainsString('position:absolute', $clean);
    }

    public function test_editorial_rich_draft_preview_and_seo_remain_private(): void
    {
        $this->actingAs($admin = $this->admin());
        $response = $this->post('/dashboard/editorial/pages', [
            'title' => 'Private Rich Draft',
            'summary' => 'Private summary',
            'body' => 'A safe rich page.',
            'body_html' => '<h2>Details</h2><p>A <strong>safe</strong> rich page.</p><script>alert(1)</script>',
            'seo_title' => 'SEO Draft Title',
            'seo_description' => 'SEO search description',
        ]);
        $response->assertRedirect();
        $entry = ContentEntry::where('type', 'page')->firstOrFail();
        $this->assertSame('draft', $entry->status);
        $this->assertSame('SEO Draft Title', $entry->seo_title);
        $this->assertStringContainsString('<strong>safe</strong>', $entry->body_html);
        $this->assertStringNotContainsString('script', $entry->body_html);
        $this->get('/dashboard/editorial/pages/'.$entry->id.'/preview')
            ->assertOk()->assertSee('SEO Draft Title', false) // source is private even if SEO title not rendered
            ->assertSee('<strong>safe</strong>', false);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard/editorial/pages/'.$entry->id.'/preview')->assertForbidden();
        $this->get('/articles/'.$entry->slug)->assertNotFound();
    }

    public function test_private_editor_media_publication_gating_and_editorial_claim(): void
    {
        Storage::fake('local');
        $this->actingAs($author = $this->admin());
        $response = $this->withHeaders(['Accept'=>'application/json'])
            ->post('/editor-media', [
                'context' => 'editorial',
                'file' => UploadedFile::fake()->image('diagram.png'),
            ]);
        $response->assertCreated()->assertJsonStructure(['id','url','mime','name']);
        $id = (int) $response->json('id');
        $url = $response->json('url');
        $this->assertSame('/editor-media/'.$id, $url);
        $this->get($url)->assertOk();

        $this->post('/dashboard/editorial/posts', [
            'title' => 'Learning With Diagrams',
            'body' => 'A diagram supports clear language learning.',
            'body_html' => '<p>A diagram supports clear language learning.</p><figure><img src="'.$url.'" alt="Diagram"><figcaption>A diagram</figcaption></figure>',
        ])->assertRedirect();
        $entry = ContentEntry::where('type','post')->firstOrFail();
        $this->assertSame($entry->id, EditorMedia::findOrFail($id)->content_entry_id);
        $this->get('/dashboard/editorial/posts/'.$entry->id.'/preview')
            ->assertOk()->assertSee('<img', false);

        auth()->logout();
        $this->get($url)->assertForbidden();
        $this->actingAs($author)->post('/dashboard/editorial/posts/'.$entry->id.'/submit')->assertRedirect();
        $this->actingAs($this->admin())->post('/dashboard/editorial/posts/reviews/'.$entry->id,[
            'decision' => 'approve',
        ])->assertRedirect();

        auth()->logout();
        $this->get($url)->assertOk();
        $this->get('/articles/'.$entry->slug)->assertOk()
            ->assertSee('A diagram', false)
            ->assertSee($url, false);
    }

    public function test_private_ticket_media_is_not_accessible_to_other_accounts(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $response = $this->withHeaders(['Accept'=>'application/json'])
            ->post('/editor-media',[
                'context'=>'ticket','file'=>UploadedFile::fake()->image('question.png'),
            ]);
        $response->assertCreated();
        $url = $response->json('url');

        $this->post('/support/tickets',[
            'subject'=>'Support with a picture','priority'=>'normal',
            'body'=>'Please review the attached picture.',
            'body_html'=>'<p>Please review the attached picture.</p><figure><img src="'.$url.'" alt="Question"></figure>',
        ])->assertRedirect();
        $ticket = SupportTicket::firstOrFail();
        $this->assertSame($ticket->id, EditorMedia::firstOrFail()->support_ticket_id);
        $this->assertStringContainsString('<img', SupportMessage::firstOrFail()->body_html);
        $this->get($url)->assertOk();

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        auth()->logout();
        $this->get($url)->assertForbidden();
        $this->actingAs($this->admin())->get($url)->assertOk();
    }

    public function test_non_admin_cannot_upload_editorial_media_or_claim_others_ticket_media(): void
    {
        Storage::fake('local');
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($first)->withHeaders(['Accept'=>'application/json'])->post('/editor-media',[
            'context'=>'editorial','file'=>UploadedFile::fake()->image('private.png'),
        ])->assertForbidden();
        $response = $this->post('/editor-media',[
            'context'=>'ticket','file'=>UploadedFile::fake()->image('private.png'),
        ]);
        $response->assertCreated();
        $url=$response->json('url');

        $this->actingAs($second)->withHeaders(['Accept'=>'text/html'])->post('/support/tickets',[
            'subject'=>'Unauthorized attachment','priority'=>'normal',
            'body'=>'Please see private attachment.',
            'body_html'=>'<p>Please see private attachment.</p><img src="'.$url.'" alt="Private">',
        ])->assertSessionHasErrors('body_html');
        $this->assertDatabaseCount('support_tickets',0);
        $this->post('/support/tickets',[
            'subject'=>'No active file','priority'=>'normal',
            'body'=>'Use text only',
            'body_html'=>'<p>Use text only</p><img src="https://not-allowed.invalid/x.png" onerror="alert(1)">',
        ])->assertRedirect();
        $message=SupportMessage::firstOrFail();
        $this->assertStringNotContainsString('not-allowed', $message->body_html);
    }
}
