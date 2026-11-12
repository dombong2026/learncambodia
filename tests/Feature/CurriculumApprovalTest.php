<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CefrLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function buildCourse(User $creator): Course
    {
        $category = Category::where('code', 'GE')->firstOrFail();

        $this->actingAs($creator)->post('/dashboard/course-builder', [
            'title' => 'Practical English A1',
            'summary' => 'An independently reviewed foundational course.',
            'description' => 'Practical communicative course for beginner English learners.',
            'category_id' => $category->id,
            'cefr_level' => 'A1',
            'delivery_mode' => 'online',
        ])->assertRedirect();

        $course = Course::where('slug', 'practical-english-a1')->firstOrFail();

        $this->post('/dashboard/course-builder/'.$course->id.'/modules', [
            'title' => 'Introductions', 'summary' => 'Introducing yourself.', 'position' => 1,
        ])->assertRedirect();
        $module = $course->modules()->firstOrFail();

        $this->post('/dashboard/course-builder/'.$course->id.'/modules/'.$module->id.'/lessons', [
            'title' => 'Say hello',
            'content' => 'Hello. My name is Sam.',
            'position' => 1,
            'duration_minutes' => 10,
            'is_preview' => '0',
        ])->assertRedirect();

        return $course->fresh();
    }

    public function test_course_curriculum_requires_independent_review_before_publication(): void
    {
        $this->seed([CategorySeeder::class, CefrLevelSeeder::class]);
        $author = $this->admin();
        $reviewer = $this->admin();
        $course = $this->buildCourse($author);

        $this->assertSame('draft', $course->status);
        $this->assertDatabaseCount('course_modules', 1);
        $this->assertDatabaseCount('course_lessons', 1);
        $this->get('/categories')->assertDontSee('Practical English A1');

        $this->post('/dashboard/course-builder/'.$course->id.'/submit')->assertRedirect();
        $this->assertSame('pending_review', $course->fresh()->status);
        $this->get('/account/courses')->assertForbidden();
        $this->post('/dashboard/reviews/courses/'.$course->id, [
            'decision' => 'approve',
        ])->assertForbidden();

        $this->actingAs($reviewer)
            ->post('/dashboard/reviews/courses/'.$course->id, ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertSame('published', $course->fresh()->status);
        $this->assertNotNull($course->fresh()->published_at);
        $this->get('/categories/'. $course->category->slug)->assertSee('Practical English A1');
        $this->assertDatabaseHas('approval_events', [
            'subject_type' => 'course',
            'subject_id' => $course->id,
            'action' => 'approve',
            'to_status' => 'published',
            'actor_id' => $reviewer->id,
        ]);
    }

    public function test_pending_or_published_curriculum_is_immutable(): void
    {
        $this->seed([CategorySeeder::class, CefrLevelSeeder::class]);
        $author = $this->admin();
        $course = $this->buildCourse($author);
        $module = $course->modules()->firstOrFail();

        $this->post('/dashboard/course-builder/'.$course->id.'/submit')->assertRedirect();

        $this->post('/dashboard/course-builder/'.$course->id.'/modules', [
            'title' => 'Unauthorized new module', 'position' => 4,
        ])->assertStatus(409);

        $this->delete('/dashboard/course-builder/'.$course->id.'/modules/'.$module->id)
            ->assertStatus(409);

        $this->assertDatabaseCount('course_modules', 1);
    }

    public function test_cross_course_module_reference_is_rejected(): void
    {
        $this->seed([CategorySeeder::class, CefrLevelSeeder::class]);
        $author = $this->admin();
        $course = $this->buildCourse($author);
        $second = Course::create([
            'slug' => 'separate-course',
            'title' => 'Separate Course',
            'summary' => 'Another draft course',
            'category_id' => $course->category_id,
            'created_by' => $author->id,
            'status' => 'draft',
        ]);
        $module = $course->modules()->firstOrFail();

        $this->post('/dashboard/course-builder/'.$second->id.'/modules/'.$module->id.'/lessons', [
            'title' => 'Wrong course',
            'content' => 'This must not be inserted.',
            'position' => 1, 'duration_minutes' => 10, 'is_preview' => '0',
        ])->assertNotFound();
    }

    public function test_student_cannot_access_administrator_curriculum_routes(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/dashboard/course-builder', [
                'title' => 'Unauthorized course',
            ])->assertForbidden();

        $this->assertDatabaseCount('courses', 0);
    }
}
