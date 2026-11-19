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

    public function test_cefr_row_and_learning_guides_follow_course_results(): void
    {
        $this->get('/courses?lang=en')->assertOk()
            ->assertSeeInOrder([
                'data-course-filters',
                'id="course-results"',
                'data-course-cefr-section',
                'id="course-instructors"',
                'id="course-faq"',
                'id="course-process"',
            ], false)
            ->assertSee('snap-x snap-mandatory', false)
            ->assertSee('name="level"', false)
            ->assertSee(route('cefr.index'));
    }
}
