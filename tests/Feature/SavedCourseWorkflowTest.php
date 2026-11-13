<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseBookmark;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedCourseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function course(string $slug = 'saved-beginner-course'): Course
    {
        $this->seed(CategorySeeder::class);

        return Course::create([
            'slug' => $slug,
            'title' => 'Reviewed Beginner Course',
            'summary' => 'Approved study pathway.',
            'description' => 'Reviewed introductory material.',
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_only_authenticated_students_may_use_private_saved_courses(): void
    {
        $course = $this->course();
        $this->get('/account/saved-courses')->assertRedirect();
        $this->post('/account/saved-courses/'.$course->id)->assertRedirect();

        $administrator = User::factory()->create();
        $administrator->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($administrator)->get('/account/saved-courses')->assertForbidden();
        $this->post('/account/saved-courses/'.$course->id)->assertForbidden();
        $this->delete('/account/saved-courses/'.$course->id)->assertForbidden();
        $this->assertDatabaseCount('course_bookmarks', 0);
    }

    public function test_student_can_save_list_and_remove_a_published_course_without_enrolling(): void
    {
        $course = $this->course();
        $student = User::factory()->create();

        $this->actingAs($student)
            ->get('/account/courses')->assertOk()->assertSee('Save course');

        $this->post('/account/saved-courses/'.$course->id)
            ->assertRedirect(route('account.saved-courses.index'));
        $this->post('/account/saved-courses/'.$course->id)
            ->assertRedirect(route('account.saved-courses.index'));

        $this->assertDatabaseHas('course_bookmarks', [
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseCount('course_bookmarks', 1);
        $this->assertDatabaseCount('course_enrollments', 0);

        $this->get('/account/courses')->assertOk()->assertSee('Remove saved course');
        $this->get('/account/saved-courses')->assertOk()
            ->assertSee('Reviewed Beginner Course')
            ->assertSee(route('courses.show', ['course' => $course->slug]), false);

        $this->delete('/account/saved-courses/'.$course->id)
            ->assertRedirect(route('account.saved-courses.index'));
        $this->delete('/account/saved-courses/'.$course->id)
            ->assertRedirect(route('account.saved-courses.index'));
        $this->assertDatabaseCount('course_bookmarks', 0);
        $this->get('/account/saved-courses')->assertOk()->assertSee('no saved published courses');
    }

    public function test_students_cannot_view_or_remove_each_others_saved_items(): void
    {
        $course = $this->course();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($first)->post('/account/saved-courses/'.$course->id)->assertRedirect();

        $this->actingAs($second)->get('/account/saved-courses')
            ->assertOk()->assertDontSee('Reviewed Beginner Course');

        $this->delete('/account/saved-courses/'.$course->id)->assertRedirect();
        $this->assertDatabaseHas('course_bookmarks', [
            'student_id' => $first->id,
            'course_id' => $course->id,
        ]);

        $this->actingAs($first)->get('/account/saved-courses')
            ->assertOk()->assertSee('Reviewed Beginner Course');
    }

    public function test_unpublished_superseded_and_invisible_category_courses_are_rejected_or_hidden(): void
    {
        $course = $this->course();
        $student = User::factory()->create();
        $this->actingAs($student)->post('/account/saved-courses/'.$course->id)->assertRedirect();

        $course->update(['status' => 'draft', 'published_at' => null]);
        $this->get('/account/saved-courses')->assertOk()->assertDontSee('Reviewed Beginner Course');
        $this->post('/account/saved-courses/'.$course->id)->assertNotFound();

        $course->update(['status' => 'published', 'published_at' => now(), 'superseded_at' => now()]);
        $this->get('/account/saved-courses')->assertOk()->assertDontSee('Reviewed Beginner Course');
        $this->post('/account/saved-courses/'.$course->id)->assertNotFound();

        $course->update(['superseded_at' => null]);
        $course->category->update(['is_published' => false]);
        $this->get('/account/saved-courses')->assertOk()->assertDontSee('Reviewed Beginner Course');
        $this->post('/account/saved-courses/'.$course->id)->assertNotFound();

        $this->assertDatabaseCount('course_bookmarks', 1);
        $this->delete('/account/saved-courses/'.$course->id)->assertRedirect();
        $this->assertDatabaseCount('course_bookmarks', 0);
    }

    public function test_bookmark_relation_is_removed_when_user_is_deleted(): void
    {
        $course = $this->course();
        $student = User::factory()->create();
        CourseBookmark::create(['student_id' => $student->id, 'course_id' => $course->id]);

        $student->delete();

        $this->assertDatabaseCount('course_bookmarks', 0);
    }
}
