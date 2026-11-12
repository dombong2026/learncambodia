<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CefrLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneThreeScreenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $account = User::factory()->create();
        $account->forceFill(['role' => 'admin'])->save();

        return $account;
    }

    public function test_new_admin_builder_appearance_and_review_views_render(): void
    {
        $this->seed([CategorySeeder::class, CefrLevelSeeder::class]);
        $admin = $this->admin();
        $course = Course::create([
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'slug' => 'acceptance-course',
            'title' => 'Acceptance Course',
            'summary' => 'A new administrative acceptance course.',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        $module = $course->modules()->create([
            'title' => 'Module 1',
            'summary' => 'Sample curriculum module',
            'position' => 1,
        ]);
        $module->lessons()->create([
            'title' => 'Lesson 1', 'content' => 'Learning content.',
            'position' => 1, 'duration_minutes' => 10,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard/courses')->assertOk()->assertSee('Acceptance Course');
        $this->get('/dashboard/course-builder/create')->assertOk()
            ->assertSee('Create New Course');
        $this->get('/dashboard/course-builder/'.$course->id.'/edit')->assertOk()
            ->assertSee('Lesson 1');
        $this->get('/dashboard/appearance')->assertOk()->assertSee('Typography');
        $this->get('/dashboard/appearance/header')->assertOk()->assertSee('Search Placeholder');
        $this->get('/dashboard/reviews?focus=courses')->assertOk()
            ->assertSee('Review & Approvals');
        $this->get('/dashboard/reviews?focus=appearance')->assertOk();
        $this->get('/dashboard/reviews?focus=enrollments')->assertOk();
    }

    public function test_student_course_and_enrollment_views_render_without_billing(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get('/account/courses')->assertOk()
            ->assertSee('Available Courses');
        $this->get('/account/enrollments')->assertOk()->assertSee('My Enrollments');
        $this->get('/account/manage/assessments/placement')->assertOk()
            ->assertSee('Not yet activated');
    }

    public function test_appearance_draft_with_empty_optional_announcement_is_accepted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/dashboard/appearance/header', [
            'announcement' => '',
            'search_placeholder' => 'Search courses',
        ])->assertRedirect();

        $this->assertDatabaseHas('appearance_revisions', [
            'section' => 'header',
            'status' => 'draft',
        ]);
    }

    public function test_an_incomplete_course_cannot_be_submitted_for_publication(): void
    {
        $this->seed(CategorySeeder::class);
        $admin = $this->admin();
        $course = Course::create([
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'slug' => 'empty-draft',
            'title' => 'Empty Draft',
            'summary' => 'Unfinished educational material',
            'description' => 'Unfinished course description',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post('/dashboard/course-builder/'.$course->id.'/submit')->assertStatus(422);

        $this->assertSame('draft', $course->fresh()->status);
    }
}
