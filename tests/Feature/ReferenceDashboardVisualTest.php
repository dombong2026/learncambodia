<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Support\DashboardShowcase;
use App\Support\WorkspaceMenu;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReferenceDashboardVisualTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        return $user;
    }

    public function test_header_structure_is_preserved_and_sidebar_has_fixed_real_settings_link_for_all_roles(): void
    {
        foreach ([
            'admin'=>'/dashboard',
            'student'=>'/account',
            'instructor'=>'/instructor',
            'developer'=>'/developer',
        ] as $role=>$url) {
            $html = $this->actingAs($this->account($role))->get($url)
                ->assertOk()
                ->assertSee('data-dashboard-showcase',false)
                ->assertSee('data-workspace-search',false)
                ->assertSee('data-saver-toggle',false)
                ->assertSee('data-dropdown-trigger',false)
                ->assertSee('name="locale" value="en"',false)
                ->assertSee('name="locale" value="km"',false)
                ->assertSee('name="locale" value="zh"',false)
                ->assertSee('data-sidebar-settings',false)
                ->assertSee('learning-dashboard-navy',false)
                ->getContent();

            $this->assertTrue(strpos($html,'data-sidebar-settings') > strpos($html,'data-workspace-nav'));
            $this->assertTrue(strpos($html,'data-sidebar-settings') < strpos($html,'</aside>'));
            $this->assertTrue(strpos($html,'aria-label="Breadcrumb"') > strpos($html,'</header>'));
            $this->assertTrue(strpos($html,'data-workspace-search') < strpos($html,'</header>'));
            $this->assertStringContainsString(
                'href="'.WorkspaceMenu::profile($role).'"',
                $html
            );
            $this->assertStringNotContainsString('KHQR',$html);
            $this->assertStringNotContainsString('Telegram',$html);
            $this->assertStringNotContainsString('Kouprey Scholar',$html);
        }
    }

    public function test_unique_svg_icons_are_mapped_to_every_visible_sidebar_item(): void
    {
        foreach (['admin','student','instructor','developer'] as $role) {
            $icons = [];
            foreach (WorkspaceMenu::groups($role) as $group) {
                foreach ($group['items'] as $item) {
                    $this->assertArrayHasKey('icon',$item);
                    $this->assertNotSame('file',$item['icon'], $role.' '.$item['label']);
                    $this->assertTrue(Route::has($item['route']));
                    $icons[] = $item['icon'];
                }
            }

            $this->assertCount(count(array_unique($icons)),$icons, $role.' must have distinct item icons.');
        }
    }

    public function test_three_languages_switch_and_keep_permissions_and_real_role_dashboard(): void
    {
        $student = $this->account('student');
        $this->actingAs($student)->post('/language',['locale'=>'zh'])
            ->assertRedirect('/account');

        $this->get('/account')->assertOk()
            ->assertSee('lang="zh"',false)
            ->assertSee('我的学习仪表盘')
            ->assertSee('您好')
            ->assertSee('我的学习')
            ->assertSee('我的作业')
            ->assertSee('data-sidebar-settings',false)
            ->assertSee('aria-label="简体中文"',false);

        $this->get('/dashboard')->assertForbidden();
        $this->post('/language',['locale'=>'km'])->assertRedirect('/account');
        $this->get('/account')->assertOk()
            ->assertSee('lang="km"',false)
            ->assertSee('ផ្ទាំងការសិក្សារបស់ខ្ញុំ')
            ->assertSee('សួស្តី');

        $this->post('/language',['locale'=>'en'])->assertRedirect('/account');
        $this->get('/account')->assertOk()
            ->assertSee('lang="en"',false)
            ->assertSee('My Learning Dashboard')
            ->assertSee('Hello');

        $this->post('/language',['locale'=>'es'])->assertSessionHasErrors('locale');
    }

    public function test_student_golden_donut_uses_real_approved_course_lesson_progress(): void
    {
        $this->seed(CategorySeeder::class);
        $course = Course::create([
            'category_id'=>Category::where('code','GE')->firstOrFail()->id,
            'slug'=>'visual-donut-course',
            'title'=>'Student Visual Donut Course',
            'summary'=>'Approved course for course progress.',
            'description'=>'Learning units for actual course completion.',
            'status'=>'published','published_at'=>now(),
        ]);
        $module = $course->modules()->create(['title'=>'Unit 1','position'=>1]);
        $first = $module->lessons()->create([
            'title'=>'First Lesson','content'=>'Real reading content','position'=>1,'duration_minutes'=>15,
        ]);
        $module->lessons()->create([
            'title'=>'Second Lesson','content'=>'Actual content','position'=>2,'duration_minutes'=>12,
        ]);
        $student = $this->account('student');
        $enrollment = CourseEnrollment::create([
            'course_id'=>$course->id,'student_id'=>$student->id,'status'=>'approved',
        ]);
        $enrollment->lessonProgress()->create(['lesson_id'=>$first->id,'completed_at'=>now()]);

        $this->actingAs($student)->get('/account')
            ->assertOk()
            ->assertSee('Student Visual Donut Course')
            ->assertSee('50%')
            ->assertSee('1 / 2')
            ->assertSee('stroke="#f7bb42"',false)
            ->assertSee('href="'.route('account.enrollments.show',$enrollment).'"',false);
    }

    public function test_percentage_without_denominator_is_unavailable_not_fabricated_zero(): void
    {
        $this->assertNull(DashboardShowcase::percent(0,0));
        $this->assertSame(0,DashboardShowcase::percent(0,25));
        $this->assertSame(50,DashboardShowcase::percent(5,10));
        $this->assertSame(100,DashboardShowcase::percent(500,100));
        $this->actingAs($this->account('admin'))->get('/dashboard')
            ->assertOk()->assertSee('—')
            ->assertSee('School performance overview')
            ->assertSee('Approved Enrollments');
    }

    public function test_chinese_is_available_for_instructor_admin_and_developer_workspaces(): void
    {
        foreach ([
            'admin'=>['/dashboard','学校仪表盘'],
            'instructor'=>['/instructor','教学仪表盘'],
            'developer'=>['/developer','开发者仪表盘'],
        ] as $role=>[$url,$expected]) {
            $this->flushSession();
            $this->actingAs($this->account($role))->post('/language',[
                'locale'=>'zh',
            ])->assertRedirect($url);
            $this->get($url)->assertOk()->assertSee($expected)
                ->assertSee('lang="zh"',false)
                ->assertSee('数据',false);
        }
    }
}
