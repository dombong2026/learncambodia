<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CefrLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendAuthMilestoneTest extends TestCase
{
    use RefreshDatabase;

    private function publishedCourse(): Course
    {
        $this->seed([CategorySeeder::class,CefrLevelSeeder::class]);

        return Course::create([
            'category_id'=>Category::where('code','GE')->firstOrFail()->id,
            'slug'=>'public-course-alpha',
            'title'=>'A Real Approved Course',
            'summary'=>'Learn essential English communication.',
            'description'=>'Reviewed English course content.',
            'cefr_level'=>'A2',
            'delivery_mode'=>'online',
            'status'=>'published',
            'published_at'=>now(),
        ]);
    }

    public function test_public_directory_and_details_show_only_discoverable_courses_without_private_lessons(): void
    {
        $course=$this->publishedCourse();
        $module=$course->modules()->create(['title'=>'First Module','position'=>1]);
        $module->lessons()->create([
            'title'=>'Private Lesson','content'=>'Never expose this private lesson body.',
            'position'=>1,'duration_minutes'=>20,
        ]);
        $draft=Course::create([
            'category_id'=>$course->category_id,'slug'=>'private-draft',
            'title'=>'Confidential Draft Course',
            'summary'=>'Private draft content.',
            'description'=>'Hidden course.',
            'status'=>'draft',
        ]);

        $this->get('/courses')->assertOk()->assertSee('A Real Approved Course')
            ->assertDontSee('Confidential Draft Course');
        $this->get('/courses?level=A2&q=Approved')->assertOk()
            ->assertSee('A Real Approved Course');
        $this->get('/courses?level=C2')->assertOk()
            ->assertDontSee('A Real Approved Course');
        $this->get('/courses?level=invalid')->assertSessionHasErrors('level');

        $this->get('/courses/'.$course->slug)->assertOk()
            ->assertSee('First Module')
            ->assertSee('A Real Approved Course')
            ->assertDontSee('Never expose this private lesson body.');
        $this->get('/courses/'.$draft->slug)->assertNotFound();
        $this->get('/')->assertOk()->assertSee('A Real Approved Course');
    }

    public function test_unpublished_posts_cannot_leak_via_public_article_routes(): void
    {
        $admin=User::factory()->create();
        $admin->forceFill(['role'=>'admin'])->save();

        $published=ContentEntry::create([
            'type'=>'post','slug'=>'published-study-tip','title'=>'Published Study Tip',
            'summary'=>'A short approved learning article.',
            'body'=>'Published learning advice and study guidance.',
            'status'=>'published','created_by'=>$admin->id,
        ]);
        $draft=ContentEntry::create([
            'type'=>'post','slug'=>'private-study-tip','title'=>'Draft Secret Title',
            'summary'=>'Private editorial summary.',
            'body'=>'Never show the draft article.',
            'status'=>'draft','created_by'=>$admin->id,
        ]);
        // A page can legitimately share an article slug; type must stay scoped.
        ContentEntry::create([
            'type'=>'page','slug'=>'published-study-tip','title'=>'Page with same slug',
            'body'=>'This is not a public article.',
            'status'=>'published','created_by'=>$admin->id,
        ]);

        $this->get('/articles')->assertOk()->assertSee('Published Study Tip')
            ->assertDontSee('Draft Secret Title');
        $this->get('/articles/'.$published->slug)->assertOk()
            ->assertSee('Published learning advice')
            ->assertDontSee('This is not a public article.');
        $this->get('/articles/'.$draft->slug)->assertNotFound();
        $this->get('/')->assertOk()->assertSee('Published Study Tip');
    }

    public function test_public_and_auth_page_have_three_language_controls_and_real_navigation(): void
    {
        $this->seed(CategorySeeder::class);
        $this->get('/')->assertOk()
            ->assertSee('Learn Cambodia')
            ->assertSee('name="locale" value="zh"',false)
            ->assertSee('href="'.route('courses.index').'"',false)
            ->assertSee('href="'.route('articles.index').'"',false);

        $this->get('/login')->assertOk()
            ->assertSee('data-password-toggle',false)
            ->assertSee('name="locale" value="zh"',false)
            ->assertSee('Back to home');
        $this->get('/register')->assertOk()
            ->assertSee('register_password_confirmation',false)
            ->assertSee('data-password-toggle',false)
            ->assertSee('draft Privacy Notice');

        $this->post('/language',['locale'=>'zh'])->assertRedirect('/');
        $this->get('/login')->assertOk()->assertSee('欢迎回来');
        $this->get('/register')->assertOk()->assertSee('创建账户');
        $this->get('/courses')->assertOk()->assertSee('探索课程');

        $this->post('/language',['locale'=>'km'])->assertRedirect('/');
        $this->get('/login')->assertOk()->assertSee('សូមស្វាគមន៍មកវិញ');
        $this->get('/courses')->assertOk()->assertSee('ស្វែងរកវគ្គសិក្សា');
    }

    public function test_public_course_has_real_student_enrollment_action_and_resume_link(): void
    {
        $course = $this->publishedCourse();
        $student = User::factory()->create();

        $this->actingAs($student)->get('/courses/'.$course->slug)->assertOk()
            ->assertSee('Request enrollment')
            ->assertSee('action="'.route('account.enrollments.store',$course).'"',false);

        $this->post('/account/courses/'.$course->id.'/enroll')->assertRedirect('/account/enrollments');
        $this->assertDatabaseHas('course_enrollments', [
            'student_id'=>$student->id, 'course_id'=>$course->id, 'status'=>'pending',
        ]);

        $this->get('/courses/'.$course->slug)->assertOk()
            ->assertSee('Enrollment status')
            ->assertDontSee('Request enrollment');

        $enrollment=\App\Models\CourseEnrollment::firstOrFail();
        $enrollment->update(['status'=>'approved']);

        $this->get('/courses/'.$course->slug)->assertOk()
            ->assertSee('Resume learning')
            ->assertSee('href="'.route('account.enrollments.show',$enrollment).'"',false);
    }

    public function test_existing_signup_still_creates_student_only(): void
    {
        $this->post('/register',[
            'name'=>'Student Reader',
            'email'=>'reader@example.test',
            'password'=>'SecureLearn123!',
            'password_confirmation'=>'SecureLearn123!',
            'terms'=>'1',
            'role'=>'admin',
        ])->assertRedirect('/account');

        $this->assertDatabaseHas('users',[
            'email'=>'reader@example.test','role'=>'student',
        ]);
    }
}
