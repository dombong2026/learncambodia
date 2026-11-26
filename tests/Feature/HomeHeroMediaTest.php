<?php

namespace Tests\Feature;

use App\Models\EditorMedia;
use App\Models\User;
use App\Support\HomeHeroMedia;
use App\Support\WebsiteSkins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class HomeHeroMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_video_is_public_only_when_selected_and_shared_across_skins(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $media = EditorMedia::create([
            'uploaded_by' => $admin->id, 'context' => 'library', 'path' => 'editor-media/promo.mp4',
            'mime' => 'video/mp4', 'original_name' => 'Promo.mp4', 'size' => 100,
        ]);
        Storage::disk('local')->put($media->path, str_repeat('x', 100));
        $this->get('/editor-media/'.$media->id)->assertForbidden();
        $this->get('/')->assertOk()->assertDontSee('data-home-hero-media=', false);
        $this->actingAs($admin)->get('/dashboard/appearance/home-hero-media?kind=video')->assertOk()->assertSee('Promo.mp4');
        $this->post('/dashboard/appearance/home-hero-media', ['source'=>'library','media_id'=>$media->id,'label'=>'Our promo'])->assertRedirect();
        $this->delete('/dashboard/media-library/'.$media->id)->assertSessionHasErrors('media');
        foreach (array_keys(WebsiteSkins::CATALOG) as $key) {
            $this->get('/dashboard/appearance/website-skins/preview/'.$key)->assertOk()
                ->assertSee('data-home-hero-media="video"', false)->assertSee('Our promo');
        }
        $this->post('/dashboard/appearance/website-skins', ['mode'=>'manual','skin'=>'water-flow'])->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertOk()->assertSee('data-home-hero-media="video"', false);
        $this->get('/editor-media/'.$media->id)->assertOk();
        $this->actingAs($admin)->post('/dashboard/appearance/home-hero-media',['source'=>'default'])->assertRedirect();
        $this->assertNull(HomeHeroMedia::current());
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertOk()->assertDontSee('data-home-hero-media=', false)
            ->assertSee('images/website-skins/water-flow.svg', false);
        $this->get('/editor-media/'.$media->id)->assertForbidden();
        $this->actingAs($admin)->delete('/dashboard/media-library/'.$media->id)->assertSessionHas('status', 'Media deleted.');
    }

    public function test_upload_and_private_media_validation(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role'=>'admin']);
        $private = EditorMedia::create([
            'uploaded_by'=>$admin->id,'context'=>'ticket','path'=>'editor-media/private.png',
            'mime'=>'image/png','original_name'=>'Private.png','size'=>100,
        ]);
        $this->actingAs($admin)->post('/dashboard/appearance/home-hero-media', ['source'=>'library','media_id'=>$private->id])
            ->assertSessionHasErrors('media_id');
        $this->post('/dashboard/appearance/home-hero-media', ['source'=>'upload','file'=>UploadedFile::fake()->create('invalid.pdf', 20, 'application/pdf')])
            ->assertSessionHasErrors('file');
        $this->post('/dashboard/appearance/home-hero-media', ['source'=>'upload','file'=>UploadedFile::fake()->image('cover.jpg'),'label'=>'A welcoming classroom'])
            ->assertRedirect();
        $media = EditorMedia::where('context','library')->sole();
        Storage::disk('local')->assertExists($media->path);
        $this->get('/')->assertOk()->assertSee('data-home-hero-media="image"', false)->assertSee('A welcoming classroom');
    }

    public function test_guest_and_student_cannot_manage_media(): void
    {
        $this->get('/dashboard/appearance/home-hero-media')->assertRedirect();
        $this->post('/dashboard/appearance/home-hero-media',['source'=>'default'])->assertRedirect();
        $this->actingAs(User::factory()->create(['role'=>'student']))->get('/dashboard/appearance/home-hero-media')->assertForbidden();
        $this->post('/dashboard/appearance/home-hero-media',['source'=>'default'])->assertForbidden();
    }
}
