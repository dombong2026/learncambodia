<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseExam;
use App\Models\ExamType;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomeFeatureThemeConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_only_public_standalone_exams(): void
    {
        $this->seed(CategorySeeder::class);
        $course = Course::create([
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'slug' => 'exam-course',
            'title' => 'Assessment course',
            'summary' => 'Example course.',
        ]);
        $defaults = [
            'exam_type_id' => ExamType::create(['name' => 'Practice'])->id,
            'kind' => 'exam',
            'published' => true,
            'questions' => [['prompt' => 'Private test prompt']],
            'time_limit' => 45,
            'passing_percent' => 70,
            'attempt_limit' => 1,
        ];

        $exam = CourseExam::create(['title' => 'Public reading practice'] + $defaults);
        CourseExam::create(['title' => 'Draft practice assessment', 'published' => false] + $defaults);
        CourseExam::create(['title' => 'Published quiz fixture', 'kind' => 'quiz'] + $defaults);
        CourseExam::create(['title' => 'Course assessment fixture', 'course_id' => $course->id] + $defaults);
        CourseExam::create(['title' => 'Removed assessment fixture'] + $defaults)->delete();

        $this->get('/?lang=en')->assertOk()
            ->assertSee('id="practice-exams"', false)
            ->assertSee('href="'.route('public.exams.show', $exam).'"', false)
            ->assertSee('Public reading practice')
            ->assertSee('45 minutes')
            ->assertSee('images/home/exams.webp')
            ->assertDontSee('Private test prompt')
            ->assertDontSee('Draft practice assessment')
            ->assertDontSee('Published quiz fixture')
            ->assertDontSee('Course assessment fixture')
            ->assertDontSee('Removed assessment fixture');
    }

    public function test_visual_home_keeps_discovery_links_and_skips_empty_exam_section(): void
    {
        foreach (['en', 'km', 'zh'] as $lang) {
            $html = $this->get('/?lang='.$lang)->assertOk()
                ->assertSee('images/home/hero.webp')
                ->assertSee('href="'.route('categories.index').'"', false)
                ->assertSee('href="'.route('cefr.index').'#A1"', false)
                ->assertSee('href="'.route('cefr.index').'#C2"', false)
                ->assertSee('href="'.route('courses.index').'"', false)
                ->assertSee('href="'.route('assessments.exams').'"', false)
                ->assertSee('href="'.route('login').'"', false)
                ->assertDontSee('id="practice-exams"', false)
                ->getContent();

            $this->assertSame(1, substr_count($html, 'data-home-levels'));
        }
    }
}
