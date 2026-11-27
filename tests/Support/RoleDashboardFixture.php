<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseHomeworkAssignment;
use App\Models\CourseInstructorAssignment;
use App\Models\HomeworkSubmission;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Hash;

final class RoleDashboardFixture
{
    /** Only called by disposable acceptance environments. */
    public static function create(): array
    {
        if (! app()->environment('testing')) {
            throw new \RuntimeException('Role dashboard fixtures require the testing environment.');
        }

        if (Category::where('code', 'GE')->doesntExist()) {
            app(CategorySeeder::class)->run();
        }

        $accounts = [];
        foreach (['student', 'instructor', 'developer', 'admin'] as $role) {
            $user = User::factory()->create([
                'name' => 'Dashboard QA '.ucfirst($role),
                'email' => $role.'-glass-browser@example.test',
                'password' => Hash::make('Only-A-CI-Browser-Fixture-2026!'),
            ]);
            $user->forceFill(['role' => $role])->save();
            $accounts[$role] = $user;
        }

        $course = Course::create([
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'slug' => 'role-glass-acceptance-course',
            'title' => 'Dashboard fixture course',
            'summary' => 'Synthetic acceptance course.',
            'description' => 'Role dashboard acceptance fixture.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        CourseInstructorAssignment::create([
            'course_id' => $course->id,
            'instructor_id' => $accounts['instructor']->id,
            'assigned_by' => $accounts['admin']->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);
        $enrollment = CourseEnrollment::create([
            'course_id' => $course->id, 'student_id' => $accounts['student']->id,
            'status' => 'approved',
        ]);
        $assignment = CourseHomeworkAssignment::create([
            'course_id' => $course->id, 'author_id' => $accounts['instructor']->id,
            'title' => 'Dashboard fixture deadline',
            'instructions' => 'Complete the synthetic exercise.',
            'status' => 'published', 'published_at' => now(), 'due_at' => now()->addDay(),
        ]);
        $grading = CourseHomeworkAssignment::create([
            'course_id' => $course->id, 'author_id' => $accounts['instructor']->id,
            'title' => 'Dashboard fixture grading',
            'instructions' => 'Grade the synthetic exercise.',
            'status' => 'published', 'published_at' => now(),
        ]);
        $submission = HomeworkSubmission::create([
            'assignment_id' => $grading->id, 'enrollment_id' => $enrollment->id,
            'body' => 'Synthetic response.', 'version' => 1,
            'status' => 'submitted', 'submitted_at' => now(),
        ]);

        return compact('accounts', 'course', 'assignment', 'submission');
    }
}
