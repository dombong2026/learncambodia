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

    public function test_public_header_supports_languages_and_auth_pages_have_floating_fields(): void
    {
        $this->seed(CategorySeeder::class);

        $this->get('/')->assertOk()
            ->assertSee('Learn Cambodia')
            ->assertSee('name="locale" value="zh"', false)
            ->assertSee('href="'.route('courses.index').'"', false)
            ->assertSee('href="'.route('articles.index').'"', false);

        $this->get('/login')->assertOk()
            ->assertSee('data-password-toggle', false)
            ->assertSee('class="auth-field-label', false)
            ->assertSee('href="'.route('password.request').'"', false)
            ->assertDontSee('name="locale" value="zh"', false);

        $this->get('/register')->assertOk()
            ->assertSee('register_password_confirmation', false)
            ->assertSee('auth-field-label', false)
            ->assertSee('draft Privacy Notice');

        $this->post('/language', ['locale' => 'zh'])->assertRedirect('/?lang=zh');
        $this->get('/login?lang=zh')->assertOk()->assertSee('欢迎回来');
        $this->get('/register?lang=zh')->assertOk()->assertSee('创建账户');
        $this->get('/courses?lang=zh')->assertOk()->assertSee('探索课程');

        $this->post('/language', ['locale' => 'km'])->assertRedirect('/?lang=km');
        $this->get('/login?lang=km')->assertOk()->assertSee('សូមស្វាគមន៍មកវិញ');
        $this->get('/courses?lang=km')->assertOk()->assertSee('ស្វែងរកវគ្គសិក្សា');
    }

    public function test_public_course_actions_follow_enrollment_and_learning_progress(): void
    {
        $course = $this->publishedCourse();

        $course->forceFill([
            'pricing' => [
                'model' => 'paid',
                'strategy' => 'fixed',
                'currency' => 'USD',
                'base_minor' => 1000,
            ],
        ])->save();

        $student = User::factory()->create([
            'role' => 'student',
            'password' => 'CourseTestPassword123!',
        ]);

        $path = '/courses/'.$course->slug.'?lang=en';
        $signIn = route('login', ['next' => $path]);

        $guest = $this->get($path)->assertOk()
            ->assertSee('href="'.e($signIn).'"', false)
            ->assertSee(trans('detail.enroll', [], 'en'))
            ->assertSee('data-public-share-url', false)
            ->assertDontSee('line-clamp-2 text-sm leading-6', false);

        $document = new \DOMDocument();
        @$document->loadHTML($guest->getContent(), LIBXML_NONET);
        $xpath = new \DOMXPath($document);

        $share = $xpath->query('//input[@data-public-share-url]');

        $this->assertCount(1, $share);
        $this->assertTrue($share->item(0)->hasAttribute('readonly'));
        $this->assertSame('url', $share->item(0)->getAttribute('type'));
        $this->assertSame(
            route('courses.show', [
                $course->getRouteKey(), 'lang' => 'en',
            ]),
            $share->item(0)->getAttribute('value')
        );
        $this->assertSame(
            $course->title,
            $share->item(0)->getAttribute('data-title')
        );

        foreach (['km', 'zh'] as $locale) {
            $localized = $this->get(
                '/courses/'.$course->slug.'?lang='.$locale
            )->assertOk()
                ->assertSee('lang="'.$locale.'"', false);

            $localizedDocument = new \DOMDocument();
            @$localizedDocument->loadHTML(
                $localized->getContent(), LIBXML_NONET
            );

            $localizedXPath = new \DOMXPath($localizedDocument);
            $localizedShare = $localizedXPath->query(
                '//input[@data-public-share-url]'
            );

            $this->assertCount(1, $localizedShare);
            $this->assertTrue(
                $localizedShare->item(0)->hasAttribute('readonly')
            );

            $this->assertSame(
                route('courses.show', [
                    $course->getRouteKey(), 'lang' => $locale,
                ]),
                $localizedShare->item(0)->getAttribute('value'),
                'The shared course URL must preserve '.$locale
            );

            $this->assertSame(
                $course->title,
                $localizedShare->item(0)->getAttribute('data-title')
            );
        }

        // Restore the original English enrollment journey.
        $this->get($path)->assertOk()
            ->assertSee('lang="en"', false);

        $this->get($signIn)->assertOk();

        $this->post('/login', [
            'email' => $student->email,
            'password' => 'CourseTestPassword123!',
        ])->assertRedirect($path);

        $enroll = route('account.enrollments.store', $course);

        $this->get($path)->assertOk()
            ->assertSee(trans('detail.enroll', [], 'en'))
            ->assertSee('action="'.$enroll.'"', false)
            ->assertSee('name="pricing_quote"', false);

        $quote = json_encode(
            \App\Support\CoursePricing::quote($course->pricing),
            JSON_THROW_ON_ERROR
        );

        $this->post($enroll, [
            'pricing_quote' => $quote,
        ])->assertRedirect(route('account.enrollments.index'));

        $enrollment = \App\Models\CourseEnrollment::query()
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)->sole();

        $this->assertSame('pending', $enrollment->status);

        $this->get($path)->assertOk()
            ->assertSee(trans('detail.enrollment_pending', [], 'en'))
            ->assertDontSee('action="'.$enroll.'"', false);

        $this->get(route('account.enrollments.show', $enrollment))
            ->assertForbidden();

        $this->post($enroll, [
            'pricing_quote' => $quote,
        ])->assertStatus(409);

        $reviewer = User::factory()->create(['role' => 'admin']);

        $reviewUrl = '/dashboard/reviews/enrollments/'.$enrollment->id;

        $this->actingAs($reviewer)
            ->postJson($reviewUrl, ['decision' => 'approve'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pricing_reference');

        $this->assertSame(
            'pending', $enrollment->fresh()->status
        );

        $this->post($reviewUrl, [
            'decision' => 'approve',
            'pricing_reference' => 'QA-PAID-COURSE-001',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            'approved', $enrollment->fresh()->status
        );

        $this->actingAs($student);

        $study = route('account.enrollments.show', [
            $enrollment, 'lang' => 'en',
        ]);

        $progress = route('account.progress.index', [
            'enrollment' => $enrollment->id,
        ]);

        $this->get($path)->assertOk()
            ->assertSee(trans('detail.start_course', [], 'en'))
            ->assertSee('href="'.e($study).'"', false)
            ->assertDontSee('action="'.$enroll.'"', false)
            ->assertDontSee($progress);

        $this->get($study)->assertOk();

        $this->assertNotNull($enrollment->fresh()->started_at);
        $this->assertDatabaseCount('lesson_progress', 0);

        $this->get($path)->assertOk()
            ->assertSee(trans('detail.learning_progress', [], 'en'))
            ->assertSee('href="'.e($progress).'"', false)
            ->assertSee('href="'.e($study).'"', false);

        $this->get($progress)->assertOk()
            ->assertSee($course->title);

        $other = User::factory()->create(['role' => 'student']);

        $this->actingAs($other)->get($progress)->assertOk()
            ->assertDontSee($course->title);

        $this->get($study)->assertNotFound();

        $this->get($path)->assertOk()
            ->assertSee('action="'.$enroll.'"', false)
            ->assertSee('name="pricing_quote"', false);

        $this->assertDatabaseCount('course_enrollments', 1);
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
