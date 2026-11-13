<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LessonNote;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPrivateNotesTest extends TestCase
{
    use RefreshDatabase;

    private function records(): array
    {
        $this->seed(CategorySeeder::class);
        $course = Course::create([
            'category_id'=>Category::where('code','GE')->firstOrFail()->id,
            'slug'=>'note-access-course','title'=>'Private Notes Course','summary'=>'Learning',
            'status'=>'published','published_at'=>now(),
        ]);
        $module = $course->modules()->create(['title'=>'Module','position'=>1]);
        $lesson = $module->lessons()->create(['title'=>'Private lesson', 'content'=>'Private lesson content', 'position'=>1]);
        $student = User::factory()->create();
        $enrollment = CourseEnrollment::create([
            'course_id'=>$course->id, 'student_id'=>$student->id, 'status'=>'approved',
        ]);
        return [$student,$enrollment,$lesson,$course];
    }

    public function test_approved_student_can_create_update_pin_and_delete_only_their_lesson_note(): void
    {
        [$student,$enrollment,$lesson] = $this->records();
        $this->actingAs($student)
            ->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$lesson->id.'/notes',[
                'title'=>'Vocabulary', 'body'=>'A personally useful note', 'is_pinned'=>1,
            ])->assertRedirect();
        $note=LessonNote::firstOrFail();
        $this->assertSame($student->id,$note->student_id);
        $this->get('/account/notes')->assertOk()->assertSee('Vocabulary');
        $this->get('/account/enrollments/'.$enrollment->id.'/watch/'.$lesson->id)
            ->assertOk()->assertSee('A personally useful note');
        $this->patch('/account/notes/'.$note->id,[
            'title'=>'Updated','body'=>'Another personal thought','is_pinned'=>0,
        ])->assertRedirect();
        $this->assertSame('Updated',$note->fresh()->title);
        $this->assertFalse($note->fresh()->is_pinned);
        $this->delete('/account/notes/'.$note->id)->assertRedirect();
        $this->assertDatabaseCount('lesson_notes',0);
    }

    public function test_notes_do_not_leak_to_other_students_or_instructors(): void
    {
        [$student,$enrollment,$lesson] = $this->records();
        $this->actingAs($student)->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$lesson->id.'/notes',[
            'title'=>'Secret private title', 'body'=>'Personal private details','is_pinned'=>0,
        ])->assertRedirect();
        $note=LessonNote::firstOrFail();
        $other=User::factory()->create();
        $this->actingAs($other)->get('/account/notes')->assertOk()->assertDontSee('Secret private title');
        $this->get('/account/notes?enrollment='.$enrollment->id)->assertNotFound();
        $this->patch('/account/notes/'.$note->id,[
            'title'=>'Attempt','body'=>'Attempt','is_pinned'=>0,
        ])->assertNotFound();
        $this->delete('/account/notes/'.$note->id)->assertNotFound();
        $this->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$lesson->id.'/notes',[
            'title'=>'Attempt','body'=>'Attempt','is_pinned'=>0,
        ])->assertNotFound();
        $instructor=User::factory()->create();
        $instructor->forceFill(['role'=>'instructor'])->save();
        $this->actingAs($instructor)->get('/account/notes')->assertForbidden();
        $this->assertDatabaseCount('lesson_notes',1);
    }

    public function test_revoked_course_and_mismatched_lesson_are_not_readable_or_editable(): void
    {
        [$student,$enrollment,$lesson,$course] = $this->records();
        $this->actingAs($student)->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$lesson->id.'/notes',[
            'title'=>'My note','body'=>'My data','is_pinned'=>0,
        ])->assertRedirect();
        $note=LessonNote::firstOrFail();
        $second=Course::create([
            'category_id'=>$course->category_id,'slug'=>'second-note-course','title'=>'Second course',
            'summary'=>'Another','status'=>'published','published_at'=>now(),
        ]);
        $otherLesson=$second->modules()->create(['title'=>'Other','position'=>1])
            ->lessons()->create(['title'=>'Other lesson','content'=>'Another','position'=>1]);
        $this->post('/account/enrollments/'.$enrollment->id.'/lessons/'.$otherLesson->id.'/notes',[
            'title'=>'Invalid','body'=>'Invalid','is_pinned'=>0,
        ])->assertNotFound();

        $enrollment->update(['status'=>'cancelled']);
        $this->get('/account/notes')->assertOk()->assertDontSee('My note');
        $this->patch('/account/notes/'.$note->id,[
            'title'=>'Invalid','body'=>'Invalid','is_pinned'=>0,
        ])->assertForbidden();
        $this->delete('/account/notes/'.$note->id)->assertRedirect();
        $this->assertDatabaseCount('lesson_notes',0);
    }
}
