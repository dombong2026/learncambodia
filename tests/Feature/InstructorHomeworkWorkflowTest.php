<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseHomeworkAssignment;
use App\Models\CourseInstructorAssignment;
use App\Models\HomeworkSubmission;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorHomeworkWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role'=>$role])->save();

        return $user;
    }

    private function course(): Course
    {
        $this->seed(CategorySeeder::class);

        return Course::create([
            'category_id'=>Category::where('code','GE')->firstOrFail()->id,
            'slug'=>'reviewed-homework',
            'title'=>'Reviewed Homework Course',
            'summary'=>'Course with real homework.',
            'description'=>'Academic homework grading experience.',
            'status'=>'published','published_at'=>now(),
        ]);
    }

    private function approvedAssignment(): array
    {
        $course = $this->course();
        $instructor = $this->user('instructor');
        $administrator = $this->user('admin');
        CourseInstructorAssignment::create([
            'course_id'=>$course->id,'instructor_id'=>$instructor->id,
            'assigned_by'=>$administrator->id,'status'=>'active','assigned_at'=>now(),
        ]);
        $this->actingAs($instructor)->post('/instructor/courses/'.$course->id.'/assignments',[
            'title'=>'Write a short introduction',
            'instructions'=>'Write an introduction to the course using your own words.',
            'rubric'=>'Accuracy 60%. Clarity 40%.',
        ])->assertRedirect();
        $assignment = CourseHomeworkAssignment::firstOrFail();
        $this->assertSame('draft',$assignment->status);
        $this->post('/instructor/assignments/'.$assignment->id.'/submit')->assertRedirect();

        $this->actingAs($administrator)
            ->post('/dashboard/homework/reviews/'.$assignment->id,[
                'decision'=>'approve',
            ])->assertRedirect();
        $this->assertSame('published',$assignment->fresh()->status);

        $student = $this->user('student');
        $enrollment = CourseEnrollment::create([
            'course_id'=>$course->id,
            'student_id'=>$student->id,
            'status'=>'approved',
        ]);

        return compact('course','instructor','administrator','assignment','student','enrollment');
    }

    public function test_published_homework_requires_independent_administrator_review(): void
    {
        ['assignment'=>$assignment,'student'=>$student] = $this->approvedAssignment();

        $this->actingAs($student)->get('/account/homework')
            ->assertOk()->assertSee('Write a short introduction');
        $this->get('/account/homework/'.$assignment->id)
            ->assertOk()->assertSee('Accuracy 60%');

        $this->assertDatabaseHas('approval_events',[
            'subject_type'=>'homework_assignment',
            'subject_id'=>$assignment->id,
            'action'=>'approve',
        ]);
    }

    public function test_student_submission_can_be_returned_revised_and_graded(): void
    {
        $setup = $this->approvedAssignment();
        extract($setup);

        $this->actingAs($student)->post('/account/homework/'.$assignment->id.'/submit',[
            'body'=>'This is the first version of my academic introduction.',
        ])->assertRedirect();
        $submission = HomeworkSubmission::firstOrFail();
        $this->assertSame('submitted',$submission->status);
        $this->assertSame(1,$submission->version);
        $this->assertDatabaseCount('homework_submission_versions',1);

        $this->post('/account/homework/'.$assignment->id.'/submit',[
            'body'=>'Unapproved simultaneous submission should be blocked.',
        ])->assertStatus(409);
        $this->assertDatabaseCount('homework_submissions',1);

        $this->actingAs($instructor)->get('/instructor/submissions')
            ->assertOk()->assertSee('Write a short introduction');
        $this->get('/instructor/submissions/'.$submission->id)
            ->assertOk()->assertSee('first version');

        $this->post('/instructor/submissions/'.$submission->id.'/review',[
            'decision'=>'return',
            'feedback'=>'Please expand this introduction with two examples.',
        ])->assertRedirect();
        $this->assertSame('returned',$submission->fresh()->status);

        $this->actingAs($student)
            ->get('/account/submissions/'.$submission->id)->assertNotFound();
        $this->get('/account/homework/submissions/'.$submission->id.'/review')
            ->assertOk()->assertSee('Please expand');
        $this->post('/account/homework/'.$assignment->id.'/submit',[
            'body'=>'Revised introduction with two clear examples and academic vocabulary.',
        ])->assertRedirect();

        $this->assertSame(2,$submission->fresh()->version);
        $this->assertDatabaseCount('homework_submissions',1);
        $this->assertDatabaseCount('homework_submission_versions',2);

        $this->actingAs($instructor)->post('/instructor/submissions/'.$submission->id.'/review',[
            'decision'=>'grade','score'=>88,
            'feedback'=>'Good revision with clear examples and coherent structure.',
        ])->assertRedirect();
        $this->assertSame('graded',$submission->fresh()->status);
        $this->assertSame(88,$submission->fresh()->score);

        $this->actingAs($student)
            ->get('/account/homework/submissions/'.$submission->id.'/review')
            ->assertOk()->assertSee('88/100')->assertSee('Good revision');

        $this->get('/account/homework/submissions')
            ->assertOk()->assertSee('Write a short introduction');

        $this->assertDatabaseHas('approval_events',[
            'subject_type'=>'homework_submission',
            'subject_id'=>$submission->id,
            'action'=>'grade','to_status'=>'graded',
            'actor_id'=>$instructor->id,
        ]);
    }

    public function test_other_student_cannot_read_or_change_private_submission(): void
    {
        ['assignment'=>$assignment,'student'=>$student] = $this->approvedAssignment();
        $this->actingAs($student)->post('/account/homework/'.$assignment->id.'/submit',[
            'body'=>'Only my teacher should be able to review this work.',
        ])->assertRedirect();
        $submission = HomeworkSubmission::firstOrFail();

        $outsider = $this->user('student');
        $this->actingAs($outsider)
            ->get('/account/homework/submissions/'.$submission->id.'/review')
            ->assertNotFound();
        $this->get('/account/homework/'.$assignment->id)->assertNotFound();
        $this->post('/account/homework/'.$assignment->id.'/submit',[
            'body'=>'Attempt to submit in another student course.',
        ])->assertNotFound();
    }

    public function test_unassigned_instructor_cannot_grade_or_view_student_submission(): void
    {
        ['assignment'=>$assignment,'student'=>$student] = $this->approvedAssignment();
        $this->actingAs($student)->post('/account/homework/'.$assignment->id.'/submit',[
            'body'=>'This student response is confidential teaching material.',
        ])->assertRedirect();
        $submission = HomeworkSubmission::firstOrFail();

        $outsider = $this->user('instructor');
        $this->actingAs($outsider)->get('/instructor/submissions/'.$submission->id)
            ->assertForbidden();
        $this->post('/instructor/submissions/'.$submission->id.'/review',[
            'decision'=>'grade','score'=>100,'feedback'=>'Forged grading attempt.',
        ])->assertForbidden();
        $this->assertSame('submitted',$submission->fresh()->status);
    }

    public function test_invalid_grade_and_duplicate_evaluation_are_blocked(): void
    {
        ['assignment'=>$assignment,'student'=>$student,'instructor'=>$instructor] = $this->approvedAssignment();
        $this->actingAs($student)->post('/account/homework/'.$assignment->id.'/submit',[
            'body'=>'One completed response for this academic coursework.',
        ])->assertRedirect();
        $submission = HomeworkSubmission::firstOrFail();

        $this->actingAs($instructor)->post('/instructor/submissions/'.$submission->id.'/review',[
            'decision'=>'grade','score'=>120,'feedback'=>'This score must be rejected.',
        ])->assertSessionHasErrors('score');
        $this->assertSame('submitted',$submission->fresh()->status);

        $this->post('/instructor/submissions/'.$submission->id.'/review',[
            'decision'=>'grade','score'=>75,'feedback'=>'Acceptable academic answer.',
        ])->assertRedirect();
        $this->post('/instructor/submissions/'.$submission->id.'/review',[
            'decision'=>'grade','score'=>90,'feedback'=>'Forbidden second grading.',
        ])->assertStatus(409);

        $this->assertSame(75,$submission->fresh()->score);
    }

    public function test_revoking_teacher_assignment_blocks_further_grading(): void
    {
        ['assignment'=>$assignment,'student'=>$student,'instructor'=>$instructor,
            'administrator'=>$administrator] = $this->approvedAssignment();

        $this->actingAs($student)->post('/account/homework/'.$assignment->id.'/submit',[
            'body'=>'A response waiting for instructor evaluation.',
        ])->assertRedirect();
        $submission = HomeworkSubmission::firstOrFail();
        $authorization = CourseInstructorAssignment::firstOrFail();

        $this->actingAs($administrator)
            ->patch('/dashboard/instructors/'.$authorization->id.'/revoke')->assertRedirect();

        $this->actingAs($instructor)
            ->post('/instructor/submissions/'.$submission->id.'/review',[
                'decision'=>'grade','score'=>90,'feedback'=>'Forbidden after permission revoked.',
            ])->assertForbidden();

        $this->assertSame('submitted',$submission->fresh()->status);
    }
}
