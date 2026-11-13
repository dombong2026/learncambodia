<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseRevisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();

        return $user;
    }

    private function publishedCourse(): Course
    {
        $this->seed(CategorySeeder::class);
        $course = Course::create([
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'title' => 'Professional English',
            'slug' => 'professional-english',
            'summary' => 'Learn practical English.',
            'description' => 'A complete series of English lessons.',
            'status' => 'published', 'published_at' => now(),
        ]);
        $module = $course->modules()->create([
            'title' => 'Module One', 'summary' => 'Introductions', 'position' => 1,
        ]);
        $module->lessons()->create([
            'title' => 'Learn greetings',
            'content' => 'Hello and welcome to this course.',
            'position' => 1, 'duration_minutes' => 10,
        ]);
        $quiz = $course->quizzes()->create([
            'title' => 'Greetings review',
            'passing_percent' => 75, 'position' => 1,
        ]);
        $quiz->questions()->create([
            'prompt' => 'Which word is a greeting?',
            'option_a' => 'Hello',
            'option_b' => 'Tree',
            'option_c' => 'Road',
            'option_d' => 'Desk',
            'correct_option' => 'A',
            'explanation' => 'Hello is used as a greeting.',
            'position' => 1,
        ]);

        return $course->fresh();
    }

    public function test_a_new_edition_copies_curriculum_but_not_enrollments_or_results(): void
    {
        $original = $this->publishedCourse();
        $student = User::factory()->create();
        $oldEnrollment = CourseEnrollment::create([
            'course_id' => $original->id,
            'student_id' => $student->id,
            'status' => 'approved',
        ]);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->post('/dashboard/course-builder/'.$original->id.'/revise')
            ->assertRedirect();

        $edition = Course::where('source_course_id', $original->id)->firstOrFail();
        $this->assertSame(2, $edition->revision_number);
        $this->assertSame('draft', $edition->status);
        $this->assertNull($edition->published_at);
        $this->assertSame($admin->id, $edition->created_by);
        $this->assertSame('professional-english-v2', $edition->slug);
        $this->assertDatabaseCount('course_modules', 2);
        $this->assertDatabaseCount('course_lessons', 2);
        $this->assertDatabaseCount('course_quizzes', 2);
        $this->assertDatabaseCount('course_quiz_questions', 2);
        $this->assertSame(0, $edition->enrollments()->count());
        $this->assertDatabaseCount('course_enrollments', 1);
        $this->assertSame($original->id, $oldEnrollment->course_id);
        $this->assertNull($original->fresh()->superseded_at);

        $this->post('/dashboard/course-builder/'.$original->id.'/revise')
            ->assertStatus(409);
        $this->assertDatabaseCount('courses', 2);
    }

    public function test_independent_review_publishes_revision_without_breaking_existing_students(): void
    {
        $original = $this->publishedCourse();
        $student = User::factory()->create();
        $originalEnrollment = CourseEnrollment::create([
            'course_id' => $original->id,
            'student_id' => $student->id,
            'status' => 'approved',
        ]);
        $author = $this->admin();
        $reviewer = $this->admin();

        $this->actingAs($author)
            ->post('/dashboard/course-builder/'.$original->id.'/revise')
            ->assertRedirect();

        $edition = Course::where('source_course_id', $original->id)->firstOrFail();
        $this->post('/dashboard/course-builder/'.$edition->id.'/submit')
            ->assertRedirect();

        $this->post('/dashboard/reviews/courses/'.$edition->id, [
            'decision' => 'approve',
        ])->assertForbidden();

        $this->actingAs($reviewer)->post('/dashboard/reviews/courses/'.$edition->id, [
            'decision' => 'approve',
        ])->assertRedirect();

        $this->assertSame('published', $edition->fresh()->status);
        $this->assertNotNull($original->fresh()->superseded_at);

        $visible = Course::discoverable()->pluck('id')->all();
        $this->assertContains($edition->id, $visible);
        $this->assertNotContains($original->id, $visible);
        $this->assertSame('published', $original->fresh()->status);
        $this->assertSame(1, $originalEnrollment->fresh()->course_id === $original->id ? 1 : 0);

        $this->actingAs($student)
            ->get('/account/enrollments/'.$originalEnrollment->id)
            ->assertOk()->assertSee('Learn greetings');

        $this->post('/account/courses/'.$original->id.'/enroll')
            ->assertNotFound();

        $this->get('/account/courses')->assertOk()
            ->assertSee('Professional English');
    }

    public function test_rejected_edition_keeps_existing_course_discoverable(): void
    {
        $original = $this->publishedCourse();
        $author = $this->admin();
        $reviewer = $this->admin();

        $this->actingAs($author)
            ->post('/dashboard/course-builder/'.$original->id.'/revise')->assertRedirect();
        $edition = Course::where('source_course_id', $original->id)->firstOrFail();
        $this->post('/dashboard/course-builder/'.$edition->id.'/submit')->assertRedirect();

        $this->actingAs($reviewer)
            ->post('/dashboard/reviews/courses/'.$edition->id, [
                'decision' => 'reject',
                'note' => 'The new edition requires an additional academic review.',
            ])->assertRedirect();

        $this->assertSame('rejected', $edition->fresh()->status);
        $this->assertNull($original->fresh()->superseded_at);
        $this->assertTrue(Course::discoverable()->whereKey($original->id)->exists());
        $this->assertFalse(Course::discoverable()->whereKey($edition->id)->exists());
    }

    public function test_students_cannot_create_course_editions(): void
    {
        $original = $this->publishedCourse();

        $this->actingAs(User::factory()->create())
            ->post('/dashboard/course-builder/'.$original->id.'/revise')
            ->assertForbidden();

        $this->assertDatabaseCount('courses', 1);
    }

    public function test_draft_courses_cannot_spawn_revisions(): void
    {
        $original = $this->publishedCourse();
        $original->update(['status' => 'draft', 'published_at' => null]);

        $this->actingAs($this->admin())
            ->post('/dashboard/course-builder/'.$original->id.'/revise')
            ->assertStatus(409);

        $this->assertDatabaseCount('courses', 1);
    }
}
