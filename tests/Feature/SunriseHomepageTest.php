<?php

namespace Tests\Feature;

use App\Models\AppearanceRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SunriseHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sunrise_is_selectable_previewable_and_requires_approval(): void
    {
        $author = User::factory()->create(['role'=>'admin']);
        $reviewer = User::factory()->create(['role'=>'admin']);
        $this->get('/')->assertOk()->assertDontSee('data-home-layout="sunrise"', false);
        $this->actingAs($author)->get('/dashboard/appearance/home')->assertOk()->assertSee('Sunrise')->assertSee('Classic');
        $this->get('/dashboard/appearance/home/preview/sunrise')->assertOk()->assertSee('data-home-layout="sunrise"', false);
        $this->get('/dashboard/appearance/home/preview/classic')->assertOk()->assertDontSee('data-home-layout="sunrise"', false);
        $this->get('/dashboard/appearance/home/preview/invalid')->assertNotFound();
        $data = ['hero_title'=>'Explore English learning for every ambition.', 'hero_intro'=>'Discover clear learning pathways from CEFR A1 to C2, international English examination preparation, and professional teaching development.'];
        $this->post('/dashboard/appearance/home', $data + ['home_layout'=>'invalid'])->assertSessionHasErrors('home_layout');
        foreach (['sunrise','classic'] as $layout) {
            $this->actingAs($author)->post('/dashboard/appearance/home', $data + ['home_layout'=>$layout])->assertRedirect();
            $revision = AppearanceRevision::latest('id')->firstOrFail();
            $this->post('/dashboard/appearance/revisions/'.$revision->id.'/submit')->assertRedirect();
            $this->actingAs($reviewer)->post('/dashboard/reviews/appearance/'.$revision->id, ['decision'=>'approve'])->assertRedirect();
            $response = $this->get('/')->assertOk();
            if ($layout === 'sunrise') {
                $response->assertSee('data-home-layout="sunrise"', false)->assertSee('Every day is a new beginning.');
            } else {
                $response->assertDontSee('data-home-layout="sunrise"', false);
            }
        }
    }

    public function test_student_cannot_preview_sunrise(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard/appearance/home/preview/sunrise')->assertForbidden();
    }
}
