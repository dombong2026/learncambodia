<?php

namespace Tests\Feature;

use App\Models\AcademicEvidenceReview;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseHomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicEvidenceReviewTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role'=>$role])->save();
        return $user;
    }

    private function enrollment(): CourseEnrollment
    {
        $this->seed(CategorySeeder::class);
        $course = Course::create([
            'category_id'=>Category::where('code','GE')->firstOrFail()->id,
            'slug'=>'academic-evidence-course',
            'title'=>'Academic Evidence Course',
            'summary'=>'Approved educational content.',
            'description'=>'An independently published curriculum.',
            'status'=>'published','published_at'=>now(),
        ]);
        $module = $course->modules()->create(['title'=>'Module 1','position'=>1]);
        $lesson = $module->lessons()->create([
            'title'=>'Evidence lesson','content'=>'Learning material.',
            'position'=>1,'duration_minutes'=>15,
        ]);
        $student = $this->user('student');
        $enrollment = CourseEnrollment::create([
            'course_id'=>$course->id,
            'student_id'=>$student->id,
            'status'=>'approved',
        ]);
        $enrollment->lessonProgress()->create([
            'lesson_id'=>$lesson->id,'completed_at'=>now(),
        ]);
        $author = $this->user('instructor');
        $assignment = CourseHomeworkAssignment::create([
            'course_id'=>$course->id,'author_id'=>$author->id,
            'title'=>'Academic writing',
            'instructions'=>'Write a short academic introduction.',
            'status'=>'published','published_at'=>now(),
        ]);
        HomeworkSubmission::create([
            'assignment_id'=>$assignment->id,
            'enrollment_id'=>$enrollment->id,
            'body'=>'Completed student homework for academic verification.',
            'version'=>1,'status'=>'graded','submitted_at'=>now(),
            'score'=>80,'feedback'=>'Good level of written clarity.',
            'graded_by'=>$author->id,'graded_at'=>now(),
        ]);

        return $enrollment;
    }

    public function test_gradebook_and_certificate_evidence_are_private_administrator_views(): void
    {
        $this->enrollment();
        $student = User::where('role','student')->firstOrFail();
        $admin = $this->user('admin');

        $this->actingAs($student)
            ->get('/dashboard/academic/gradebook')->assertForbidden();
        $this->get('/dashboard/academic/evidence')->assertForbidden();

        $this->actingAs($admin)
            ->get('/dashboard/academic/gradebook')
            ->assertOk()->assertSee('Academic writing')->assertSee('80/100');
        $this->get('/dashboard/academic/evidence')
            ->assertOk()->assertSee('Academic Evidence Course')
            ->assertSee('Certificate issuance disabled');
    }

    public function test_two_distinct_administrators_must_review_evidence_without_issuing_a_certificate(): void
    {
        $enrollment = $this->enrollment();
        $requester = $this->user('admin');
        $reviewer = $this->user('admin');

        $this->actingAs($requester)
            ->post('/dashboard/academic/evidence/enrollments/'.$enrollment->id)
            ->assertRedirect();

        $review = AcademicEvidenceReview::firstOrFail();
        $this->assertSame('pending_review',$review->status);
        $this->assertSame(1,$review->evidence_snapshot['lesson_count']);
        $this->assertSame(1,$review->evidence_snapshot['self_reported_completed_lessons']);
        $this->assertSame(1,$review->evidence_snapshot['graded_homework_submissions']);

        $this->post('/dashboard/academic/evidence/reviews/'.$review->id,[
            'decision'=>'reviewed',
            'note'=>'Independent academic evidence inspected.',
        ])->assertForbidden();

        $this->actingAs($reviewer)
            ->post('/dashboard/academic/evidence/reviews/'.$review->id,[
                'decision'=>'reviewed',
                'note'=>'Verified source records for independent review.',
            ])->assertRedirect();

        $this->assertSame('reviewed',$review->fresh()->status);
        $this->assertDatabaseHas('approval_events',[
            'subject_type'=>'academic_evidence','subject_id'=>$review->id,
            'action'=>'reviewed','actor_id'=>$reviewer->id,
        ]);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('certificates'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.certificates.issue'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('account.certificates.download'));

        $this->post('/dashboard/academic/evidence/reviews/'.$review->id,[
            'decision'=>'reviewed',
            'note'=>'Cannot review the same record twice.',
        ])->assertStatus(409);
    }

    public function test_stale_evidence_requires_new_review_and_retains_audit_events(): void
    {
        $enrollment = $this->enrollment();
        $requester = $this->user('admin');
        $reviewer = $this->user('admin');
        $this->actingAs($requester)
            ->post('/dashboard/academic/evidence/enrollments/'.$enrollment->id)
            ->assertRedirect();
        $review = AcademicEvidenceReview::firstOrFail();

        $enrollment->lessonProgress()->update(['completed_at'=>null]);

        $this->actingAs($reviewer)
            ->post('/dashboard/academic/evidence/reviews/'.$review->id,[
                'decision'=>'reviewed','note'=>'The record cannot be accepted while stale.',
            ])->assertStatus(409);

        $this->post('/dashboard/academic/evidence/reviews/'.$review->id,[
            'decision'=>'needs_revision',
            'note'=>'Evidence changed after the earlier snapshot was made.',
        ])->assertRedirect();
        $this->assertSame('needs_revision',$review->fresh()->status);

        $this->actingAs($requester)
            ->post('/dashboard/academic/evidence/enrollments/'.$enrollment->id)
            ->assertRedirect();
        $this->assertSame('pending_review',$review->fresh()->status);
        $this->assertSame(0,$review->fresh()->evidence_snapshot['self_reported_completed_lessons']);
        $this->assertDatabaseCount('academic_evidence_reviews',1);
    }

    public function test_student_cannot_request_academic_evidence_review(): void
    {
        $enrollment = $this->enrollment();
        $student = $enrollment->student;
        $this->actingAs($student)
            ->post('/dashboard/academic/evidence/enrollments/'.$enrollment->id)
            ->assertForbidden();
        $this->assertDatabaseCount('academic_evidence_reviews',0);
    }
}
