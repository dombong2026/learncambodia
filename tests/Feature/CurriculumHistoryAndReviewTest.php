<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseReviewSnapshot;
use App\Models\CurriculumLessonRevision;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumHistoryAndReviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>'admin'])->save();
        return $user;
    }

    private function draft(User $author): Course
    {
        $this->seed(CategorySeeder::class);
        return Course::create([
            'category_id'=>Category::where('code','GE')->firstOrFail()->id,
            'created_by'=>$author->id,'slug'=>'revision-course',
            'title'=>'Reviewed Revision Course','summary'=>'Structured learning',
            'description'=>'Complete reviewed learning objectives.','status'=>'draft',
        ]);
    }

    public function test_lesson_revisions_duplication_and_independent_approval_evidence(): void
    {
        $author=$this->admin();
        $reviewer=$this->admin();
        $course=$this->draft($author);
        $module=$course->modules()->create(['title'=>'Unit One','position'=>1]);

        $this->actingAs($author)->post('/dashboard/course-builder/'.$course->id.'/modules/'.$module->id.'/lessons',[
            'title'=>'Intro lesson','content'=>'Start learning here',
            'position'=>1,'duration_minutes'=>20,'is_preview'=>0,
        ])->assertRedirect();
        $lesson=$module->lessons()->firstOrFail();
        $this->assertSame(1,CurriculumLessonRevision::where('lesson_id',$lesson->id)->count());

        $this->patch('/dashboard/course-builder/'.$course->id.'/modules/'.$module->id.'/lessons/'.$lesson->id,[
            'title'=>'Updated intro','content'=>'Improved reviewed reading',
            'position'=>1,'duration_minutes'=>25,'is_preview'=>0,
        ])->assertRedirect();
        $this->assertSame(2,CurriculumLessonRevision::where('lesson_id',$lesson->id)->count());

        $this->post('/dashboard/course-builder/'.$course->id.'/modules/'.$module->id.'/lessons/'.$lesson->id.'/duplicate')->assertRedirect();
        $this->assertSame(2,$module->lessons()->count());
        $this->get('/dashboard/course-builder/'.$course->id.'/history')
            ->assertOk()->assertSee('Improved reviewed reading')
            ->assertSee('Academic readiness checklist');

        $this->post('/dashboard/course-builder/'.$course->id.'/submit')->assertRedirect();
        $snapshot=CourseReviewSnapshot::firstOrFail();
        $this->assertSame(64,strlen($snapshot->fingerprint));
        $this->post('/dashboard/reviews/courses/'.$course->id,['decision'=>'approve'])->assertForbidden();

        $this->actingAs($reviewer)
            ->post('/dashboard/reviews/courses/'.$course->id,['decision'=>'approve'])->assertRedirect();
        $this->assertSame('published',$course->fresh()->status);
        $this->assertSame('approve',$snapshot->fresh()->decision);
        $this->assertSame($reviewer->id,$snapshot->fresh()->reviewed_by);
    }

    public function test_pending_content_changes_block_approval_and_history_is_admin_only(): void
    {
        $author=$this->admin();
        $reviewer=$this->admin();
        $course=$this->draft($author);
        $module=$course->modules()->create(['title'=>'Unit One','position'=>1]);
        $lesson=$module->lessons()->create(['title'=>'Lesson','content'=>'Original content','position'=>1]);

        $this->actingAs($author)
            ->post('/dashboard/course-builder/'.$course->id.'/submit')->assertRedirect();
        $lesson->update(['content'=>'Changed after submission']);

        $this->actingAs($reviewer)
            ->post('/dashboard/reviews/courses/'.$course->id,['decision'=>'approve'])->assertStatus(409);
        $this->assertSame('pending_review',$course->fresh()->status);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard/course-builder/'.$course->id.'/history')->assertForbidden();
        $this->post('/dashboard/course-builder/'.$course->id.'/modules/'.$module->id.'/duplicate')->assertForbidden();
    }

    public function test_pending_course_cannot_duplicate_lessons_or_modules(): void
    {
        $author=$this->admin();
        $course=$this->draft($author);
        $module=$course->modules()->create(['title'=>'Unit One','position'=>1]);
        $lesson=$module->lessons()->create(['title'=>'Lesson','content'=>'Text','position'=>1]);
        $course->update(['status'=>'pending_review']);

        $this->actingAs($author)
            ->post('/dashboard/course-builder/'.$course->id.'/modules/'.$module->id.'/duplicate')->assertStatus(409);
        $this->post('/dashboard/course-builder/'.$course->id.'/modules/'.$module->id.'/lessons/'.$lesson->id.'/duplicate')->assertStatus(409);
        $this->assertDatabaseCount('curriculum_lesson_revisions',0);
    }
}
