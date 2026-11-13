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

class HomeworkAcademicReviewTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $account = User::factory()->create();
        $account->forceFill(['role'=>$role])->save();
        return $account;
    }

    private function setupCourse(): array
    {
        $this->seed(CategorySeeder::class);
        $course = Course::create([
            'category_id'=>Category::where('code','GE')->firstOrFail()->id,
            'slug'=>'review-course','title'=>'Assignment Review Course',
            'summary'=>'Academic writing course.',
            'description'=>'Verified education course.',
            'status'=>'published','published_at'=>now(),
        ]);
        $admin = $this->user('admin');
        $teacher = $this->user('instructor');
        CourseInstructorAssignment::create([
            'course_id'=>$course->id,'instructor_id'=>$teacher->id,
            'assigned_by'=>$admin->id,'status'=>'active','assigned_at'=>now(),
        ]);
        return compact('course','admin','teacher');
    }

    public function test_homework_is_hidden_from_students_until_independent_approval(): void
    {
        ['course'=>$course,'admin'=>$admin,'teacher'=>$teacher] = $this->setupCourse();
        $student = $this->user('student');
        CourseEnrollment::create([
            'course_id'=>$course->id,
            'student_id'=>$student->id,'status'=>'approved',
        ]);

        $this->actingAs($teacher)->post('/instructor/courses/'.$course->id.'/assignments',[
            'title'=>'Reviewed Assignment',
            'instructions'=>'Complete this written assignment with your own ideas.',
        ])->assertRedirect();
        $assignment = CourseHomeworkAssignment::firstOrFail();

        $this->actingAs($student)->get('/account/homework')
            ->assertOk()->assertDontSee('Reviewed Assignment');
        $this->get('/account/homework/'.$assignment->id)->assertNotFound();
        $this->post('/account/homework/'.$assignment->id.'/submit',[
            'body'=>'This early submission should never be stored.',
        ])->assertNotFound();

        $this->actingAs($teacher)
            ->post('/instructor/assignments/'.$assignment->id.'/submit')
            ->assertRedirect();
        $this->patch('/instructor/assignments/'.$assignment->id,[
            'title'=>'Illegal change',
            'instructions'=>'Cannot change during review.',
        ])->assertStatus(409);

        $this->actingAs($admin)
            ->post('/dashboard/homework/reviews/'.$assignment->id,[
                'decision'=>'approve',
            ])->assertRedirect();
        $this->assertSame('published',$assignment->fresh()->status);

        $this->actingAs($teacher)
            ->patch('/instructor/assignments/'.$assignment->id,[
                'title'=>'Published mutation',
                'instructions'=>'Cannot change published.',
            ])->assertStatus(409);
        $this->actingAs($student)->get('/account/homework')
            ->assertOk()->assertSee('Reviewed Assignment');
    }

    public function test_rejected_homework_is_revisable_and_does_not_activate(): void
    {
        ['course'=>$course,'admin'=>$admin,'teacher'=>$teacher] = $this->setupCourse();
        $this->actingAs($teacher)->post('/instructor/courses/'.$course->id.'/assignments',[
            'title'=>'First Attempt','instructions'=>'An assignment needing revision.',
        ])->assertRedirect();
        $assignment = CourseHomeworkAssignment::firstOrFail();

        $this->post('/instructor/assignments/'.$assignment->id.'/submit')->assertRedirect();
        $this->actingAs($admin)
            ->post('/dashboard/homework/reviews/'.$assignment->id,[
                'decision'=>'reject','note'=>'Learning outcomes and assessment rubric are insufficient.',
            ])->assertRedirect();
        $this->assertSame('rejected',$assignment->fresh()->status);

        $this->actingAs($teacher)->patch('/instructor/assignments/'.$assignment->id,[
            'title'=>'Improved Assignment',
            'instructions'=>'Now complete the exercise using clear learning outcomes.',
            'rubric'=>'Accuracy and clarity, graded out of 100.',
        ])->assertRedirect();

        $this->assertSame('Improved Assignment',$assignment->fresh()->title);
        $this->assertDatabaseCount('course_homework_assignments',1);
    }

    public function test_student_deadline_is_enforced_without_changing_published_assignment(): void
    {
        ['course'=>$course,'teacher'=>$teacher] = $this->setupCourse();
        $assignment = CourseHomeworkAssignment::create([
            'course_id'=>$course->id,'author_id'=>$teacher->id,
            'title'=>'Expired Assignment',
            'instructions'=>'Complete this learning activity.',
            'status'=>'published','published_at'=>now(),
            'due_at'=>now()->subMinute(),
        ]);
        $student = $this->user('student');
        CourseEnrollment::create([
            'course_id'=>$course->id,'student_id'=>$student->id,'status'=>'approved',
        ]);

        $this->actingAs($student)->post('/account/homework/'.$assignment->id.'/submit',[
            'body'=>'This answer is past the deadline.',
        ])->assertStatus(422);
        $this->assertDatabaseCount('homework_submissions',0);
    }

    public function test_returned_homework_allows_at_most_three_versions(): void
    {
        ['course'=>$course,'teacher'=>$teacher] = $this->setupCourse();
        $assignment = CourseHomeworkAssignment::create([
            'course_id'=>$course->id,'author_id'=>$teacher->id,
            'title'=>'Resubmission practice',
            'instructions'=>'Submit a complete learning response.',
            'status'=>'published','published_at'=>now(),
        ]);
        $student = $this->user('student');
        CourseEnrollment::create([
            'course_id'=>$course->id,'student_id'=>$student->id,'status'=>'approved',
        ]);

        for($version=1;$version<=3;$version++){
            $this->actingAs($student)->post('/account/homework/'.$assignment->id.'/submit',[
                'body'=>'This is academic version '.$version.' with sufficient text.',
            ])->assertRedirect();
            $submission = HomeworkSubmission::firstOrFail();
            $this->actingAs($teacher)
                ->post('/instructor/submissions/'.$submission->id.'/review',[
                    'decision'=>'return',
                    'feedback'=>'Please improve your academic writing and retry.',
                ])->assertRedirect();
        }

        $this->assertDatabaseCount('homework_submission_versions',3);
        $this->assertSame(3,$submission->fresh()->version);
        $this->actingAs($student)->post('/account/homework/'.$assignment->id.'/submit',[
            'body'=>'A fourth version must never be permitted.',
        ])->assertStatus(409);
    }
}
