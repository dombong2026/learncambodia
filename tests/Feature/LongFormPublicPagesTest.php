<?php

namespace Tests\Feature;

use App\Models\AppearanceRevision;
use App\Models\Category;
use App\Models\ContactInquiry;
use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\CourseInstructorAssignment;
use App\Models\PublicProfile;
use App\Models\User;
use App\Support\Appearance;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CefrLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LongFormPublicPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>'admin'])->save();
        return $user;
    }

    private function publishedCourse(Category $category,string $slug='long-page-course'): Course
    {
        return Course::create([
            'category_id'=>$category->id,
            'slug'=>$slug,'title'=>'Reviewed Educational Course',
            'summary'=>'Reviewed and approved curriculum',
            'description'=>'Real educational course description.',
            'delivery_mode'=>'online','cefr_level'=>'A1',
            'status'=>'published','published_at'=>now(),
        ]);
    }

    public function test_categories_page_is_one_wide_long_form_page_using_real_published_categories(): void
    {
        $this->seed([CategorySeeder::class,CefrLevelSeeder::class]);
        $category=Category::where('code','GE')->firstOrFail();
        $this->publishedCourse($category);

        Category::create([
            'code'=>'SECRET','slug'=>'hidden-draft-category',
            'name'=>'Hidden Draft Category','description'=>'Not public.',
            'group'=>'english-courses','is_published'=>false,'sort_order'=>998,
        ]);

        $this->get('/categories')->assertOk()
            ->assertSee('Explore learning categories')
            ->assertSee('id="learning-focus"',false)
            ->assertSee('id="category-directory"',false)
            ->assertSee('max-w-[1600px]',false)
            ->assertSee($category->name)
            ->assertDontSee('Hidden Draft Category')
            ->assertDontSee('50,000+')
            ->assertSee('Learn Cambodia home')
            ->assertSee('© '.date('Y').' Learn Cambodia');

        $this->get('/categories?group=english-courses')->assertOk()
            ->assertSee('English Courses');
        $this->get('/categories?group=invalid')->assertSessionHasErrors('group');
        $this->get('/categories?q=Hidden+Draft')->assertOk()
            ->assertDontSee('Hidden Draft Category');
    }

    public function test_single_category_is_one_full_width_page_with_only_real_courses_and_approved_teachers(): void
    {
        $this->seed([CategorySeeder::class,CefrLevelSeeder::class]);
        $category=Category::where('code','GE')->firstOrFail();
        $course=$this->publishedCourse($category);

        $module=$course->modules()->create(['title'=>'Public Curriculum Module','position'=>1]);
        $module->lessons()->create([
            'title'=>'Private Lesson Title','content'=>'Secret lesson answer content',
            'position'=>1,'duration_minutes'=>30,
        ]);

        $instructor=User::factory()->create();
        $instructor->forceFill(['role'=>'instructor'])->save();
        $profile=PublicProfile::create([
            'user_id'=>$instructor->id,'kind'=>'instructor',
            'display_name'=>'Approved Educator Bio',
            'headline'=>'Education for all learners',
        ]);
        $profile->forceFill([
            'status'=>'published','consented_at'=>now(),'published_at'=>now(),
        ])->save();
        $admin=$this->admin();
        CourseInstructorAssignment::create([
            'course_id'=>$course->id,'instructor_id'=>$instructor->id,
            'assigned_by'=>$admin->id,'status'=>'active','assigned_at'=>now(),
        ]);

        $this->get('/categories/'.$category->slug)->assertOk()
            ->assertSee('max-w-[1600px]',false)
            ->assertSee('id="category-courses"',false)
            ->assertSee('Reviewed Educational Course')
            ->assertSee('Approved Educator Bio')
            ->assertSee('Study resources and guidance')
            ->assertDontSee('Secret lesson answer content')
            ->assertDontSee('Private Lesson Title')
            ->assertDontSee('Scan KHQR')
            ->assertDontSee('50,000+');
    }

    public function test_about_and_contact_use_wide_stack_not_split_layout(): void
    {
        $this->seed(CategorySeeder::class);
        $this->get('/information/about')->assertOk()
            ->assertSee('Building accessible learning journeys')
            ->assertSee('id="about-mission"',false)
            ->assertSee('max-w-[1600px]',false)
            ->assertSee('Our educational approach')
            ->assertSee('Learn Cambodia home')
            ->assertDontSee('Accredited by MoEYS');

        $this->get('/information/contact')->assertOk()
            ->assertSee('Contact Learn Cambodia')
            ->assertSee('id="contact-form"',false)
            ->assertSee('action="'.route('contact.store').'"',false)
            ->assertSee('name="consent"',false)
            ->assertSee('max-w-[1600px]',false)
            ->assertDontSee('0863-556-6837');
    }

    public function test_contact_form_requires_explicit_consent_and_validates_content(): void
    {
        $this->post('/contact',[
            'name'=>'Student Support Request',
            'email'=>'learner@example.test','topic'=>'courses',
            'subject'=>'Ask about courses',
            'message'=>'Could I study the current published English curriculum?',
        ])->assertSessionHasErrors('consent');
        $this->assertDatabaseCount('contact_inquiries',0);

        $this->post('/contact',[
            'name'=>'Support Guest','email'=>'not-an-email','topic'=>'invalid',
            'subject'=>'Hi','message'=>'Too short','consent'=>'1',
        ])->assertSessionHasErrors(['email','topic','subject','message']);
        $this->assertDatabaseCount('contact_inquiries',0);
    }

    public function test_contact_form_stores_private_inquiry_only_and_admin_can_review(): void
    {
        $input=[
            'name'=>'Learner Who Needs Help',
            'email'=>'private-learner@example.test',
            'topic'=>'enrollment',
            'subject'=>'Question about enrollment',
            'message'=>'Please clarify how school approval works for course enrollment.',
            'consent'=>'1',
        ];
        $this->post('/contact',$input)->assertRedirect('/information/contact');
        $this->assertDatabaseCount('contact_inquiries',1);
        $this->assertDatabaseHas('contact_inquiries',[
            'topic'=>'enrollment','status'=>'new',
            'email'=>'private-learner@example.test',
        ]);
        $this->get('/information/contact')->assertOk()
            ->assertDontSee('Learner Who Needs Help')
            ->assertDontSee('private-learner@example.test');

        $guest=User::factory()->create();
        $this->actingAs($guest)->get('/dashboard/contact-inquiries')->assertForbidden();

        $admin=$this->admin();
        $inquiry=ContactInquiry::firstOrFail();
        $this->actingAs($admin)->get('/dashboard/contact-inquiries')
            ->assertOk()->assertSee('Question about enrollment')
            ->assertSee('private-learner@example.test');
        $this->patch('/dashboard/contact-inquiries/'.$inquiry->id,[
            'status'=>'closed','review_note'=>'Handled internally.',
        ])->assertRedirect();
        $this->assertDatabaseHas('contact_inquiries',[
            'id'=>$inquiry->id,'status'=>'closed','reviewed_by'=>$admin->id,
        ]);
    }

    public function test_honeypot_submissions_are_not_recorded(): void
    {
        $this->post('/contact',[
            'name'=>'Automated Attempt','email'=>'bot@example.test','topic'=>'general',
            'subject'=>'Request information',
            'message'=>'Automated crawler message body for testing.',
            'consent'=>'1','company_website'=>'https://spam.example',
        ])->assertRedirect('/information/contact');

        $this->assertDatabaseCount('contact_inquiries',0);
    }

    public function test_authors_page_uses_only_published_articles_and_opted_in_identities(): void
    {
        $admin=$this->admin();
        $instructor=User::factory()->create();
        $instructor->forceFill(['role'=>'instructor'])->save();

        ContentEntry::create([
            'type'=>'post','slug'=>'approved-author-work','title'=>'Real Published Teaching Guide',
            'summary'=>'A reviewed educational text','body'=>'Full editorial material.',
            'status'=>'published','created_by'=>$instructor->id,
        ]);
        ContentEntry::create([
            'type'=>'post','slug'=>'private-author-work','title'=>'Secret Editorial Draft',
            'summary'=>'Private summary','body'=>'Private article content.',
            'status'=>'draft','created_by'=>$admin->id,
        ]);

        $this->get('/authors')->assertOk()
            ->assertSee('Educational authors and contributors')
            ->assertSee('Real Published Teaching Guide')
            ->assertDontSee('Secret Editorial Draft')
            ->assertDontSee($instructor->name)
            ->assertDontSee($instructor->email);

        $profile=PublicProfile::create([
            'user_id'=>$instructor->id,'kind'=>'instructor',
            'display_name'=>'Consenting Teaching Author',
            'headline'=>'Reviewed educational writing',
            'bio'=>'Consented public education biography.',
        ]);
        $profile->forceFill([
            'status'=>'published','published_at'=>now(),'consented_at'=>now(),
        ])->save();

        $this->get('/authors')->assertOk()->assertSee('Consenting Teaching Author');
        $this->get('/authors/'.$profile->id)->assertOk()
            ->assertSee('Consented public education biography.')
            ->assertSee('Real Published Teaching Guide')
            ->assertDontSee($instructor->email);

        $profile->forceFill(['status'=>'withdrawn','published_at'=>null])->save();
        $this->get('/authors/'.$profile->id)->assertNotFound();
        $this->get('/authors')->assertOk()
            ->assertDontSee('Consenting Teaching Author')
            ->assertSee('Real Published Teaching Guide');
    }

    public function test_appearance_manager_lists_page_names_and_approved_copy_publishes_only_after_review(): void
    {
        $this->seed(CategorySeeder::class);
        $author=$this->admin();
        $reviewer=$this->admin();

        $this->actingAs($author)->get('/dashboard/appearance')->assertOk()
            ->assertSee('Categories')
            ->assertSee('Single Category')
            ->assertSee('Author')
            ->assertSee('About')
            ->assertSee('Contact');

        foreach(['categories','single_category','author','about','contact'] as $section) {
            $this->get('/dashboard/appearance/'.$section)->assertOk()
                ->assertSee('Save new draft');
        }
        $settings=Appearance::approved('categories');
        $this->post('/dashboard/appearance/categories',array_replace($settings,[
            'heading'=>'Editorially Controlled Category Layout',
        ]))->assertRedirect('/dashboard/appearance/categories');

        $revision=AppearanceRevision::where('section','categories')->firstOrFail();
        $this->get('/categories')->assertOk()
            ->assertDontSee('Editorially Controlled Category Layout');
        $this->post('/dashboard/appearance/revisions/'.$revision->id.'/submit')
            ->assertRedirect();
        $this->post('/dashboard/reviews/appearance/'.$revision->id,[
            'decision'=>'approve',
        ])->assertForbidden();

        $this->actingAs($reviewer)
            ->post('/dashboard/reviews/appearance/'.$revision->id,[
                'decision'=>'approve',
            ])->assertRedirect();
        $this->get('/categories')->assertOk()
            ->assertSee('Editorially Controlled Category Layout');
    }

    public function test_long_pages_have_language_specific_labels(): void
    {
        $this->seed(CategorySeeder::class);

        $this->post('/language',['locale'=>'zh'])->assertRedirect('/');
        $this->get('/categories')->assertOk()->assertSee('课程类别');
        $this->get('/information/about')->assertOk()->assertSee('我们的使命');
        $this->get('/information/contact')->assertOk()->assertSee('提交咨询');
        $this->get('/authors')->assertOk()->assertSee('教育作者');

        $this->post('/language',['locale'=>'km'])->assertRedirect('/');
        $this->get('/categories')->assertOk()->assertSee('ប្រភេទវគ្គសិក្សា');
        $this->get('/information/about')->assertOk()->assertSee('បេសកកម្មរបស់យើង');
        $this->get('/information/contact')->assertOk()->assertSee('ផ្ញើសំណួរ');
        $this->get('/authors')->assertOk()->assertSee('អ្នកនិពន្ធ');
    }
}
