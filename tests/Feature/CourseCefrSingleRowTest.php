<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CourseCefrSingleRowTest extends TestCase
{
    use RefreshDatabase;

    public function test_six_cefr_course_links_share_a_single_nonwrapping_row(): void
    {
        foreach (['en', 'km', 'zh'] as $lang) {
            $html = $this->get('/courses?lang='.$lang)->assertOk()
                ->assertSee('data-course-cefr-row', false)
                ->assertSee('flex-nowrap', false)
                ->assertSee('overflow-x-auto', false)
                ->assertSee('premium-card', false)
                ->getContent();
            $this->assertSame(1, substr_count($html, 'data-course-cefr-row'));
            foreach (['A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as $level) {
                $this->assertSame(1, substr_count($html, 'data-course-cefr-level="'.$level.'"'));
                $this->assertStringContainsString(
                    'href="'.route('courses.index', ['level' => $level]).'"',
                    $html
                );
            }
        }
    }

    public function test_cefr_card_presentation_preserves_learning_levels_and_filter_controls(): void
    {
        $view = file_get_contents(resource_path('views/courses/index.blade.php'));
        $this->assertIsString($view);
        $this->assertStringContainsString('data-course-cefr-row', $view);
        $this->assertStringContainsString('data-course-cefr-section', $view);
        $this->assertStringContainsString('class="col-span-full min-w-0 rounded-3xl', $view);
        $this->assertStringContainsString('snap-x snap-mandatory', $view);
        $this->assertStringContainsString('bg-white/90', $view);
        $this->assertStringContainsString('dark:bg-white/10', $view);
        $this->assertLessThan(
            strpos($view, 'data-course-filters'),
            strpos($view, 'data-course-cefr-section'),
            'The learning-level cards should span the catalog above its narrow sidebars.'
        );
        $this->assertStringContainsString('class="min-w-0 space-y-5 lg:col-span-2 xl:col-span-1"', $view);
        $this->assertStringContainsString('flex w-full min-w-0 max-w-full flex-nowrap gap-3 overflow-x-auto', $view);
        $this->assertStringNotContainsString('mt-4 grid grid-cols-3 gap-2', $view);
        $this->assertStringContainsString('data-course-filters', $view);
        $this->assertStringContainsString("name=\"level\"", $view);
        $this->assertStringContainsString('route(\'cefr.index\')', $view);
        $this->assertStringContainsString('route(\'courses.index\',[\'level\'=>$lvl])', $view);
    }
}
