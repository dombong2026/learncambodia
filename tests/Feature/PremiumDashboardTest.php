<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseHomeworkAssignment;
use App\Models\CourseInstructorAssignment;
use App\Models\HomeworkSubmission;
use App\Models\User;
use App\Support\WorkspaceMenu;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PremiumDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $account = User::factory()->create();
        $account->forceFill(['role' => $role])->save();
        return $account;
    }

    private function course(string $slug = 'dashboard-reading'): Course
    {
        if (Category::where('code','GE')->doesntExist()) {
            $this->seed(CategorySeeder::class);
        }

        return Course::create([
            'category_id' => Category::where('code','GE')->firstOrFail()->id,
            'slug' => $slug,
            'title' => $slug === 'dashboard-reading' ? 'Reading Progress Study' : 'Other Students Course',
            'summary' => 'An active published course for testing.',
            'description' => 'Course details for testing.',
            'status' => 'published','published_at' => now(),
        ]);
    }

    public function test_menu_only_displays_registered_working_routes_in_compact_groups(): void
    {
        foreach (['admin','student','instructor','developer'] as $role) {
            $groups = WorkspaceMenu::groups($role);
            $this->assertGreaterThan(0, count($groups));
            $this->assertLessThanOrEqual(7, count($groups));
            foreach ($groups as $group) {
                $this->assertNotEmpty($group['items']);
                foreach ($group['items'] as $item) {
                    $this->assertArrayHasKey('route',$item);
                    $this->assertTrue(Route::has($item['route']),$item['label']);
                    $this->assertStringStartsWith('http',WorkspaceMenu::href($role,$group,$item));
                }
            }
            $this->assertCount(5,WorkspaceMenu::mobileItems($role));
        }

        $this->assertNotNull(WorkspaceMenu::find('admin','advertisement','google-ads'));
        $this->assertFalse(collect(WorkspaceMenu::groups('admin'))
            ->flatMap(fn($group)=>$group['items'])->contains('label','Google Ads'));
        $this->assertFalse(collect(WorkspaceMenu::groups('admin'))
            ->flatMap(fn($group)=>$group['items'])->contains('label','Payment Methods'));
        $this->assertFalse(collect(WorkspaceMenu::groups('student'))
            ->flatMap(fn($group)=>$group['items'])->contains('label','Placement Test'));
    }

    public function test_all_roles_have_a_real_contextual_action_mobile_tabs_and_breadcrumb_outside_header(): void
    {
        $routes = [
            'admin'=>['/dashboard','Create course','/dashboard/course-builder/create'],
            'student'=>['/account','My learning','/account/enrollments'],
            'instructor'=>['/instructor','My courses','/instructor/courses'],
            'developer'=>['/developer','Access tokens','/developer/tokens'],
        ];
        foreach($routes as $role=>[$landing,$label,$action]){
            $response = $this->actingAs($this->user($role))->get($landing)
                ->assertOk()
                ->assertSee($label)
                ->assertSee('aria-label="Breadcrumb"',false)
                ->assertSee('aria-label="Mobile workspace navigation"',false)
                ->assertSee('data-saver-toggle',false)
                ->assertSee('href="'.url($action).'"',false)
                ->assertDontSee('Google Ads');
            $html = $response->getContent();
            $this->assertGreaterThan(strpos($html,'</header>'),strpos($html,'aria-label="Breadcrumb"'));
        }
    }

    public function test_student_progress_ring_and_deadline_are_based_on_owned_records(): void
    {
        $course = $this->course();
        $module = $course->modules()->create(['title'=>'Module','position'=>1]);
        $lesson = $module->lessons()->create([
            'title'=>'First lesson','content'=>'Material one.','position'=>1,'duration_minutes'=>10,
        ]);
        $module->lessons()->create([
            'title'=>'Second lesson','content'=>'Material two.','position'=>2,'duration_minutes'=>10,
        ]);
        $student = $this->user('student');
        $enrollment = CourseEnrollment::create([
            'course_id'=>$course->id,'student_id'=>$student->id,'status'=>'approved',
        ]);
        $enrollment->lessonProgress()->create(['lesson_id'=>$lesson->id,'completed_at'=>now()]);

        $teacher = $this->user('instructor');
        CourseHomeworkAssignment::create([
            'course_id'=>$course->id,'author_id'=>$teacher->id,
            'title'=>'Tomorrow Reading Homework',
            'instructions'=>'Complete the assigned reading questions.',
            'status'=>'published','published_at'=>now(),'due_at'=>now()->addDay(),
        ]);

        $this->actingAs($student)->get('/account')
            ->assertOk()
            ->assertSee('50%')
            ->assertSee('1 / 2')
            ->assertSee('Tomorrow Reading Homework')
            ->assertSee('Resume learning')
            ->assertSee('Reading Progress Study')
            ->assertSee('Study Activity Streak')
            ->assertSee('Study Activity Streak: 1.',false)
            ->assertSee('href="'.route('account.enrollments.show',$enrollment).'"',false);
    }

    public function test_khmer_toggle_changes_dashboard_language_and_keeps_role_route(): void
    {
        $student = $this->user('student');
        $this->actingAs($student)->post('/language',['locale'=>'km'])
            ->assertRedirect('/account?lang=km');
        $this->get('/account')->assertOk()
            ->assertSee('ផ្ទាំងការសិក្សារបស់ខ្ញុំ')
            ->assertSee('ប្រវត្តិរូប និងសុវត្ថិភាព')
            ->assertSee('lang="km"',false)
            ->assertSee('value="en"',false)
            ->assertSee('value="km"',false);

        $this->post('/language',['locale'=>'en'])->assertRedirect('/account?lang=en');
        $this->get('/account')->assertOk()->assertSee('My Learning Dashboard');
    }

    public function test_instructor_queue_is_scoped_to_assigned_courses_only(): void
    {
        $course = $this->course();
        $other = $this->course('other-course');
        $teacher = $this->user('instructor');
        $admin = $this->user('admin');
        $student = $this->user('student');

        CourseInstructorAssignment::create([
            'course_id'=>$course->id,'instructor_id'=>$teacher->id,
            'assigned_by'=>$admin->id,'status'=>'active','assigned_at'=>now(),
        ]);
        foreach([$course,$other] as $current){
            $assignment = CourseHomeworkAssignment::create([
                'course_id'=>$current->id,'author_id'=>$teacher->id,
                'title'=>$current->id===$course->id?'Authorized Grading Item':'Unassigned Private Item',
                'instructions'=>'This record is part of a course.',
                'status'=>'published','published_at'=>now(),
            ]);
            $enrollment = CourseEnrollment::create([
                'course_id'=>$current->id,'student_id'=>$student->id,'status'=>'approved',
            ]);
            HomeworkSubmission::create([
                'assignment_id'=>$assignment->id,'enrollment_id'=>$enrollment->id,
                'body'=>'A valid student response in this course.','version'=>1,
                'status'=>'submitted','submitted_at'=>now(),
            ]);
        }

        $this->actingAs($teacher)->get('/instructor')
            ->assertOk()
            ->assertSee('Authorized Grading Item')
            ->assertDontSee('Unassigned Private Item')
            ->assertSee('Awaiting Grading')
            ->assertSee('Active Students');
    }

    public function test_admin_review_queue_displays_real_pending_course_count(): void
    {
        $course = $this->course();
        $course->update(['status'=>'pending_review','published_at'=>null]);

        $this->actingAs($this->user('admin'))->get('/dashboard')
            ->assertOk()
            ->assertSee('Pending Approvals')
            ->assertSee('Course reviews')
            ->assertSee('href="'.route('admin.reviews.index',['focus'=>'courses']).'"',false);
    }

    public function test_developer_dashboard_uses_actual_available_api_routes(): void
    {
        $developer = $this->user('developer');
        $developer->createToken('Read token',['catalog:read'],now()->addDay());
        $this->actingAs($developer)->get('/developer')->assertOk()
            ->assertSee('Active Tokens')
            ->assertSee('Read-only Endpoints')
            ->assertSee('3')
            ->assertSee('Token Slots Remaining');
    }
}
