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

    public function test_course_watch_resumes_the_next_unfinished_lesson_and_shows_progress(): void
    {
        ['student' => $student, 'enrollment' => $enrollment, 'first' => $first, 'second' => $second] =
            $this->enrolledCourse();

        $watch = route('learning.watch', ['lang' => 'en']);
        $firstUrl = route('account.lessons.watch', [$enrollment, $first]);
        $secondUrl = route('account.lessons.watch', [$enrollment, $second]);

        $this->get($watch)->assertOk()->assertSee('data-course-watch', false)
            ->assertDontSee($firstUrl, false)->assertDontSee($first->content);
        $this->actingAs($student)->get($watch)->assertOk()->assertSee($firstUrl, false)->assertSee('Start course');

        $this->from($firstUrl)->post(route('account.progress.update', [$enrollment, $first]), [
            'complete' => '1',
        ])->assertRedirect($firstUrl);

        $this->get($watch)->assertOk()->assertSee($secondUrl, false)
            ->assertSee('Resume lesson')->assertSee('1 of 2 lessons completed');

        $this->get($secondUrl)->assertOk()->assertSee('data-course-lesson', false)
            ->assertSee('Lesson 2 of 2')->assertSee('1 of 2 lessons completed')
            ->assertSee('aria-current="page"', false)->assertSee($second->content);
        $this->from($secondUrl)->post(route('account.notes.store', [$enrollment, $second]), [
            'title' => 'My study note', 'body' => 'Review the example tomorrow.', 'is_pinned' => '0',
        ])->assertRedirect($secondUrl);
        $this->get($secondUrl)->assertOk()->assertSee('Review the example tomorrow.');

        $this->from($secondUrl)->post(route('account.progress.update', [$enrollment, $second]), [
            'complete' => '1',
        ])->assertRedirect($secondUrl);
        $this->get($watch)->assertOk()->assertSee('Course complete')
            ->assertSee(route('account.enrollments.show', $enrollment), false);

        $this->actingAs(User::factory()->create())->get($secondUrl)->assertNotFound();
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
