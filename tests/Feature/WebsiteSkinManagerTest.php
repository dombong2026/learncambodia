<?php

namespace Tests\Feature;

use App\Models\AppearanceRevision;
use App\Models\User;
use App\Support\WebsiteSkins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteSkinManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_skin_at_a_time_with_legacy_and_preview(): void
    {
        $admin = User::factory()->create(['role'=>'admin']);
        $this->get('/')->assertOk()->assertDontSee('data-website-skin=', false);
        $this->actingAs($admin)->get('/dashboard/appearance/website-skins')->assertOk()
            ->assertSee('Natural Premium')->assertSee('Phumi Spirit')->assertSee('role="switch"', false);
        foreach (array_keys(WebsiteSkins::CATALOG) as $key) {
            $this->post('/dashboard/appearance/website-skins', ['mode'=>'manual','skin'=>$key])->assertRedirect();
            $this->get('/')->assertOk()->assertSee('data-website-skin="'.$key.'"', false)
                ->assertSee('images/website-skins/'.$key.'.svg', false);
            $this->get('/courses')->assertOk()->assertSee('data-website-skin="'.$key.'"', false);
            $this->assertSame(1, AppearanceRevision::where('section','website_skins')->where('status','approved')->count());
        }
        $this->get('/dashboard/appearance/website-skins/preview/natural-premium')->assertOk()
            ->assertSee('data-website-skin="natural-premium"', false)->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/')->assertOk()->assertSee('data-website-skin="phumi-spirit"', false);
        $this->post('/dashboard/appearance/website-skins', ['mode'=>'manual','skin'=>'invalid'])->assertSessionHasErrors('skin');
        $this->post('/dashboard/appearance/website-skins', ['mode'=>'legacy'])->assertRedirect();
        $this->get('/')->assertOk()->assertDontSee('data-website-skin=', false);
        $this->assertDatabaseMissing('appearance_revisions',['section'=>'home']);
    }

    public function test_automatic_mode_and_schedule(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-29 08:00:00', 'Asia/Phnom_Penh'));
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $this->post('/dashboard/appearance/website-skins', ['mode'=>'automatic'])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('data-website-skin="natural-premium"', false);
        $period = ['start'=>'2026-11-29','end'=>'2026-11-30','skin'=>'angkor-heritage'];
        $this->post('/dashboard/appearance/website-skins/periods', $period)->assertRedirect();
        $this->get('/')->assertOk()->assertSee('data-website-skin="angkor-heritage"', false);
        $this->post('/dashboard/appearance/website-skins/periods', $period)->assertSessionHasErrors('start');
        $this->post('/dashboard/appearance/website-skins/periods/remove', $period)->assertRedirect();
        $this->get('/')->assertOk()->assertSee('data-website-skin="natural-premium"', false);
    }

    public function test_guest_and_student_cannot_activate_or_preview(): void
    {
        $this->post('/dashboard/appearance/website-skins', ['mode'=>'manual','skin'=>'sorya-sunrise'])->assertRedirect();
        $this->actingAs(User::factory()->create(['role'=>'student']));
        $this->get('/dashboard/appearance/website-skins')->assertForbidden();
        $this->get('/dashboard/appearance/website-skins/preview/sorya-sunrise')->assertForbidden();
        $this->post('/dashboard/appearance/website-skins', ['mode'=>'manual','skin'=>'sorya-sunrise'])->assertForbidden();
        $this->assertDatabaseMissing('appearance_revisions',['section'=>'website_skins']);
    }
}
