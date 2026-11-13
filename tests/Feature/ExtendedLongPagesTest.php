<?php

namespace Tests\Feature;

use App\Models\AppearanceRevision;
use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseInstructorAssignment;
use App\Models\LessonProgress;
use App\Models\PracticeAttempt;
use App\Models\User;
use App\Support\Appearance;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExtendedLongPagesTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $role): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>$role])->save();

        return $user;
    }

    private function approvedCourse(string $slug='approved-course'): Course
    {
        $category=Category::query()->first();
        if (!$category) {
            $this->seed(CategorySeeder::class);
            $category=Category::query()->where('is_published',true)->firstOrFail();
        }

        return Course::create([
            'category_id'=>$category->id,
            'slug'=>$slug,
            'title'=>'Reviewed '.$slug,
            'summary'=>'Educational content with actual approved review records.',
            'description'=>'A published learning course used for independent tests.',
            'status'=>'published','published_at'=>now(),
        ]);
    }

    private function lesson(Course $course)
    {
        $module=$course->modules()->create([
            'title'=>'Curriculum unit','position'=>1,
        ]);
        return $module->lessons()->create([
            'title'=>'Protected Lesson Name',
            'content'=>'Sensitive instructional passage for an approved learner only.',
            'position'=>1,'duration_minutes'=>25,
        ]);
    }

    private function quiz(Course $course)
    {
        $quiz=$course->quizzes()->create([
            'title'=>'Real Practice Quiz',
            'instructions'=>'Choose an answer privately.',
            'passing_percent'=>70,'position'=>1,
        ]);
        $quiz->questions()->create([
            'prompt'=>'A private assessment question',
            'option_a'=>'Secret answer A','option_b'=>'Secret answer B',
            'option_c'=>'Secret answer C','option_d'=>'Secret answer D',
            'correct_option'=>'B',
            'explanation'=>'Private answer key explanation',
            'position'=>1,
        ]);

        return $quiz;
    }

    public function test_all_ten_public_page_types_render_as_full_width_continuous_pages(): void
    {
        foreach ([
            '/articles','/recommendations','/leaderboard',
            '/exam-catalog','/course-watch','/reading',
            '/library','/upload-studio',
        ] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('max-w-[1600px]',false)
                ->assertSee('Learn Cambodia home')
                ->assertDontSee('50,000+');
        }
    }

    public function test_blog_and_single_article_render_reviewed_content_and_do_not_expose_private_drafts(): void
    {
        $admin=$this->role('admin');
        ContentEntry::create([
            'type'=>'post','slug'=>'safe-reading-article',
            'title'=>'Published Reading Resource',
            'summary'=>'Relevant educational learning materials.',
            'body'=>"Public educational paragraph.\n\nSecond approved paragraph.",
            'status'=>'published','created_by'=>$admin->id,
        ]);
        ContentEntry::create([
            'type'=>'post','slug'=>'secret-reading-article',
            'title'=>'Unapproved Content Draft',
            'summary'=>'Not published','body'=>'Private educational draft.',
            'status'=>'draft','created_by'=>$admin->id,
        ]);

        $this->get('/articles')->assertOk()
            ->assertSee('Published Reading Resource')
            ->assertDontSee('Unapproved Content Draft')
            ->assertSee('max-w-[1600px]',false);
        $this->get('/articles/safe-reading-article')->assertOk()
            ->assertSee('Public educational paragraph.')
            ->assertSee('Second approved paragraph.')
            ->assertSee('Article overview')
            ->assertDontSee('Unapproved Content Draft')
            ->assertDontSee($admin->email);
        $this->get('/articles/secret-reading-article')->assertNotFound();
    }

    public function test_library_and_reading_show_real_published_material_only(): void
    {
        $admin=$this->role('admin');
        $this->approvedCourse();
        ContentEntry::create([
            'type'=>'post','slug'=>'public-learning-library',
            'title'=>'Approved Resource Guide',
            'summary'=>'A real public library item.',
            'body'=>'Reviewed educational information.',
            'status'=>'published','created_by'=>$admin->id,
        ]);
        ContentEntry::create([
            'type'=>'post','slug'=>'private-learning-library',
            'title'=>'Secret Library Draft',
            'summary'=>'Draft','body'=>'Do not release.',
            'status'=>'draft','created_by'=>$admin->id,
        ]);

        foreach (['/library','/reading'] as $page) {
            $this->get($page)->assertOk()
                ->assertSee('Approved Resource Guide')
                ->assertDontSee('Secret Library Draft')
                ->assertSee('max-w-[1600px]',false);
        }
    }

    public function test_exam_directory_and_single_exam_list_only_safe_quiz_metadata(): void
    {
        $course=$this->approvedCourse('reviewed-examination');
        $quiz=$this->quiz($course);
        $hidden=$this->approvedCourse('hidden-examination');
        $hidden->forceFill(['status'=>'draft','published_at'=>null])->save();
        $this->quiz($hidden);

        $this->get('/exam-catalog')->assertOk()
            ->assertSee($course->title)
            ->assertDontSee($hidden->title)
            ->assertDontSee('Secret answer B')
            ->assertSee('max-w-[1600px]',false);
        $this->get('/exam-catalog/'.$course->slug)->assertOk()
            ->assertSee($quiz->title)
            ->assertSee('1 questions')  // visible question count
            ->assertDontSee('Secret answer B')
            ->assertDontSee('Private answer key explanation')
            ->assertDontSee('A private assessment question');
        $this->get('/exam-catalog/'.$hidden->slug)->assertNotFound();
    }

    public function test_private_focused_lesson_rejects_guest_wrong_student_and_cancelled_enrollment(): void
    {
        $course=$this->approvedCourse('focused-learning');
        $lesson=$this->lesson($course);
        $student=$this->role('student');
        $other=$this->role('student');
        $enrollment=CourseEnrollment::create([
            'course_id'=>$course->id,'student_id'=>$student->id,
            'status'=>'approved',
        ]);
        $url='/account/enrollments/'.$enrollment->id.'/watch/'.$lesson->id;
        $this->get($url)->assertRedirect('/login');
        $this->actingAs($other)->get($url)->assertNotFound();

        $this->actingAs($student)->get($url)->assertOk()
            ->assertSee($lesson->title)
            ->assertSee('Sensitive instructional passage for an approved learner only.')
            ->assertSee('max-w-[1600px]',false)
            ->assertSee('Mark this lesson complete');

        $enrollment->update(['status'=>'cancelled']);
        $this->get($url)->assertForbidden();
        $this->get('/course-watch')->assertOk()
            ->assertDontSee('Sensitive instructional passage for an approved learner only.');
    }

    public function test_study_hub_uses_approved_enrollment_and_recommendations_do_not_leak_other_users(): void
    {
        $student=$this->role('student');
        $other=$this->role('student');
        $course=$this->approvedCourse('own-learning-path');
        $lesson=$this->lesson($course);
        $enrollment=CourseEnrollment::create([
            'course_id'=>$course->id,'student_id'=>$student->id,'status'=>'approved',
        ]);
        LessonProgress::create([
            'enrollment_id'=>$enrollment->id,'lesson_id'=>$lesson->id,'completed_at'=>now(),
        ]);

        $quiz=$this->quiz($course);
        PracticeAttempt::create([
            'course_quiz_id'=>$quiz->id,'enrollment_id'=>$enrollment->id,
            'correct_count'=>1,'question_count'=>1,'score_percent'=>100,
            'passed'=>true,'submitted_at'=>now(),
        ]);

        CourseEnrollment::create([
            'course_id'=>$course->id,'student_id'=>$other->id,'status'=>'approved',
        ]);

        $this->actingAs($student)->get('/recommendations')->assertOk()
            ->assertSee('Reviewed own-learning-path')
            ->assertSee('Your recent practice')
            ->assertDontSee($other->email);
        $this->get('/leaderboard')->assertOk()
            ->assertSee('First passing practice quiz')
            ->assertSee('Completed from your learning records')
            ->assertDontSee($other->email)
            ->assertDontSee('15,420,000');
        $this->get('/course-watch')->assertOk()
            ->assertSee('Open focused lesson');
    }

    public function test_studio_only_displays_an_authorized_instructors_owned_course_assignments(): void
    {
        $course=$this->approvedCourse('instructor-course');
        $instructor=$this->role('instructor');
        $other=$this->role('student');
        $admin=$this->role('admin');
        CourseInstructorAssignment::create([
            'course_id'=>$course->id,'instructor_id'=>$instructor->id,
            'assigned_by'=>$admin->id,'status'=>'active','assigned_at'=>now(),
        ]);

        $this->get('/upload-studio')->assertOk()
            ->assertDontSee($course->title)
            ->assertSee('Instructor authorization required');
        $this->actingAs($other)->get('/upload-studio')->assertOk()
            ->assertDontSee($course->title);

        $this->actingAs($instructor)->get('/upload-studio')->assertOk()
            ->assertSee($course->title)
            ->assertSee('Your active teaching assignments')
            ->assertSee('File and video uploads are unavailable');
    }

    public function test_all_ten_appearance_page_names_can_be_edited_and_require_independent_approval(): void
    {
        $author=$this->role('admin');
        $reviewer=$this->role('admin');
        $sections=[
            'blog'=>'Blog',
            'single_blog'=>'Single Blog',
            'recommendations'=>'Recommendations',
            'leaderboard_trophy'=>'Leaderboard & Trophy',
            'course_exam_catalog'=>'Course Exam Catalog',
            'single_course_exam'=>'Single Course Exam',
            'course_watch'=>'Course Watch',
            'reading'=>'Reading',
            'library'=>'Library',
            'upload_studio'=>'Upload Studio',
        ];
        $this->actingAs($author)->get('/dashboard/appearance')->assertOk()
            ->assertSee('10 page layouts');
        foreach($sections as $key=>$name) {
            $this->get('/dashboard/appearance/'.$key)
                ->assertOk()->assertSee('Save new draft');
            $this->assertSame($name,Appearance::LABELS[$key]);
            $this->assertNotEmpty(Appearance::preview($key));
        }

        $data=Appearance::approved('blog');
        $data['hero_heading']='Reviewed Blog Long-Page Title';
        $this->post('/dashboard/appearance/blog',$data)->assertRedirect('/dashboard/appearance/blog');
        $revision=AppearanceRevision::where('section','blog')->firstOrFail();
        $this->get('/articles')->assertDontSee('Reviewed Blog Long-Page Title');
        $this->post('/dashboard/appearance/revisions/'.$revision->id.'/submit')->assertRedirect();
        $this->post('/dashboard/reviews/appearance/'.$revision->id,['decision'=>'approve'])
            ->assertForbidden();
        $this->actingAs($reviewer)
            ->post('/dashboard/reviews/appearance/'.$revision->id,['decision'=>'approve'])
            ->assertRedirect();
        $this->get('/articles')->assertSee('Reviewed Blog Long-Page Title');
    }
}
