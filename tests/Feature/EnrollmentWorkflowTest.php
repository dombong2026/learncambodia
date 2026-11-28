<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();
        return $user;
    }

    private function publishedCourse(bool $paid = false): Course
    {
        $this->seed(CategorySeeder::class);

        return Course::create([
            'slug' => 'published-beginner-english',
            'title' => 'Published Beginner English',
            'summary' => 'Practical beginner lessons.',
            'description' => 'An accepted beginner curriculum.',
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'status' => 'published',
            'published_at' => now(),
            'pricing' => $paid ? [
                'model' => 'paid',
                'strategy' => 'fixed',
                'currency' => 'USD',
                'base_minor' => 1000,
            ] : null,
        ]);
    }

    public function test_enrollment_requires_admin_approval_before_students_can_open_lessons(): void
    {
        $course = $this->publishedCourse(true);
        $module = $course->modules()->create([
            'title' => 'Unit one', 'position' => 1,
        ]);
        $module->lessons()->create([
            'title' => 'Private lesson one',
            'content' => 'Only approved students may read these sentences.',
            'position' => 1, 'duration_minutes' => 15,
        ]);

        $student = User::factory()->create();
        $other = User::factory()->create();
        $reviewer = $this->admin();

        $this->actingAs($student)
            ->post('/account/courses/'.$course->id.'/enroll', [
                'pricing_quote' => json_encode(
                    \App\Support\CoursePricing::quote($course->pricing),
                    JSON_THROW_ON_ERROR
                ),
            ])->assertRedirect();

        $enrollment = CourseEnrollment::firstOrFail();
        $this->assertSame('pending', $enrollment->status);
        $this->get('/account/enrollments/'.$enrollment->id)->assertForbidden();

        $this->post('/account/courses/'.$course->id.'/enroll', [
            'pricing_quote' => json_encode(
                \App\Support\CoursePricing::quote($course->pricing),
                JSON_THROW_ON_ERROR
            ),
        ])->assertStatus(409);
        $this->actingAs($other)->get('/account/enrollments/'.$enrollment->id)->assertNotFound();
        $this->delete('/account/enrollments/'.$enrollment->id)->assertNotFound();

        $this->actingAs($reviewer)
            ->post('/dashboard/reviews/enrollments/'.$enrollment->id, [
                'decision' => 'approve',
                'pricing_reference' => 'QA-PAYMENT-001',
            ])->assertRedirect();

        $this->assertSame('approved', $enrollment->fresh()->status);
        $this->actingAs($student)->get('/account/enrollments/'.$enrollment->id)
            ->assertRedirect();
        $this->get(route('account.lessons.watch', [
            $enrollment, $module->lessons()->sole()
        ]))->assertOk()->assertSee('Private lesson one')
            ->assertSee('Only approved students may read these sentences.');
        $this->assertDatabaseHas('approval_events', [
            'subject_type' => 'enrollment',
            'subject_id' => $enrollment->id,
            'action' => 'approve',
            'actor_id' => $reviewer->id,
        ]);
    }

    public function test_student_can_cancel_pending_request_and_others_cannot_cancel_it(): void
    {
        $course = $this->publishedCourse(true);
        $student = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($student)
            ->post('/account/courses/'.$course->id.'/enroll', [
                'pricing_quote' => json_encode(
                    \App\Support\CoursePricing::quote($course->pricing),
                    JSON_THROW_ON_ERROR
                ),
            ])->assertRedirect();
        $enrollment = CourseEnrollment::firstOrFail();
        $this->assertSame('pending', $enrollment->status);

        $this->actingAs($other)
            ->delete('/account/enrollments/'.$enrollment->id)->assertNotFound();

        $this->actingAs($student)
            ->delete('/account/enrollments/'.$enrollment->id)->assertRedirect();

        $this->assertSame('cancelled', $enrollment->fresh()->status);
    }

    public function test_unpublished_courses_cannot_accept_enrollments_or_be_read(): void
    {
        $course = $this->publishedCourse();
        $course->update(['status' => 'draft', 'published_at' => null]);
        $student = User::factory()->create();

        $this->actingAs($student)
            ->post('/account/courses/'.$course->id.'/enroll')->assertNotFound();
        $this->assertDatabaseCount('course_enrollments', 0);
    }

    public function test_developer_cannot_submit_student_enrollments(): void
    {
        $course = $this->publishedCourse();
        $developer = User::factory()->create();
        $developer->forceFill(['role' => 'developer'])->save();

        $this->actingAs($developer)
            ->post('/account/courses/'.$course->id.'/enroll')->assertForbidden();
    }

    public function test_administrator_can_decline_and_record_reason(): void
    {
        $course = $this->publishedCourse(true);
        $student = User::factory()->create();
        $reviewer = $this->admin();

        $this->actingAs($student)
            ->post('/account/courses/'.$course->id.'/enroll', [
                'pricing_quote' => json_encode(
                    \App\Support\CoursePricing::quote($course->pricing),
                    JSON_THROW_ON_ERROR
                ),
            ])->assertRedirect();
        $enrollment = CourseEnrollment::firstOrFail();

        $this->actingAs($reviewer)->post('/dashboard/reviews/enrollments/'.$enrollment->id, [
            'decision' => 'decline',
            'note' => 'Capacity constraints for this course session.',
        ])->assertRedirect();

        $this->assertSame('declined', $enrollment->fresh()->status);
    }

    public function test_free_course_enrollment_is_automatically_approved(): void
    {
        $course = $this->publishedCourse();
        $student = User::factory()->create();

        $this->actingAs($student)
            ->post('/account/courses/'.$course->id.'/enroll')
            ->assertRedirect();

        $enrollment = CourseEnrollment::where(
            'student_id', $student->id
        )->where('course_id', $course->id)->sole();

        $this->assertSame('approved', $enrollment->status);
        $this->assertNull($enrollment->pricing_quote);

        $this->assertDatabaseHas('approval_events', [
            'subject_type' => 'enrollment',
            'subject_id' => $enrollment->id,
            'action' => 'auto_approved',
            'actor_id' => $student->id,
        ]);

        $this->post('/account/courses/'.$course->id.'/enroll')
            ->assertStatus(409);
    }

}
