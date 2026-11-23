<?php

namespace Tests\Feature;

use App\Models\AppearanceRevision;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomeCoursePlayerHeroTest extends TestCase
{
    use RefreshDatabase;

    private function publishedCourse(User $admin): Course
    {
        $category = Category::create([
            'code' => 'GE',
            'slug' => 'general-english',
            'name' => 'General English',
            'group' => 'english-courses',
            'description' => 'English courses',
            'sort_order' => 1,
            'is_published' => true,
        ]);

        $course = Course::create([
            'category_id' => $category->id,
            'slug' => 'homepage-promotional-course',
            'title' => 'Homepage Promotional Course',
            'summary' => 'A reviewed educational course for public discovery.',
            'delivery_mode' => 'online',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'created_by' => $admin->id,
            'featured_kind' => 'color',
            'featured_value' => '#17395f',
        ]);
        $module = $course->modules()->create(['title' => 'Module One', 'position' => 1]);
        $module->lessons()->create([
            'title' => 'Preview Lesson', 'content' => 'Visible public preview.',
            'content_html' => '<p>Visible public preview.</p>',
            'duration_minutes' => 5, 'position' => 1, 'is_preview' => true,
        ]);
        $module->lessons()->create([
            'title' => 'Protected Lesson', 'content' => 'SECRET PRIVATE LESSON MATERIAL',
            'content_html' => '<p>SECRET PRIVATE LESSON MATERIAL</p>',
            'duration_minutes' => 5, 'position' => 2, 'is_preview' => false,
        ]);

        return $course;
    }

    public function test_hero_defaults_to_original_and_course_player_obeys_appearance_approval(): void
    {
        $author = User::factory()->create(['role' => 'admin']);
        $reviewer = User::factory()->create(['role' => 'admin']);
        $course = $this->publishedCourse($author);

        $this->get('/?lang=en')->assertOk()
            ->assertSee('Explore English learning for every ambition.')
            ->assertDontSee('data-home-course-hero', false);

        $this->actingAs($author)->get('/dashboard/appearance/home')->assertOk()
            ->assertSee('Hero Default (current)')
            ->assertSee('Course Player')
            ->assertSee('Homepage Promotional Course');

        $this->post('/dashboard/appearance/home', [
            'hero_title' => 'Existing hero remains available',
            'hero_intro' => 'A safe default introduction.',
            'hero_mode' => 'invalid',
            'hero_course_id' => $course->id,
        ])->assertSessionHasErrors('hero_mode');

        $this->post('/dashboard/appearance/home', [
            'hero_title' => 'Existing hero remains available',
            'hero_intro' => 'A safe default introduction.',
            'hero_mode' => 'course_player',
            'hero_course_id' => $course->id,
        ])->assertRedirect();

        $revision = AppearanceRevision::firstOrFail();
        $this->get('/?lang=en')->assertOk()->assertDontSee('data-home-course-hero', false);
        $this->post('/dashboard/appearance/revisions/'.$revision->id.'/submit')->assertRedirect();
        $this->actingAs($reviewer)->post('/dashboard/reviews/appearance/'.$revision->id, [
            'decision' => 'approve',
        ])->assertRedirect();

        $this->get('/?lang=en')->assertOk()
            ->assertSee('data-home-course-hero', false)
            ->assertSee('data-home-course-screen="'.$course->id.'"', false)
            ->assertSee('Homepage Promotional Course')
            ->assertSee('Module One')
            ->assertSee('Preview Lesson')
            ->assertSee('Protected Lesson')
            ->assertSee('Visible public preview.')
            ->assertDontSee('SECRET PRIVATE LESSON MATERIAL')
            ->assertDontSee('Explore English learning for every ambition.');
    }

    public function test_empty_course_catalog_falls_back_to_original_hero(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AppearanceRevision::create([
            'section' => 'home',
            'status' => 'approved',
            'created_by' => $admin->id,
            'settings' => [
                'hero_title' => 'Retained default hero',
                'hero_intro' => 'No courses are published.',
                'hero_mode' => 'course_player',
                'hero_course_id' => null,
            ],
        ]);

        $this->get('/?lang=km')->assertOk()
            ->assertSee('Retained default hero')
            ->assertDontSee('data-home-course-hero', false);
    }
}
