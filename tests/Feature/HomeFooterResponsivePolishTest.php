<?php

namespace Tests\Feature;

use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomeFooterResponsivePolishTest extends TestCase
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

    public function test_home_pathway_heading_and_level_guidance_have_inline_icons_without_blank_spacers(): void
    {
        $this->seed(CategorySeeder::class);
        $this->selectHomepageLayout('classic');

        $html = $this->get('/?lang=en')->assertOk()
            ->assertSee('id="home-cefr"', false)
            ->assertSee('data-home-levels', false)
            ->assertSee('href="'.route('categories.index').'"', false)
            ->assertSee('href="'.route('cefr.index').'"', false)
            ->getContent();

        $document = new \DOMDocument();
        @$document->loadHTML($html, LIBXML_NONET);
        $xpath = new \DOMXPath($document);

        $links = $xpath->query(
            '//*[@id="home-cefr"]//*[@data-home-levels]/a'
        );

        $this->assertCount(6, $links);

        foreach (['A1','A2','B1','B2','C1','C2'] as $i => $level) {
            $this->assertSame($level, trim($links->item($i)->textContent));
            $this->assertSame(
                route('cefr.index').'#'.$level,
                $links->item($i)->getAttribute('href')
            );
        }

        $this->assertSame(
            1,
            $xpath->query('//*[@id="home-cefr-title"]')->length
        );
    }

    public function test_four_learning_steps_show_icons_in_each_real_navigation_card(): void
    {
        $this->selectHomepageLayout('sunrise');

        $html = $this->get('/?lang=en')->assertOk()
            ->assertSee('data-home-layout="sunrise"', false)
            ->assertSee(trans('pages.how_it_works', [], 'en'))
            ->getContent();

        $document = new \DOMDocument();
        @$document->loadHTML($html, LIBXML_NONET);
        $xpath = new \DOMXPath($document);

        $steps = $xpath->query('//a[span[@class="sr-step"]]');
        $this->assertCount(4, $steps);

        $routes = ['courses.index', 'register', 'login', 'cefr.index'];

        $titles = [
            'pages.step_discover',
            'pages.step_account',
            'pages.step_approval',
            'pages.step_study',
        ];

        foreach ($routes as $index => $route) {
            $step = $steps->item($index);

            $this->assertSame(
                route($route), $step->getAttribute('href')
            );

            $this->assertStringContainsString(
                str_pad((string) ($index+1), 2, '0', STR_PAD_LEFT),
                $step->textContent
            );

            $this->assertStringContainsString(
                trans($titles[$index], [], 'en'),
                $step->textContent
            );
        }
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
