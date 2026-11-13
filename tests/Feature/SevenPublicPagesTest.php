<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\CourseInstructorAssignment;
use App\Models\CourseLesson;
use App\Models\PublicProfile;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CefrLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SevenPublicPagesTest extends TestCase
{
    use RefreshDatabase;

    private function course(string $slug, string $title, string $categoryCode='GE', string $mode='online'): Course
    {
        return Course::create([
            'category_id'=>Category::where('code',$categoryCode)->firstOrFail()->id,
            'slug'=>$slug,'title'=>$title,
            'summary'=>'Reviewed learning introduction for '.$title,
            'description'=>'Published course objectives. Private lessons require enrollment.',
            'cefr_level'=>'A1','delivery_mode'=>$mode,
            'status'=>'published','published_at'=>now(),
        ]);
    }

    private function approvedPerson(string $kind,string $display): PublicProfile
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>$kind])->save();
        $profile=PublicProfile::create([
            'user_id'=>$user->id,'kind'=>$kind,
            'display_name'=>$display,'headline'=>'Learning and teaching profile',
            'bio'=>'A voluntarily shared and reviewed educational biography.',
            'subjects'=>'English, language learning',
        ]);
        $profile->forceFill([
            'status'=>'published',
            'consented_at'=>now(),'published_at'=>now(),
            'adult_confirmed_at'=>$kind==='student' ? now() : null,
        ])->save();

        return $profile;
    }

    public function test_all_seven_public_pages_render_with_existing_global_chrome(): void
    {
        $this->seed([CategorySeeder::class,CefrLevelSeeder::class]);
        $course=$this->course('seven-pages-course','Learning Seven Pages');
        $teacher=$this->approvedPerson('instructor','Published Educator');
        $learner=$this->approvedPerson('student','Consenting Adult Learner');
        $admin=User::factory()->create();
        $admin->forceFill(['role'=>'admin'])->save();

        CourseInstructorAssignment::create([
            'course_id'=>$course->id,'instructor_id'=>$teacher->user_id,
            'assigned_by'=>$admin->id,'status'=>'active','assigned_at'=>now(),
        ]);
        config()->set('public_profiles.students_enabled',true);

        foreach([
            ['/', 'Study corner'],
            ['/courses','Explore courses and study subjects'],
            ['/courses/'.$course->slug,'Learning Seven Pages'],
            ['/instructors','Meet our instructors'],
            ['/instructors/'.$teacher->id,'Published Educator'],
            ['/students','Student life & learning journeys'],
            ['/students/'.$learner->id,'Consenting Adult Learner'],
        ] as [$url,$heading]) {
            $this->get($url)->assertOk()
                ->assertSee($heading)
                ->assertSee('Learn Cambodia')
                ->assertSee('aria-label="Learn Cambodia home"',false)
                ->assertSee('© '.date('Y').' Learn Cambodia');
        }
    }

    public function test_home_public_numbers_are_actual_record_counts_not_reference_placeholders(): void
    {
        $this->seed([CategorySeeder::class,CefrLevelSeeder::class]);
        $course=$this->course('actual-count-course','Approved Real Course');

        $owner=User::factory()->create();
        ContentEntry::create([
            'type'=>'post','slug'=>'actual-study-article','title'=>'Actual Published Article',
            'summary'=>'Approved study advice','body'=>'Study frequently.',
            'status'=>'published','created_by'=>$owner->id,
        ]);

        $this->get('/')->assertOk()
            ->assertSee('Approved Real Course')
            ->assertSee('Actual Published Article')
            ->assertSee('Published courses')
            ->assertDontSee('100K+')
            ->assertDontSee('500+ Educators')
            ->assertDontSee('50K+');
        $this->assertDatabaseCount('courses',1);
    }

    public function test_catalog_search_and_subject_group_and_mode_filters_use_real_courses(): void
    {
        $this->seed([CategorySeeder::class,CefrLevelSeeder::class]);
        $course=$this->course('real-general-english','Real General English','GE','online');
        $other=$this->course('real-exam-course','Real Exam Preparation','IELTS','onsite');
        Course::create([
            'category_id'=>$course->category_id,
            'slug'=>'not-reviewed',
            'title'=>'Confidential Course Draft',
            'summary'=>'Unpublished information','status'=>'draft',
        ]);

        $this->get('/courses?group=english-courses&mode=online')->assertOk()
            ->assertSee($course->title)
            ->assertDontSee($other->title)
            ->assertDontSee('Confidential Course Draft');

        $this->get('/courses?group=exam-preparation&mode=onsite')->assertOk()
            ->assertSee($other->title)
            ->assertDontSee($course->title);

        $this->get('/courses?q=Real+General')->assertOk()
            ->assertSee($course->title)
            ->assertDontSee($other->title);

        $this->get('/courses?group=not-a-real-category')->assertSessionHasErrors('group');
        $this->get('/courses?mode=unknown')->assertSessionHasErrors('mode');
    }

    public function test_single_course_only_shows_public_module_outline_and_approved_instructor(): void
    {
        $this->seed([CategorySeeder::class,CefrLevelSeeder::class]);
        $course=$this->course('curriculum-only','Curriculum Outline Course');
        $module=$course->modules()->create(['title'=>'Public Module Title','position'=>1]);
        $module->lessons()->create([
            'title'=>'Nonpublic Lesson Title',
            'content'=>'Highly private lesson content and protected assessment',
            'position'=>1,'duration_minutes'=>15,
        ]);
        $instructor=$this->approvedPerson('instructor','Consenting Course Instructor');
        $admin=User::factory()->create();
        CourseInstructorAssignment::create([
            'course_id'=>$course->id,'instructor_id'=>$instructor->user_id,
            'assigned_by'=>$admin->id,'status'=>'active','assigned_at'=>now(),
        ]);

        $this->get('/courses/'.$course->slug)->assertOk()
            ->assertSee('Public Module Title')
            ->assertSee('Consenting Course Instructor')
            ->assertSee('View course outline')
            ->assertDontSee('Highly private lesson content')
            ->assertDontSee('Nonpublic Lesson Title')
            ->assertDontSee('Download Syllabus PDF')
            ->assertDontSee('Scan KHQR');
    }

    public function test_three_languages_render_headings_for_new_pages(): void
    {
        $this->seed(CategorySeeder::class);
        $this->post('/language',['locale'=>'zh'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('学习角与 CEFR 等级');
        $this->get('/courses')->assertOk()->assertSee('探索课程和学习科目');
        $this->get('/instructors')->assertOk()->assertSee('认识我们的教师');
        $this->get('/students')->assertOk()->assertSee('学生生活与学习历程');

        $this->post('/language',['locale'=>'km'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('មគ្គុទ្ទេសក៍សិក្សា');
        $this->get('/courses')->assertOk()->assertSee('ស្វែងរកវគ្គសិក្សា និងមុខវិជ្ជា');
        $this->get('/instructors')->assertOk()->assertSee('ស្គាល់គ្រូបង្រៀនរបស់យើង');
        $this->get('/students')->assertOk()->assertSee('ជីវិតសិស្ស និងដំណើរសិក្សា');
    }
}
