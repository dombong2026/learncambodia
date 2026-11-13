<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseInstructorAssignment;
use App\Models\InstructorFeedbackTemplate;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorToolsExtensionTest extends TestCase
{
    use RefreshDatabase;

    private function instructor(): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>'instructor'])->save();
        return $user;
    }

    private function assignedCourse(User $teacher, string $slug): Course
    {
        if(Category::where('code','GE')->doesntExist()) $this->seed(CategorySeeder::class);
        $course=Course::create([
            'category_id'=>Category::where('code','GE')->firstOrFail()->id,
            'slug'=>$slug,'title'=>'Current '.$slug,'summary'=>'Reviewed study',
            'status'=>'published','published_at'=>now(),
        ]);
        CourseInstructorAssignment::create([
            'course_id'=>$course->id,'instructor_id'=>$teacher->id,
            'assigned_by'=>User::factory()->create()->id,
            'status'=>'active','assigned_at'=>now(),
        ]);
        return $course;
    }

    public function test_private_feedback_templates_are_owned_and_editable(): void
    {
        $teacher=$this->instructor();
        $other=$this->instructor();
        $this->get('/instructor/feedback-templates')->assertRedirect();
        $this->actingAs($teacher)->post('/instructor/feedback-templates',[
            'title'=>'Language accuracy','body'=>'Review your verb endings carefully.',
        ])->assertRedirect();
        $template=InstructorFeedbackTemplate::firstOrFail();
        $this->get('/instructor/feedback-templates')->assertOk()->assertSee('Language accuracy');
        $this->actingAs($other)->get('/instructor/feedback-templates')->assertOk()->assertDontSee('Language accuracy');
        $this->patch('/instructor/feedback-templates/'.$template->id,[
            'title'=>'Intrusion','body'=>'Attempt from another instructor',
        ])->assertNotFound();
        $this->delete('/instructor/feedback-templates/'.$template->id)->assertNotFound();
        $this->actingAs($teacher)->patch('/instructor/feedback-templates/'.$template->id,[
            'title'=>'Writing accuracy','body'=>'Use stronger topic sentences.',
        ])->assertRedirect();
        $this->assertDatabaseHas('instructor_feedback_templates',['id'=>$template->id,'title'=>'Writing accuracy']);
        $this->delete('/instructor/feedback-templates/'.$template->id)->assertRedirect();
        $this->assertDatabaseCount('instructor_feedback_templates',0);
    }

    public function test_teaching_insights_are_restricted_to_current_assignments(): void
    {
        $teacher=$this->instructor();
        $other=$this->instructor();
        $own=$this->assignedCourse($teacher,'instructor-own');
        $foreign=$this->assignedCourse($other,'instructor-foreign');

        $this->actingAs($teacher)->get('/instructor/insights')->assertOk()
            ->assertSee('Current instructor-own')->assertDontSee('Current instructor-foreign');
        CourseInstructorAssignment::where('course_id',$own->id)->update(['status'=>'revoked','revoked_at'=>now()]);
        $this->get('/instructor/insights')->assertOk()->assertDontSee('Current instructor-own');
        $this->actingAs(User::factory()->create())->get('/instructor/insights')->assertForbidden();
    }

    public function test_reusable_template_can_prefill_grading_only_for_its_owner(): void
    {
        $teacher=$this->instructor();
        $course=$this->assignedCourse($teacher,'feedback-course');
        $admin=User::factory()->create();
        $student=User::factory()->create();
        $enrollment=$course->enrollments()->create([
            'student_id'=>$student->id,'status'=>'approved',
        ]);
        $assignment=$course->homeworkAssignments()->create([
            'author_id'=>$teacher->id,'title'=>'Writing work','instructions'=>'Respond with a paragraph',
            'status'=>'published','published_at'=>now(),
        ]);
        $submission=$assignment->submissions()->create([
            'enrollment_id'=>$enrollment->id,'body'=>'My answer','version'=>1,
            'status'=>'submitted','submitted_at'=>now(),
        ]);
        $mine=InstructorFeedbackTemplate::create([
            'instructor_id'=>$teacher->id,'title'=>'Mine','body'=>'Check your paragraph structure.',
        ]);
        $theirs=InstructorFeedbackTemplate::create([
            'instructor_id'=>$this->instructor()->id,'title'=>'Other private template',
            'body'=>'This must not be visible to the current teacher.',
        ]);
        $this->actingAs($teacher)->get('/instructor/submissions/'.$submission->id.'?template='.$mine->id)
            ->assertOk()->assertSee('Check your paragraph structure.');
        $this->get('/instructor/submissions/'.$submission->id.'?template='.$theirs->id)->assertNotFound();
    }
}
