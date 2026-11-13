<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseInstructorAssignment;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function course(): Course
    {
        $this->seed(CategorySeeder::class);

        return Course::create([
            'category_id' => Category::where('code','GE')->firstOrFail()->id,
            'slug' => 'instructor-teaching-course',
            'title' => 'Instructor Teaching Course',
            'summary' => 'Academically approved English lessons.',
            'description' => 'Independent curriculum tests.',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_instructor_role_requires_audited_operator_provisioning(): void
    {
        $teacher = User::factory()->create(['email'=>'teacher@learncambodia.test']);
        $this->artisan('accounts:assign-role',[
            'email'=>$teacher->email,
            'role'=>'instructor',
            '--operator'=>'Principal Operator',
            '--reason'=>'Verified instructor appointment',
        ])->assertExitCode(1);

        $this->assertSame('student',$teacher->fresh()->role);

        $this->artisan('accounts:assign-role',[
            'email'=>$teacher->email,
            'role'=>'instructor',
            '--operator'=>'Principal Operator',
            '--reason'=>'Verified instructor appointment',
            '--confirm'=>true,
        ])->assertExitCode(0);

        $this->assertSame('instructor',$teacher->fresh()->role);
        $this->assertDatabaseHas('account_role_changes',[
            'user_id'=>$teacher->id,'previous_role'=>'student',
            'new_role'=>'instructor',
        ]);
        $this->actingAs($teacher->fresh())
            ->get('/instructor')->assertOk()->assertSee('Teaching Dashboard');
        $this->get('/account')->assertForbidden();
        $this->get('/developer')->assertForbidden();
        $this->get('/dashboard')->assertForbidden();
        $this->get('/instructor/profile')->assertOk()->assertSee('Profile & Security');
        $this->post('/language',['locale'=>'km'])->assertRedirect('/instructor?lang=km');
    }

    public function test_administrator_can_assign_revoke_and_reassign_teacher_to_published_course(): void
    {
        $course = $this->course();
        $teacher = $this->user('instructor');
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/dashboard/instructors/assign',[
            'course_id'=>$course->id,'instructor_id'=>$teacher->id,
        ])->assertRedirect();

        $record = CourseInstructorAssignment::firstOrFail();
        $this->assertSame('active',$record->status);
        $this->assertDatabaseHas('approval_events',[
            'subject_type'=>'instructor_assignment','subject_id'=>$record->id,
            'action'=>'assigned','actor_id'=>$admin->id,
        ]);
        $this->post('/dashboard/instructors/assign',[
            'course_id'=>$course->id,'instructor_id'=>$teacher->id,
        ])->assertStatus(409);

        $this->actingAs($teacher)
            ->get('/instructor/courses')->assertOk()->assertSee('Instructor Teaching Course');
        $this->get('/instructor/courses/'.$course->id)->assertOk()
            ->assertSee('Create a course assignment');

        $this->actingAs($admin)
            ->patch('/dashboard/instructors/'.$record->id.'/revoke')->assertRedirect();
        $this->assertSame('revoked',$record->fresh()->status);
        $this->actingAs($teacher)
            ->get('/instructor/courses/'.$course->id)->assertForbidden();

        $this->actingAs($admin)->post('/dashboard/instructors/assign',[
            'course_id'=>$course->id,'instructor_id'=>$teacher->id,
        ])->assertRedirect();
        $this->assertSame('active',$record->fresh()->status);
        $this->assertDatabaseCount('course_instructor_assignments',1);
    }

    public function test_student_cannot_assign_instructors_or_enter_teacher_dashboard(): void
    {
        $course = $this->course();
        $teacher = $this->user('instructor');
        $student = $this->user('student');

        $this->actingAs($student)
            ->post('/dashboard/instructors/assign',[
                'course_id'=>$course->id,'instructor_id'=>$teacher->id,
            ])->assertForbidden();

        $this->get('/instructor')->assertForbidden();
        $this->assertDatabaseCount('course_instructor_assignments',0);
    }

    public function test_non_instructor_account_cannot_be_assigned(): void
    {
        $course = $this->course();
        $admin = $this->user('admin');
        $student = $this->user('student');

        $this->actingAs($admin)->post('/dashboard/instructors/assign',[
            'course_id'=>$course->id,'instructor_id'=>$student->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('course_instructor_assignments',0);
    }

    public function test_demoting_instructor_revokes_teaching_permissions_and_tokens(): void
    {
        $course = $this->course();
        $admin = $this->user('admin');
        $teacher = $this->user('instructor');

        $this->actingAs($admin)->post('/dashboard/instructors/assign',[
            'course_id'=>$course->id,'instructor_id'=>$teacher->id,
        ])->assertRedirect();

        $this->artisan('accounts:assign-role',[
            'email'=>$teacher->email,
            'role'=>'student',
            '--operator'=>'School Principal',
            '--reason'=>'The approved teaching appointment ended',
            '--confirm'=>true,
        ])->assertExitCode(0);

        $this->assertSame('student',$teacher->fresh()->role);
        $this->assertSame('revoked',CourseInstructorAssignment::firstOrFail()->status);
        $this->actingAs($teacher->fresh())->get('/instructor')->assertForbidden();
    }

    public function test_public_registration_cannot_request_instructor_role(): void
    {
        $this->post('/register',[
            'name'=>'Unauthorized Instructor',
            'email'=>'fake-instructor@example.test',
            'password'=>'ValidPassword123!',
            'password_confirmation'=>'ValidPassword123!',
            'terms'=>'1',
            'role'=>'instructor',
        ])->assertRedirect('/account');

        $this->assertDatabaseHas('users',[
            'email'=>'fake-instructor@example.test',
            'role'=>'student',
        ]);
    }
}
