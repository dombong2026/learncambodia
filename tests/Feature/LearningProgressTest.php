<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LessonProgress;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProgressTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledCourse(): array
    {
        $this->seed(CategorySeeder::class);
        $course = Course::create([
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'title' => 'Learning Progress Study',
            'slug' => 'learning-progress-study',
            'summary' => 'A published course with two lessons.',
            'description' => 'For isolated progress acceptance.',
            'status' => 'published', 'published_at' => now(),
        ]);
        $module = $course->modules()->create(['title' => 'Module 1', 'position' => 1]);
        $first = $module->lessons()->create([
            'title' => 'First lesson', 'content' => 'Read this lesson.',
            'position' => 1, 'duration_minutes' => 10,
        ]);
        $second = $module->lessons()->create([
            'title' => 'Second lesson', 'content' => 'Next lesson.',
            'position' => 2, 'duration_minutes' => 15,
        ]);
        $student = User::factory()->create();
        $enrollment = CourseEnrollment::create([
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => 'approved',
        ]);

        return compact('course', 'student', 'enrollment', 'first', 'second');
    }

    public function test_student_can_mark_owned_lesson_complete_and_undo(): void
    {
        ['student' => $student, 'enrollment' => $enrollment, 'first' => $first] =
            $this->enrolledCourse();

        $this->actingAs($student)->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$first->id.'/progress', [
            'complete' => '1',
        ])->assertRedirect();

        $this->assertNotNull(LessonProgress::firstOrFail()->completed_at);
        $this->get('/account/progress')->assertOk()->assertSee('1 of 2 lessons');
        $this->get('/account/enrollments/'.$enrollment->id)
            ->assertOk()->assertSee('Completed · Mark for review');

        $this->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$first->id.'/progress', [
            'complete' => '1',
        ])->assertRedirect();
        $this->assertDatabaseCount('lesson_progress', 1);

        $this->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$first->id.'/progress', [
            'complete' => '0',
        ])->assertRedirect();
        $this->assertNull(LessonProgress::firstOrFail()->completed_at);
        $this->get('/account/progress')->assertSee('0 of 2 lessons');
    }

    public function test_other_students_cannot_change_progress_or_read_private_enrollments(): void
    {
        ['enrollment' => $enrollment, 'first' => $first] = $this->enrolledCourse();
        $other = User::factory()->create();

        $this->actingAs($other)->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$first->id.'/progress', [
            'complete' => 1,
        ])->assertNotFound();
        $this->get('/account/enrollments/'.$enrollment->id)->assertNotFound();
        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_progress_rejects_lessons_from_another_course(): void
    {
        ['course' => $course, 'student' => $student, 'enrollment' => $enrollment] =
            $this->enrolledCourse();

        $other = Course::create([
            'category_id' => $course->category_id, 'title' => 'Other Curriculum',
            'slug' => 'other-curriculum', 'summary' => 'Different course',
            'status' => 'published', 'published_at' => now(),
        ]);
        $module = $other->modules()->create(['title' => 'Other module', 'position' => 1]);
        $foreignLesson = $module->lessons()->create([
            'title' => 'Other lesson', 'content' => 'Private',
            'position' => 1, 'duration_minutes' => 10,
        ]);

        $this->actingAs($student)->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$foreignLesson->id.'/progress', [
            'complete' => true,
        ])->assertNotFound();
        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_revoked_enrollment_and_unpublished_course_block_progress(): void
    {
        ['student' => $student, 'enrollment' => $enrollment, 'first' => $first,
            'course' => $course] = $this->enrolledCourse();

        $enrollment->update(['status' => 'cancelled']);
        $this->actingAs($student)->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$first->id.'/progress', [
            'complete' => 1,
        ])->assertForbidden();

        $enrollment->update(['status' => 'approved']);
        $course->update(['status' => 'draft', 'published_at' => null]);

        $this->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$first->id.'/progress', [
            'complete' => 1,
        ])->assertNotFound();

        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_invalid_completion_input_is_rejected(): void
    {
        ['student' => $student, 'enrollment' => $enrollment, 'first' => $first] =
            $this->enrolledCourse();

        $this->actingAs($student)->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$first->id.'/progress', [
            'complete' => 'administrator',
        ])->assertSessionHasErrors('complete');
        $this->assertDatabaseCount('lesson_progress', 0);
    }
}
