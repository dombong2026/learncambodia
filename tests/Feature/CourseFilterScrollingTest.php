<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CourseFilterScrollingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_renders_nonsticky_filters_with_working_form(): void
    {
        $html = $this->get('/courses?lang=en')->assertOk()
            ->assertSee('data-course-filters', false)
            ->assertSee('name="group"', false)
            ->assertSee('name="category"', false)
            ->assertSee('name="level"', false)
            ->assertSee('name="mode"', false)
            ->assertSee('name="q"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-course-filters'));
        $template = file_get_contents(resource_path('views/courses/index.blade.php'));
        $this->assertIsString($template);
        $this->assertMatchesRegularExpression('/<aside\s+data-course-filters\s+class="self-start[^"]*"/', $template);
        $this->assertStringNotContainsString('lg:sticky', $template);
        $this->assertStringNotContainsString('lg:top-32', $template);
        $this->assertStringContainsString('action="{{ route(\'courses.index\') }}"', $template);
        $this->assertStringContainsString("{{ __('frontend.apply') }}", $template);
    }

    public function test_public_filter_asides_do_not_pin_over_following_sections(): void
    {
        foreach ([
            'courses/index.blade.php',
            'categories/index.blade.php',
            'articles/index.blade.php',
            'people/index.blade.php',
            'authors/index.blade.php',
            'learning/exams.blade.php',
        ] as $view) {
            $template = file_get_contents(resource_path('views/'.$view));
            $this->assertIsString($template);
            preg_match_all('/<aside\b[^>]*>/s', $template, $asides);
            foreach ($asides[0] as $aside) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\b(?:sticky|fixed|(?:sm|md|lg|xl|2xl):sticky|(?:sm|md|lg|xl|2xl):fixed)\b/',
                    $aside,
                    'A public aside should remain in normal document flow: '.$view
                );
            }
        }
    }
}
