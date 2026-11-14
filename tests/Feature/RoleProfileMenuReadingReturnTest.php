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

    public function test_every_role_gets_its_real_workspace_links_with_icons_on_public_and_account_header(): void
    {
        foreach ([
            'admin' => '/dashboard',
            'instructor' => '/instructor',
            'student' => '/account',
            'developer' => '/developer',
        ] as $role => $dashboard) {
            $user = $this->userWithRole($role);
            $expected = collect(WorkspaceMenu::groups($role))
                ->sum(fn (array $group) => count($group['items']));
            $this->assertGreaterThan(0, $expected);

            foreach (['/?lang=en', $dashboard.'?lang=en'] as $url) {
                $html = $this->actingAs($user)->get($url)->assertOk()
                    ->assertSee('data-profile-role-menu="'.$role.'"', false)
                    ->assertSee('data-profile-role-link', false)
                    ->assertSee('data-profile-dashboard', false)
                    ->assertSee('data-profile-settings', false)
                    ->assertSee('data-profile-link-icon', false)
                    ->assertSee('data-profile-link-icon', false)
                    ->assertSee('action="'.route('logout').'"', false)
                    ->assertSee('data-theme-toggle', false)
                    ->assertSee('href="'.route($user->homeRouteName()).'"', false)
                    ->assertSee('href="'.WorkspaceMenu::profile($role).'"', false)
                    ->getContent();

                $this->assertSame($expected, substr_count($html, 'data-profile-role-link'));
            }
            $this->assertSame($role, $user->role);
        }
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
        $this->get($returnTo)->assertOk()->assertSee('Approved editorial article');
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
