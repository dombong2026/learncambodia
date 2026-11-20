<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkCourseReviewTest extends TestCase
{
    use RefreshDatabase;

    private function pendingCourse(User $reviewer, string $title): Course
    {
        $course = Course::create([
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'created_by' => $reviewer->id,
            'delivery_mode' => 'online',
            'slug' => str($title)->slug(),
            'title' => $title,
            'summary' => 'Course with a complete submission.',
            'description' => 'Learn with a worked example.',
            'status' => 'draft',
        ]);
        $module = $course->modules()->create(['title' => 'Unit one', 'position' => 1]);
        $module->lessons()->create(['title' => 'Lesson one', 'content' => 'Original lesson.', 'position' => 1]);

        $this->actingAs($reviewer)
            ->post(route('admin.course-builder.submit', $course))
            ->assertRedirect();

        return $course->refresh();
    }

    public function test_bulk_approval_publishes_only_eligible_selected_courses_and_records_each_decision(): void
    {
        $this->seed(CategorySeeder::class);
        $reviewer = User::factory()->create(['role' => 'admin']);
        $first = $this->pendingCourse($reviewer, 'First selected course');
        $second = $this->pendingCourse($reviewer, 'Second selected course');
        $untouched = $this->pendingCourse($reviewer, 'Course left unchecked');
        $changed = $this->pendingCourse($reviewer, 'Changed course');
        $changed->lessons()->firstOrFail()->update(['content' => 'Edited after submission.']);

        $queue = route('admin.reviews.index', ['focus' => 'courses', 'lang' => 'en']);
        $action = route('admin.reviews.courses.approve-selected', ['lang' => 'en']);
        $this->get($queue)->assertOk()
            ->assertSee('Pending courses')
            ->assertSee('Select this page')
            ->assertSee('First selected course')
            ->assertSee($action, false);

        $selection = [$first->public_id, $changed->public_id, $second->public_id];
        $this->post($action, ['courses' => $selection])
            ->assertRedirect($queue)
            ->assertSessionHas('course_review_batch.published', 2)
            ->assertSessionHas('course_review_batch.skipped.0.course', $changed->title);

        foreach ([$first, $second] as $course) {
            $this->assertSame('published', $course->fresh()->status);
            $this->assertDatabaseHas('course_review_snapshots', [
                'course_id' => $course->id, 'decision' => 'approve', 'reviewed_by' => $reviewer->id,
            ]);
            $this->assertDatabaseHas('approval_events', [
                'subject_type' => 'course', 'subject_id' => $course->id,
                'actor_id' => $reviewer->id, 'action' => 'approve', 'to_status' => 'published',
            ]);
        }

        $this->assertSame('pending_review', $untouched->fresh()->status);
        $this->assertSame('pending_review', $changed->fresh()->status);
        $this->get($queue)->assertSee('2 courses published.')->assertSee('1 skipped.');

        $this->post($action, ['courses' => $selection])
            ->assertSessionHas('course_review_batch.published', 0);
        $this->assertDatabaseCount('approval_events', 6);
    }

    public function test_bulk_approval_rejects_invalid_selections_and_disallows_students(): void
    {
        $this->seed(CategorySeeder::class);
        $reviewer = User::factory()->create(['role' => 'admin']);
        $course = $this->pendingCourse($reviewer, 'Still pending');
        $action = route('admin.reviews.courses.approve-selected');

        $this->post($action, ['courses' => []])->assertSessionHasErrors('courses');
        $this->post($action, ['courses' => [$course->public_id, $course->public_id]])
            ->assertSessionHasErrors(['courses.0', 'courses.1']);
        $this->post($action, ['courses' => range(900000, 900010)])->assertSessionHasErrors('courses');
        $this->actingAs(User::factory()->create())
            ->post($action, ['courses' => [$course->public_id]])->assertForbidden();

        $this->assertSame('pending_review', $course->fresh()->status);
    }
}
