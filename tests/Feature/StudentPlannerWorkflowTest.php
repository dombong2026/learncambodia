<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\StudyGoal;
use App\Models\StudyTask;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPlannerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function enrollment(User $student): CourseEnrollment
    {
        if (Category::where('code', 'GE')->doesntExist()) $this->seed(CategorySeeder::class);
        $course = Course::create([
            'slug' => 'planner-'.strtolower(bin2hex(random_bytes(3))),
            'title' => 'Planner course', 'summary' => 'Reviewed',
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'status' => 'published', 'published_at' => now(),
        ]);
        return CourseEnrollment::create(['course_id' => $course->id, 'student_id' => $student->id, 'status' => 'approved']);
    }

    public function test_student_can_plan_weekly_sessions_goals_tasks_and_completion(): void
    {
        $student = User::factory()->create();
        $enrollment = $this->enrollment($student);
        $this->actingAs($student)->post('/account/planner/goals', [
            'enrollment_id' => $enrollment->id, 'title' => 'Read two units', 'target_date' => '2027-02-15',
        ])->assertRedirect();
        $goal = StudyGoal::firstOrFail();
        $this->post('/account/planner/goals/'.$goal->id.'/tasks', ['title' => 'Finish reading'])->assertRedirect();
        $task = StudyTask::firstOrFail();
        $this->patch('/account/planner/tasks/'.$task->id, ['complete' => '1'])->assertRedirect();
        $this->patch('/account/planner/goals/'.$goal->id, [
            'title' => 'Read three units', 'target_date' => '2027-02-16', 'complete' => '1',
        ])->assertRedirect();
        $this->post('/account/planner/sessions', [
            'enrollment_id' => $enrollment->id, 'title' => 'Reading circle',
            'weekday' => 1, 'start_time' => '18:00', 'duration_minutes' => 30,
        ])->assertRedirect();
        $this->get('/account/planner')->assertOk()->assertSee('Reading circle')
            ->assertSee('1 of 1 tasks completed')->assertSee('Read three units');
        $this->assertNotNull($goal->fresh()->completed_at);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_other_students_and_unapproved_enrollments_cannot_change_any_plan(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $enrollment = $this->enrollment($owner);
        $this->actingAs($owner)->post('/account/planner/goals', [
            'enrollment_id' => $enrollment->id, 'title' => 'Private task goal',
        ])->assertRedirect();
        $goal = StudyGoal::firstOrFail();
        $this->post('/account/planner/goals/'.$goal->id.'/tasks', ['title'=>'Private task'])->assertRedirect();
        $task = StudyTask::firstOrFail();

        $this->actingAs($other)->get('/account/planner')->assertOk()->assertDontSee('Private task goal');
        $this->post('/account/planner/goals', [
            'enrollment_id' => $enrollment->id, 'title' => 'Intrusion',
        ])->assertNotFound();
        $this->patch('/account/planner/goals/'.$goal->id, ['title' => 'Changed', 'complete' => 1])->assertNotFound();
        $this->patch('/account/planner/tasks/'.$task->id, ['complete' => 1])->assertNotFound();
        $this->delete('/account/planner/goals/'.$goal->id)->assertNotFound();
        $this->assertNull($task->fresh()->completed_at);

        $enrollment->update(['status' => 'cancelled']);
        $this->actingAs($owner)->get('/account/planner')->assertOk()->assertDontSee('Private task goal');
        $this->post('/account/planner/goals/'.$goal->id.'/tasks', ['title' => 'No longer enrolled'])->assertStatus(403);
    }

    public function test_role_boundaries_and_validation_keep_study_data_private(): void
    {
        $student = User::factory()->create();
        $enrollment = $this->enrollment($student);
        $this->get('/account/planner')->assertRedirect();
        $this->actingAs($student)->post('/account/planner/sessions', [
            'enrollment_id' => $enrollment->id, 'title' => 'Invalid',
            'weekday' => 9, 'start_time' => '18:00', 'duration_minutes' => 30,
        ])->assertSessionHasErrors('weekday');
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->get('/account/planner')->assertForbidden();
    }
}
