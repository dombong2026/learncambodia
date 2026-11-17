<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\EditorMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditorialDirectPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_editorial_managers_publish_and_withdraw_at_the_requested_urls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        foreach ([
            'pages' => '/pages/editorial-sample',
            'posts' => '/articles/editorial-sample',
            'policies' => '/policies/editorial-sample',
            'help-articles' => '/help-center/articles/editorial-sample',
        ] as $kind => $url) {
            $endpoint = '/dashboard/editorial/'.$kind;
            $payload = [
                'title' => 'Editorial sample',
                'body' => 'Complete document contents.',
                'featured_kind' => 'color',
                'featured_color' => '#334455',
            ];

            $this->actingAs($student)->post($endpoint, $payload + ['publication_intent' => 'publish'])
                ->assertForbidden();

            $this->actingAs($admin)->get($endpoint.'/create')->assertOk()
                ->assertSee('Publication')->assertSee('Click to copy')->assertSee('Featured media');
            $this->post($endpoint, $payload + ['publication_intent' => 'draft'])->assertRedirect();
            $entry = ContentEntry::latest('id')->firstOrFail();
            $this->actingAs($student)->get($url)->assertNotFound();

            $this->actingAs($admin)->patch($endpoint.'/'.$entry->id, $payload + ['publication_intent' => 'publish'])
                ->assertRedirect();
            $this->assertDatabaseHas('approval_events', [
                'subject_type' => 'editorial_'.$entry->type,
                'subject_id' => $entry->id,
                'actor_id' => $admin->id,
                'action' => 'published',
            ]);
            $this->actingAs($student)->get($url)->assertOk()
                ->assertSee('Complete document contents.')->assertSee('#334455');

            $this->actingAs($admin)->patch($endpoint.'/'.$entry->id, $payload + ['publication_intent' => 'draft'])
                ->assertRedirect();
            $this->actingAs($student)->get($url)->assertNotFound();
        }
    }

    public function test_policy_revision_and_legal_link_use_the_current_published_policy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/dashboard/editorial/policies', [
            'title' => 'Privacy policy', 'slug' => 'privacy', 'body' => 'Prior policy text.',
            'policy_version' => '1.0', 'policy_effective_on' => '2026-11-01',
            'publication_intent' => 'publish',
        ])->assertRedirect();
        $entry = ContentEntry::firstOrFail();
        $this->get('/information/privacy')->assertRedirect(ContentEntry::where('type', 'policy')->where('slug', 'privacy')->firstOrFail()->publicUrl());
        $this->get('/information/contact')->assertOk()->assertSee('Privacy policy');
        $this->get('/policies')->assertOk()->assertSee('Privacy policy');
        $this->patch('/dashboard/editorial/policies/'.$entry->id, [
            'title' => 'Privacy policy', 'slug' => 'privacy', 'body' => 'Current policy text.',
            'policy_version' => '2.0', 'policy_effective_on' => '2026-11-20',
            'publication_intent' => 'save',
        ])->assertRedirect();
        $this->get('/policies/privacy')->assertOk()->assertSee('Current policy text.')
            ->assertSee('2026-11-20')->assertDontSee('Prior policy text.');
        $revision = $entry->policyRevisions()->firstOrFail();
        $this->get('/dashboard/policy-history/'.$entry->id.'/'.$revision->id)
            ->assertOk()->assertSee('Prior policy text.')->assertSee('1.0');
        $this->actingAs(User::factory()->create())->get('/dashboard/policy-history/'.$entry->id.'/'.$revision->id)
            ->assertForbidden();
    }

    public function test_media_library_reuse_respects_publication_and_rejects_private_editor_media(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $file = UploadedFile::fake()->image('cover-sample.png', 16, 16);
        $upload = $this->actingAs($admin)->postJson('/dashboard/media-library', ['file' => $file])->assertCreated();
        $asset = $upload->json();
        $this->getJson('/dashboard/media-library?q=cover&kind=image')->assertOk()
            ->assertJsonPath('items.0.id', $asset['id']);
        $this->actingAs($student)->getJson('/dashboard/media-library')->assertForbidden();
        $this->get($asset['url'])->assertForbidden();

        $private = EditorMedia::create([
            'uploaded_by' => $admin->id, 'context' => 'ticket', 'path' => 'internal',
            'mime' => 'image/png', 'original_name' => 'ticket.png', 'size' => 100,
        ]);
        $data = ['title' => 'Public media', 'body' => 'Sample article', 'featured_kind' => 'image'];
        $this->actingAs($admin)->post('/dashboard/editorial/posts', $data + ['featured_media_id' => $private->id])
            ->assertSessionHasErrors('featured_media_id');
        $this->post('/dashboard/editorial/posts', $data + [
            'featured_media_id' => $asset['id'], 'publication_intent' => 'publish',
        ])->assertRedirect();
        $this->post('/dashboard/editorial/pages', $data + [
            'featured_media_id' => $asset['id'], 'publication_intent' => 'publish',
        ])->assertRedirect();

        $this->actingAs($student)->get($asset['url'])->assertOk();
        $entry = ContentEntry::where('type', 'post')->firstOrFail();
        $this->actingAs($admin)->delete('/dashboard/editorial/posts/'.$entry->id)->assertRedirect();
        $this->actingAs($student)->get($asset['url'])->assertOk();
        $entry = ContentEntry::where('type', 'page')->firstOrFail();
        $this->actingAs($admin)->delete('/dashboard/editorial/pages/'.$entry->id)->assertRedirect();
        $this->actingAs($student)->get($asset['url'])->assertForbidden();
    }

    public function test_youtube_embeds_only_valid_video_ids_and_video_streams_ranges(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/dashboard/editorial/pages', [
            'title' => 'Featured lesson', 'body' => 'Video lesson', 'featured_kind' => 'youtube',
            'featured_youtube' => 'https://youtube.com.invalid/watch?v=Ab123456789',
            'publication_intent' => 'publish',
        ])->assertSessionHasErrors('featured_youtube');
        $this->post('/dashboard/editorial/pages', [
            'title' => 'Featured lesson', 'body' => 'Video lesson', 'featured_kind' => 'youtube',
            'featured_youtube' => 'https://youtu.be/Ab123456789?si=ignored',
            'publication_intent' => 'publish',
        ])->assertRedirect();
        $this->get('/pages/featured-lesson')->assertOk()->assertSee('https://www.youtube-nocookie.com/embed/Ab123456789');

        Storage::disk('local')->put('editor-media/range.mp4', str_repeat('x', 100));
        $media = EditorMedia::create([
            'uploaded_by' => $admin->id, 'context' => 'library', 'path' => 'editor-media/range.mp4',
            'mime' => 'video/mp4', 'original_name' => 'range.mp4', 'size' => 100,
        ]);
        $this->post('/dashboard/editorial/pages', [
            'title' => 'Hosted video', 'body' => 'Video lesson', 'featured_kind' => 'video',
            'featured_media_id' => $media->id, 'publication_intent' => 'publish',
        ])->assertRedirect();
        $this->get('/pages/hosted-video')->assertOk()->assertSee('data-featured-video', false);
        $this->get('/editor-media/'.$media->id, ['Range' => 'bytes=10-19'])
            ->assertStatus(206)->assertHeader('Content-Range', 'bytes 10-19/100');
    }
}
