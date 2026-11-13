<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_report_counts_actual_activities_without_exposing_private_answers(): void
    {
        $this->seed(CategorySeeder::class);
        $category = Category::where('code', 'GE')->firstOrFail();
        $course = Course::create([
            'category_id' => $category->id,
            'slug' => 'report-course',
            'title' => 'Report Course',
            'summary' => 'Course acceptance records.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $module = $course->modules()->create(['title' => 'One module', 'position' => 1]);
        $lesson = $module->lessons()->create([
            'title' => 'Lesson', 'content' => 'Private text',
            'position' => 1, 'duration_minutes' => 15,
        ]);
        $student = User::factory()->create(['name' => 'Private Student']);
        $enrollment = CourseEnrollment::create([
            'course_id' => $course->id, 'student_id' => $student->id,
            'status' => 'approved',
        ]);
        $enrollment->lessonProgress()->create([
            'lesson_id' => $lesson->id,
            'completed_at' => now(),
        ]);
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->get('/dashboard/learning-report')
            ->assertOk()
            ->assertSee('Report Course')
            ->assertSee('Completed lesson records')
            ->assertDontSee('Private Student');

        $this->actingAs($student)->get('/dashboard/learning-report')->assertForbidden();
    }
}
