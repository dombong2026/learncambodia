<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\User;
use App\Support\ReadingReturn;
use App\Support\WorkspaceMenu;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RoleProfileMenuReadingReturnTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['password' => 'MemberPassword123!']);
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function publishedArticle(): ContentEntry
    {
        $author = $this->userWithRole('admin');

        return ContentEntry::create([
            'type' => 'post',
            'slug' => 'verified-reading-guide',
            'title' => 'Verified reading guide',
            'summary' => 'A real public reading guide',
            'body' => 'An approved article for language learners.',
            'status' => 'published',
            'created_by' => $author->id,
        ]);
    }

    private function examCourse(): Course
    {
        $this->seed(CategorySeeder::class);
        $course = Course::create([
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'slug' => 'reading-exam-alpha',
            'title' => 'Reading examination',
            'summary' => 'Public exam information',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $quiz = $course->quizzes()->create([
            'title' => 'Reading quiz',
            'position' => 1,
            'passing_percent' => 70,
        ]);
        $quiz->questions()->create([
            'prompt' => 'Choose the greeting',
            'option_a' => 'Hello',
            'option_b' => 'Goodbye',
            'option_c' => 'Blue',
            'option_d' => 'Green',
            'correct_option' => 'A',
            'position' => 1,
        ]);

        return $course;
    }

    public function test_guest_dropdown_has_svg_icons_and_working_login_and_register_links(): void
    {
        $html = $this->get('/?lang=en')->assertOk()
            ->assertSee('data-profile-sign-in', false)
            ->assertSee('data-profile-register', false)
            ->assertSee('data-profile-sign-in-icon', false)
            ->assertSee('data-profile-register-icon', false)
            ->assertSee('data-theme-toggle', false)
            ->getContent();

        $this->assertSame(0, substr_count($html, 'data-profile-role-link'));
        $this->assertStringContainsString('href="'.route('login').'"', $html);
        $this->assertStringContainsString('href="'.route('register').'"', $html);
    }

    public function test_every_role_gets_curated_unique_workspace_links_on_public_and_account_header(): void
    {
        $menus = [
            'admin' => ['/dashboard', [
                ['admin.courses.index'], ['admin.exams.index'], ['admin.quizzes-manager.index'],
                ['admin.reviews.index', ['focus' => 'courses']],
                ['admin.reviews.index', ['focus' => 'enrollments']],
                ['admin.homework.reviews'], ['admin.users.index'],
            ]],
            'student' => ['/account', [
                ['account.enrollments.index'], ['account.courses.index'], ['account.saved-courses.index'],
                ['account.homework.index'], ['account.progress.index'], ['account.practice.index'],
            ]],
            'instructor' => ['/instructor', [
                ['instructor.courses.index'], ['instructor.homework.index'], ['instructor.grading.index'],
            ]],
            'developer' => ['/developer', [
                ['developer.apis'], ['developer.tokens.index'], ['developer.usage'],
            ]],
        ];
        $menus['superadmin'] = $menus['admin'];

        foreach ($menus as $role => [$dashboard, $links]) {
            $user = $this->userWithRole($role);
            $menuRole = $role === 'superadmin' ? 'admin' : $role;

            foreach (['/?lang=en', $dashboard.'?lang=en'] as $url) {
                $html = $this->actingAs($user)->get($url)->assertOk()
                    ->assertSee('data-profile-role-menu="'.$menuRole.'"', false)
                    ->assertSee('data-profile-dashboard', false)
                    ->assertDontSee('data-profile-settings', false)
                    ->assertDontSee('data-profile-nav-group', false)
                    ->assertSee('action="'.route('logout').'"', false)
                    ->assertSee('data-theme-toggle', false)
                    ->getContent();

                $document = new \DOMDocument();
                $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new \DOMXPath($document);
                $menu = $xpath->query('//*[@data-profile-role-menu]')->item(0);
                $this->assertNotNull($menu);
                $rows = $xpath->query('.//*[@data-profile-role-link]', $menu);
                $this->assertCount(count($links), $rows);
                $actual = [];
                foreach ($rows as $row) {
                    $this->assertSame(1, $xpath->query('.//*[local-name()="svg"]', $row)->length);
                    $actual[] = $row->getAttribute('href');
                }
                $this->assertSame(array_map(fn ($link) => route($link[0], $link[1] ?? []), $links), $actual);
                $this->assertSame(array_values(array_unique($actual)), $actual);
                $this->assertSame(route($user->homeRouteName()), $xpath->query('.//*[@data-profile-dashboard]', $menu)->item(0)->getAttribute('href'));
                $this->assertNotContains(WorkspaceMenu::profile($menuRole), $actual);

                if ($url !== '/?lang=en') {
                    $sidebar = $xpath->query('//*[@data-sidebar]')->item(0);
                    $this->assertNotNull($sidebar);
                    $this->assertSame(WorkspaceMenu::profile($menuRole), $xpath->query('.//*[@data-sidebar-settings]', $sidebar)->item(0)->getAttribute('href'));
                    $this->assertGreaterThan(count($links), $xpath->query('.//*[@data-workspace-link]', $sidebar)->length);
                }
            }
            $this->assertSame($role, $user->fresh()->role);
        }
    }

    public function test_unverified_member_keeps_required_email_verification_action(): void
    {
        $user = $this->userWithRole('student');
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user)->get('/?lang=en')->assertOk()
            ->assertSee('href="'.route('verification.notice').'"', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('action="'.route('logout').'"', false);
    }

    public function test_successful_login_defaults_to_the_correct_role_dashboard_without_reading_context(): void
    {
        foreach (['student', 'instructor', 'admin', 'developer'] as $role) {
            $user = $this->userWithRole($role);
            $this->get('/login?lang=en')->assertOk();

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'MemberPassword123!',
            ])->assertRedirect(route($user->homeRouteName()));

            $this->assertAuthenticatedAs($user);
            $this->post('/logout')->assertRedirect(route('home'));
        }
    }

    public function test_login_from_published_article_returns_to_same_public_article(): void
    {
        $article = $this->publishedArticle();
        $student = $this->userWithRole('student');
        $returnTo = '/articles/'.$article->slug.'?lang=zh';
        $link = route('login', ['next' => $returnTo]);

        $this->get($returnTo)->assertOk()->assertSee(e($link), false)
            ->assertSee('data-profile-sign-in', false);
        $this->get($link)->assertOk()->assertSee(e(route('register', ['next' => $returnTo])), false);

        $this->post('/login', [
            'email' => $student->email,
            'password' => 'MemberPassword123!',
        ])->assertRedirect($returnTo);

        $this->assertAuthenticatedAs($student);
        $this->get($returnTo)->assertOk()->assertSee('data-public-document="post"', false);
    }

    public function test_exam_sign_in_preserves_public_exam_while_private_answers_remain_protected(): void
    {
        $course = $this->examCourse();
        $student = $this->userWithRole('student');
        $returnTo = '/exam-catalog/'.$course->slug.'?lang=en';
        $signInUrl = route('login', ['next' => $returnTo]);

        $this->get($returnTo)->assertOk()->assertSee(e($signInUrl), false)
            ->assertSee('Sign in to learn');

        $this->get($signInUrl)->assertOk();
        $this->post('/login', [
            'email' => $student->email,
            'password' => 'MemberPassword123!',
        ])->assertRedirect($returnTo);
        $this->get($returnTo)->assertOk()->assertSee('Secure assessment access')
            ->assertDontSee('name="correct_option"', false);
    }

    public function test_registration_from_reading_context_returns_to_article_and_keeps_student_role(): void
    {
        $article = $this->publishedArticle();
        $target = '/articles/'.$article->slug.'?lang=en';

        $this->get(route('register', ['next' => $target]))->assertOk()
            ->assertSee(e(route('login', ['next' => $target])), false);
        $this->post('/register', [
            'name' => 'New Student Reader',
            'email' => 'new-student-reader@example.test',
            'password' => 'ValidSecurePassword123!',
            'password_confirmation' => 'ValidSecurePassword123!',
            'terms' => '1',
            'role' => 'admin',
        ])->assertRedirect($target);

        $this->assertDatabaseHas('users', ['email' => 'new-student-reader@example.test', 'role' => 'student']);
    }

    public function test_external_or_unauthorized_login_targets_cannot_override_role_dashboard(): void
    {
        $student = $this->userWithRole('student');
        foreach ([
            'https://example.org/phish',
            '//example.org/phish',
            '/dashboard',
            '/account/notes',
            '/articles/../../dashboard',
            '/articles/valid?next=https://example.org',
            '/articles/%2e%2e/dashboard',
        ] as $unsafe) {
            $this->assertNull(ReadingReturn::valid($unsafe));
        }

        $this->get(route('login', ['next' => 'https://example.org/phish']))->assertOk();
        $this->post('/login', [
            'email' => $student->email,
            'password' => 'MemberPassword123!',
        ])->assertRedirect(route('account.overview'));
    }

    public function test_return_destination_is_cleared_when_a_standard_login_is_opened(): void
    {
        $article = $this->publishedArticle();
        $user = $this->userWithRole('student');
        $this->get(route('login', ['next' => '/articles/'.$article->slug.'?lang=en']))->assertOk();
        $this->get('/login?lang=en')->assertOk();
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'MemberPassword123!',
        ])->assertRedirect(route('account.overview'));
    }
}
