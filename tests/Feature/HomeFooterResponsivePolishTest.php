<?php

namespace Tests\Feature;

use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomeFooterResponsivePolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_pathway_heading_and_level_guidance_have_inline_icons_without_blank_spacers(): void
    {
        $this->seed(CategorySeeder::class);
        $html = $this->get('/?lang=en')->assertOk()
            ->assertSee('data-home-pathways', false)
            ->assertSee('data-home-level-guidance', false)
            ->assertSee('href="'.route('categories.index').'"', false)
            ->assertSee('href="'.route('cefr.index').'"', false)
            ->getContent();

        $template = file_get_contents(resource_path('views/home.blade.php'));
        $this->assertIsString($template);
        $this->assertStringContainsString('data-home-level-guidance', $template);
        $this->assertStringContainsString('name="award" class="h-9 w-9"', $template);
        $this->assertStringContainsString('data-home-level-icon', $template);
        $this->assertStringContainsString('data-home-pathways-icon', $template);
        $this->assertStringNotContainsString('premium-card-content mt-20 text-lg font-black', $template);
        $this->assertStringContainsString('data-home-pathways', $html);
    }

    public function test_four_learning_steps_show_icons_in_each_real_navigation_card(): void
    {
        $html = $this->get('/?lang=en')->assertOk()
            ->assertSee('data-home-learning-steps', false)
            ->assertSee('data-home-step-card', false)
            ->assertSee('data-home-step-icon', false)
            ->getContent();

        $this->assertSame(4, substr_count($html, 'data-home-step-card'));
        $this->assertSame(4, substr_count($html, 'data-home-step-icon'));
        $this->assertStringContainsString('grid-cols-2 items-stretch', $html);
        $this->assertStringContainsString('xl:grid-cols-4', $html);
    }

    public function test_footer_has_four_responsive_sections_and_social_sharing_without_unverified_profiles(): void
    {
        foreach (['/?lang=en', '/categories?lang=en', '/cefr?lang=en'] as $path) {
            $html = $this->get($path)->assertOk()
                ->assertSee('data-footer-columns', false)
                ->assertSee('data-footer-bottom', false)
                ->assertSee('data-footer-social', false)
                ->assertSee('All Rights Reserved.')
                ->assertDontSee('Original educational content and branding reserved.')
                ->assertDontSee('Independent preparation information. Third-party exams and qualifications belong to their respective organizations.')
                ->getContent();

            $this->assertStringContainsString('md:grid-cols-4', $html);
            $this->assertSame(4, substr_count($html, 'aria-label="Share LearnCambodia on '));
            $this->assertStringContainsString('https://www.facebook.com/sharer/', $html);
            $this->assertStringContainsString('https://twitter.com/intent/tweet', $html);
            $this->assertStringContainsString('https://t.me/share/url', $html);
            $this->assertStringContainsString('https://api.whatsapp.com/send', $html);
            $this->assertStringNotContainsString('data-footer-social-profile', $html);
        }
    }
    public function test_footer_social_share_icons_have_no_visible_box_background_or_border(): void
    {
        $markup = $this->get('/?lang=en')->assertOk()
            ->assertSee('data-footer-social', false)
            ->getContent();

        $footerTemplate = file_get_contents(resource_path('views/partials/footer.blade.php'));
        $this->assertIsString($footerTemplate);

        $this->assertSame(4, substr_count($footerTemplate, 'rounded-lg border-0 bg-transparent'));
        $this->assertSame(4, substr_count($markup, 'rounded-lg border-0 bg-transparent'));
        $this->assertStringNotContainsString('border border-slate-700 bg-white/5', $footerTemplate);
        $this->assertStringNotContainsString('hover:bg-white/10', $footerTemplate);
        $this->assertStringNotContainsString('hover:border-amber-400', $footerTemplate);

        // Keep the accessible 40px touch targets, motion preferences, and real share links.
        $this->assertSame(4, substr_count($footerTemplate, 'inline-flex h-10 w-10'));
        $this->assertSame(4, substr_count($footerTemplate, 'motion-reduce:transform-none'));
        $this->assertSame(4, substr_count($footerTemplate, 'focus-visible:outline-amber-400'));
        $this->assertSame(4, substr_count($footerTemplate, 'aria-label="Share LearnCambodia on '));
    }

}
