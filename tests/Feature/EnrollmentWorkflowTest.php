<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Support\CoursePricing;
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

    private function publishedCourse(): Course
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
        ]);
    }

    public function test_free_enrollment_opens_lessons_immediately_for_its_owner(): void
    {
        $course = $this->publishedCourse();
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
        $response = $this->actingAs($student)
            ->post('/account/courses/'.$course->id.'/enroll');

        $enrollment = CourseEnrollment::firstOrFail();
        $response->assertRedirect(route('account.enrollments.show', $enrollment));
        $this->assertSame('approved', $enrollment->status);
        $this->assertNull($enrollment->reviewed_by);

        $this->post('/account/courses/'.$course->id.'/enroll')->assertStatus(409);
        $this->actingAs($other)->get('/account/enrollments/'.$enrollment->id)->assertNotFound();
        $this->delete('/account/enrollments/'.$enrollment->id)->assertNotFound();

        $this->actingAs($student)->get('/account/enrollments/'.$enrollment->id)
            ->assertOk()->assertSee('Private lesson one')
            ->assertSee('Only approved students may read these sentences.');
        $this->assertDatabaseHas('approval_events', [
            'subject_type' => 'enrollment',
            'subject_id' => $enrollment->id,
            'action' => 'auto_approved',
            'actor_id' => $student->id,
            'to_status' => 'approved',
        ]);
    }

    public function test_student_can_cancel_pending_request_and_others_cannot_cancel_it(): void
    {
        $course = $this->publishedCourse();
        $course->update(['pricing' => ['model' => 'paid', 'currency' => 'USD', 'strategy' => 'fixed', 'base_minor' => 1500]]);
        $student = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($student)
            ->post('/account/courses/'.$course->id.'/enroll', [
                'pricing_quote' => json_encode(CoursePricing::quote($course->pricing), JSON_THROW_ON_ERROR),
            ])->assertRedirect(route('account.enrollments.index'));
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
        $course = $this->publishedCourse();
        $course->update(['pricing' => ['model' => 'paid', 'currency' => 'USD', 'strategy' => 'fixed', 'base_minor' => 1500]]);
        $student = User::factory()->create();
        $reviewer = $this->admin();

        $this->actingAs($student)
            ->post('/account/courses/'.$course->id.'/enroll', [
                'pricing_quote' => json_encode(CoursePricing::quote($course->pricing), JSON_THROW_ON_ERROR),
            ])->assertRedirect(route('account.enrollments.index'));
        $enrollment = CourseEnrollment::firstOrFail();
        $this->assertSame('pending', $enrollment->status);

        $this->actingAs($reviewer)->post('/dashboard/reviews/enrollments/'.$enrollment->id, [
            'decision' => 'decline',
            'note' => 'Capacity constraints for this course session.',
        ])->assertRedirect();

        $this->assertSame('declined', $enrollment->fresh()->status);
    }

    public function test_backfill_activates_only_eligible_legacy_free_enrollments_once(): void
    {
        $course = $this->publishedCourse();
        $student = User::factory()->create();
        $pending = ['course_id' => $course->id, 'status' => 'pending'];
        $free = CourseEnrollment::create($pending + ['student_id' => $student->id]);
        $paid = CourseEnrollment::create($pending + [
            'student_id' => User::factory()->create()->id,
            'pricing_quote' => ['model' => 'paid', 'currency' => 'USD', 'amount_minor' => 1500],
        ]);
        $hidden = $course->replicate();
        $hidden->forceFill(['slug' => 'legacy-unpublished-course', 'status' => 'draft', 'published_at' => null])->save();
        $unavailable = CourseEnrollment::create(array_replace($pending, [
            'course_id' => $hidden->id, 'student_id' => $student->id,
        ]));
        $formerStudent = CourseEnrollment::create($pending + ['student_id' => $this->admin()->id]);
        $declined = CourseEnrollment::create(array_replace($pending, [
            'status' => 'declined', 'student_id' => User::factory()->create()->id,
        ]));
        $explicitFree = $course->replicate();
        $explicitFree->forceFill(['slug' => 'explicit-free-course', 'pricing' => ['model' => 'free']])->save();
        $explicitFreeEnrollment = CourseEnrollment::create(array_replace($pending, [
            'course_id' => $explicitFree->id, 'student_id' => $student->id,
        ]));

        $this->artisan('learncambodia:activate-free-enrollments')
            ->expectsOutput('FREE_COURSE_ENROLLMENTS_ACTIVATED=2')->assertSuccessful();
        $this->artisan('learncambodia:activate-free-enrollments')
            ->expectsOutput('FREE_COURSE_ENROLLMENTS_ACTIVATED=0')->assertSuccessful();

        $this->assertSame('approved', $free->fresh()->status);
        $this->assertSame('approved', $explicitFreeEnrollment->fresh()->status);
        $this->assertSame('pending', $paid->fresh()->status);
        $this->assertSame('pending', $unavailable->fresh()->status);
        $this->assertSame('pending', $formerStudent->fresh()->status);
        $this->assertSame('declined', $declined->fresh()->status);
        $this->assertDatabaseCount('approval_events', 2);
        $this->actingAs($student)->get(route('account.enrollments.show', $free))->assertOk();
    }
}
