<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomeFeatureThemeConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_feature_artwork_uses_unboxed_icons_and_consistent_theme_surfaces(): void
    {
        foreach (['en', 'km', 'zh'] as $lang) {
            $html = $this->get('/?lang='.$lang)->assertOk()
                ->assertSee('data-home-pathways-icon', false)
                ->assertSee('data-home-level-icon', false)
                ->assertSee('data-home-feature="flexible"', false)
                ->assertSee('data-home-feature="educator"', false)
                ->assertSee('data-home-study-corner', false)
                ->assertSee('dark:bg-[#0d2c57]', false)
                ->getContent();

            $this->assertSame(1, substr_count($html, 'data-home-pathways-icon'));
            $this->assertSame(1, substr_count($html, 'data-home-level-icon'));
            $this->assertStringContainsString('bg-transparent', $html);
        }

        $template = file_get_contents(resource_path('views/home.blade.php'));
        $this->assertIsString($template);
        $this->assertStringContainsString('data-home-pathways-icon', $template);
        $this->assertStringContainsString('data-home-level-icon', $template);
        $this->assertStringNotContainsString('group-hover:bg-[#dceeff]', $template);
        $this->assertStringNotContainsString('bg-[#eaf3ff] text-[#316ea8]', $template);
        $this->assertStringContainsString('dark:from-[#123b6b]', $template);
        $this->assertStringContainsString('dark:bg-[#10345f]', $template);
    }

    public function test_cefr_guide_description_is_accurate_and_expanded_in_all_languages(): void
    {
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
