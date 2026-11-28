<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomeFeatureThemeConsistencyTest extends TestCase
{
    use RefreshDatabase;


    private function selectHomepageLayout(string $layout): void
    {
        $admin = \App\Models\User::factory()->create([
            'role' => 'admin',
        ]);

        \App\Models\AppearanceRevision::create([
            'section' => 'home_layout',
            'settings' => ['home_layout' => $layout],
            'status' => 'approved',
            'created_by' => $admin->id,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->assertSame(
            $layout,
            \App\Support\Appearance::value('home', 'home_layout')
        );
    }

    public function test_home_feature_artwork_uses_unboxed_icons_and_consistent_theme_surfaces(): void
    {
        foreach (['classic', 'sunrise'] as $layout) {
            $this->selectHomepageLayout($layout);

            foreach (['en', 'km', 'zh'] as $lang) {
                $response = $this->get('/?lang='.$lang)
                    ->assertOk()
                    ->assertSee('lang="'.$lang.'"', false)
                    ->assertSee('data-theme-toggle', false);

                if ($layout === 'classic') {
                    $response
                        ->assertSee('class="lc-home"', false)
                        ->assertSee('css/home.css', false)
                        ->assertSee('id="home-cefr"', false)
                        ->assertSee('id="home-educators"', false)
                        ->assertSee('alt=""', false)
                        ->assertDontSee('data-home-layout="sunrise"', false);
                } else {
                    $response
                        ->assertSee('data-home-layout="sunrise"', false)
                        ->assertSee('css/home-sunrise.css', false)
                        ->assertSee('class="sr-icon"', false)
                        ->assertSee('class="sr-level"', false)
                        ->assertDontSee('class="lc-home"', false);
                }

                foreach (['A1','A2','B1','B2','C1','C2'] as $level) {
                    $response->assertSee(
                        'href="'.route('cefr.index').'#'.$level.'"',
                        false
                    );
                }
            }
        }
    }

    public function test_cefr_guide_description_is_accurate_and_expanded_in_all_languages(): void
    {
        $this->selectHomepageLayout('sunrise');

        $original = [
            'en' => 'Explore CEFR descriptors from A1 to C2 and choose a pathway appropriate to your goals.',
            'km' => 'ស្វែងយល់កម្រិត CEFR ចាប់ពី A1 ដល់ C2 សម្រាប់កំណត់គោលដៅសិក្សា។',
            'zh' => '了解 CEFR A1 至 C2 等级描述，选择合适的学习路径。',
        ];
        foreach ($original as $lang => $previous) {
            $description = trans('pages.level_guidance_desc', [], $lang);
            $this->assertGreaterThan(mb_strlen($previous), mb_strlen($description));
            $this->assertStringContainsString('CEFR', $description);
            $this->assertStringContainsString('A1', $description);
            $this->assertStringContainsString('C2', $description);
            $this->get('/?lang='.$lang)->assertOk()->assertSee($description);
        }
    }

    public function test_featured_card_links_remain_functional(): void
    {
        $this->get('/?lang=en')->assertOk()
            ->assertSee('href="'.route('categories.index').'"', false)
            ->assertSee('href="'.route('courses.index').'"', false)
            ->assertSee('href="'.route('cefr.index').'"', false)
            ->assertSee('href="'.route('login').'"', false);
    }
}
