<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\EditorMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaLibraryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_grid_paginates_at_four_rows_and_keeps_the_editorial_picker_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (range(1, 17) as $index) {
            EditorMedia::create([
                'uploaded_by' => $admin->id, 'context' => 'library', 'path' => "editor-media/{$index}.png",
                'mime' => 'image/png', 'original_name' => "Image {$index}.png", 'size' => 100,
            ]);
        }

        foreach (range(1, 4) as $columns) {
            $this->actingAs($admin)->get("/dashboard/media-library?columns={$columns}")
                ->assertOk()->assertViewHas('items', fn ($items) => $items->count() === $columns * 4);
        }

        $this->getJson('/dashboard/media-library?columns=1')->assertOk()->assertJsonCount(12, 'items');
        $this->get('/dashboard/media-library?columns=4&page=3')->assertRedirect('/dashboard/media-library?columns=4&page=2');
    }

    public function test_admin_can_upload_rename_replace_and_delete_unused_library_media(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->from('/dashboard/media-library')
            ->post('/dashboard/media-library', ['file' => UploadedFile::fake()->image('sample.png')])
            ->assertRedirect('/dashboard/media-library')->assertSessionHas('status', 'Media uploaded.');
        $media = EditorMedia::sole();
        $oldPath = $media->path;
        $endpoint = '/dashboard/media-library/'.$media->id;
        $this->get('/editor-media/'.$media->id)->assertOk();

        $this->patch($endpoint, ['title' => 'Renamed media'])
            ->assertRedirect()->assertSessionHas('status', 'Media updated.');
        $this->assertSame('Renamed media', $media->fresh()->original_name);

        $this->patch($endpoint, [
            'title' => 'Renamed media',
            'replacement' => UploadedFile::fake()->create('sample.mp4', 20, 'video/mp4'),
        ])->assertSessionHasErrors('replacement');

        $this->patch($endpoint, [
            'title' => 'Renamed media',
            'replacement' => UploadedFile::fake()->image('replacement.jpg'),
        ])->assertSessionHas('status', 'Media updated.');
        $current = $media->fresh();
        $this->assertSame('image/jpeg', $current->mime);
        Storage::disk('local')->assertMissing($oldPath)->assertExists($current->path);

        $this->actingAs(User::factory()->create(['role' => 'student']))
            ->patch($endpoint, ['title' => 'Unauthorized'])->assertForbidden();
        $this->delete($endpoint)->assertForbidden();
        $this->actingAs($admin)->delete($endpoint)->assertSessionHas('status', 'Media deleted.');
        $this->assertModelMissing($current);
        Storage::disk('local')->assertMissing($current->path);
    }

    public function test_delete_protects_live_entries_and_historical_policy_files(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = $this->actingAs($admin)
            ->postJson('/dashboard/media-library', ['file' => UploadedFile::fake()->image('policy.png')])
            ->assertCreated()->json();
        $endpoint = '/dashboard/media-library/'.$asset['id'];
        $this->post('/dashboard/editorial/policies', [
            'title' => 'Sample policy', 'body' => 'Sample policy text.',
            'publication_intent' => 'publish', 'featured_kind' => 'image', 'featured_media_id' => $asset['id'],
        ])->assertRedirect();
        $entry = ContentEntry::sole();
        $this->delete($endpoint)->assertSessionHasErrors('media');

        $this->patch('/dashboard/editorial/policies/'.$entry->id, [
            'title' => 'Sample policy', 'body' => 'Updated policy text.',
            'publication_intent' => 'save', 'featured_kind' => 'none',
        ])->assertRedirect();
        $this->assertNull($entry->fresh()->featured_media_id);
        $this->delete($endpoint)->assertSessionHasErrors('media');
        Storage::disk('local')->assertExists(EditorMedia::findOrFail($asset['id'])->path);
    }
}
