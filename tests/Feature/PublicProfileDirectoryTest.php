<?php

namespace Tests\Feature;

use App\Models\ApprovalEvent;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseInstructorAssignment;
use App\Models\PublicProfile;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CefrLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicProfileDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>$role])->save();

        return $user;
    }

    private function saveProfile(User $user, string $role, array $overrides=[]): PublicProfile
    {
        $uri=$role==='instructor' ? '/instructor' : '/account';
        $data=[
            'display_name'=>'Learning Member',
            'headline'=>'Independent English study',
            'subjects'=>'English, CEFR',
            'city'=>'Battambang',
            'bio'=>'I enjoy independent learning and teaching language skills.',
            'public_consent'=>'1',
        ];
        if($role==='student') $data['adult_confirmation']='1';

        $this->actingAs($user)->post($uri.'/public-profile',array_replace($data,$overrides))
            ->assertRedirect();

        return PublicProfile::query()->where('user_id',$user->id)->firstOrFail();
    }

    private function approve(PublicProfile $profile, User $reviewer): void
    {
        $uri=$profile->kind==='instructor' ? '/instructor' : '/account';
        $this->actingAs($profile->user)->post($uri.'/public-profile/submit')
            ->assertRedirect();
        $this->actingAs($reviewer)->post('/dashboard/public-profiles/'.$profile->id.'/review',[
            'decision'=>'approve',
        ])->assertRedirect();
    }

    public function test_public_directories_have_no_fabricated_profiles_and_student_profiles_are_disabled(): void
    {
        $this->get('/instructors')->assertOk()
            ->assertSee('Meet our instructors')
            ->assertSee('No instructors have completed');
        $this->get('/students')->assertOk()
            ->assertSee('Student public profiles are disabled')
            ->assertSee('0 profiles');
        $this->get('/students/1')->assertNotFound();
        $this->get('/instructors/1')->assertNotFound();
    }

    public function test_approved_instructor_profile_shows_only_public_fields_and_real_assigned_courses(): void
    {
        $this->seed([CategorySeeder::class,CefrLevelSeeder::class]);
        $admin=$this->person('admin');
        $instructor=$this->person('instructor');
        $instructor->update(['name'=>'Private Legal Full Name']);
        $course=Course::create([
            'category_id'=>Category::where('code','GE')->firstOrFail()->id,
            'slug'=>'verified-course-for-public-teacher',
            'title'=>'Real Public Teacher Course','summary'=>'This course is approved.',
            'description'=>'Reviewed educational curriculum.','status'=>'published',
            'published_at'=>now(),'cefr_level'=>'A1','delivery_mode'=>'online',
        ]);
        CourseInstructorAssignment::create([
            'course_id'=>$course->id,'instructor_id'=>$instructor->id,
            'assigned_by'=>$admin->id,'status'=>'active','assigned_at'=>now(),
        ]);

        $profile=$this->saveProfile($instructor,'instructor',[
            'display_name'=>'Published Teacher Profile',
            'headline'=>'Educational instructor · English',
            'bio'=>'Public biography that the author consented to share.',
        ]);

        $this->get('/instructors')->assertOk()->assertDontSee('Published Teacher Profile');
        $this->get('/instructors/'.$profile->id)->assertNotFound();
        $this->actingAs($instructor)->get('/instructor/public-profile')->assertOk()
            ->assertSee('Public profile &amp; consent',false);

        $this->post('/instructor/public-profile/submit')->assertRedirect();
        $this->get('/instructors')->assertOk()->assertDontSee('Published Teacher Profile');

        $this->actingAs($admin)
            ->get('/dashboard/public-profiles')->assertOk()
            ->assertSee('Published Teacher Profile');
        $this->post('/dashboard/public-profiles/'.$profile->id.'/review',[
            'decision'=>'approve',
        ])->assertRedirect();

        $this->get('/instructors')->assertOk()->assertSee('Published Teacher Profile');
        $this->get('/instructors/'.$profile->id)->assertOk()
            ->assertSee('Public biography that the author consented')
            ->assertSee('Real Public Teacher Course')
            ->assertDontSee('Private Legal Full Name')
            ->assertDontSee($instructor->email)
            ->assertDontSee('12K+')
            ->assertDontSee('4.9');
        $this->get('/courses/'.$course->slug)->assertOk()
            ->assertSee('Published Teacher Profile');

        $this->assertDatabaseHas('approval_events',[
            'subject_type'=>'public_profile','subject_id'=>$profile->id,
            'action'=>'approved','to_status'=>'published','actor_id'=>$admin->id,
        ]);

        $this->actingAs($instructor)->post('/instructor/public-profile',[
            'display_name'=>'Unapproved Swap','headline'=>'Changed headline','public_consent'=>'1',
        ])->assertStatus(409);
        $this->post('/instructor/public-profile/withdraw')->assertRedirect();
        $this->get('/instructors/'.$profile->id)->assertNotFound();
        $this->get('/instructors')->assertOk()->assertDontSee('Published Teacher Profile');
        $this->assertSame('withdrawn',$profile->fresh()->status);
    }

    public function test_student_profiles_remain_private_even_after_review_until_operator_flag(): void
    {
        $student=$this->person('student');
        $admin=$this->person('admin');
        $profile=$this->saveProfile($student,'student',[
            'display_name'=>'Opted In Adult Learner',
            'show_learning_summary'=>'1',
        ]);

        $this->actingAs($student)->post('/account/public-profile/submit')->assertRedirect();
        $this->actingAs($admin)->post('/dashboard/public-profiles/'.$profile->id.'/review',[
            'decision'=>'approve',
        ])->assertRedirect();
        $this->get('/students')->assertOk()->assertDontSee('Opted In Adult Learner');
        $this->get('/students/'.$profile->id)->assertNotFound();

        config()->set('public_profiles.students_enabled',true);
        $this->get('/students')->assertOk()->assertSee('Opted In Adult Learner');
        $this->get('/students/'.$profile->id)->assertOk()
            ->assertSee('Learning portfolio')
            ->assertDontSee($student->email);
        $this->actingAs($student)->post('/account/public-profile/withdraw')->assertRedirect();
        $this->get('/students/'.$profile->id)->assertNotFound();
    }

    public function test_student_must_attest_adulthood_and_consent_before_submission(): void
    {
        $student=$this->person('student');
        $this->actingAs($student)->post('/account/public-profile',[
            'display_name'=>'Under Review','headline'=>'My public story',
            'public_consent'=>'1',
        ])->assertSessionHasErrors('adult_confirmation');
        $this->assertDatabaseCount('public_profiles',0);

        $this->post('/account/public-profile',[
            'display_name'=>'Under Review','headline'=>'My public story',
            'adult_confirmation'=>'1',
        ])->assertSessionHasErrors('public_consent');
        $this->assertDatabaseCount('public_profiles',0);
    }

    public function test_unapproved_profile_and_changed_role_are_never_public(): void
    {
        $admin=$this->person('admin');
        $instructor=$this->person('instructor');
        $profile=$this->saveProfile($instructor,'instructor',[
            'display_name'=>'Revoked Instructor Profile',
        ]);
        $this->approve($profile,$admin);
        $this->get('/instructors/'.$profile->id)->assertOk();

        $instructor->forceFill(['role'=>'student'])->save();
        $this->get('/instructors/'.$profile->id)->assertNotFound();
        $this->get('/instructors')->assertOk()->assertDontSee('Revoked Instructor Profile');
    }

    public function test_admin_must_be_separate_reviewer_and_rejected_profile_stays_private(): void
    {
        $admin=$this->person('admin');
        $instructor=$this->person('instructor');
        $profile=$this->saveProfile($instructor,'instructor');

        $this->actingAs($instructor)->post('/instructor/public-profile/submit')->assertRedirect();
        $this->post('/dashboard/public-profiles/'.$profile->id.'/review',[
            'decision'=>'approve',
        ])->assertForbidden();
        $this->actingAs($admin)->post('/dashboard/public-profiles/'.$profile->id.'/review',[
            'decision'=>'reject',
        ])->assertSessionHasErrors('note');
        $this->post('/dashboard/public-profiles/'.$profile->id.'/review',[
            'decision'=>'reject','note'=>'Remove detailed personal information.',
        ])->assertRedirect();
        $this->assertSame('rejected',$profile->fresh()->status);
        $this->get('/instructors/'.$profile->id)->assertNotFound();

        $this->actingAs($instructor)->post('/instructor/public-profile',[
            'display_name'=>'Revised Public Teacher',
            'headline'=>'Qualified teaching interest',
            'public_consent'=>'1',
        ])->assertRedirect();
        $this->post('/instructor/public-profile/submit')->assertRedirect();
        $this->actingAs($admin)->post('/dashboard/public-profiles/'.$profile->id.'/review',[
            'decision'=>'approve',
        ])->assertRedirect();
        $this->get('/instructors/'.$profile->id)->assertOk()
            ->assertSee('Revised Public Teacher');
    }

    public function test_role_based_profile_access_is_enforced(): void
    {
        $student=$this->person('student');
        $instructor=$this->person('instructor');
        $admin=$this->person('admin');

        $this->actingAs($student)->get('/instructor/public-profile')->assertForbidden();
        $this->get('/dashboard/public-profiles')->assertForbidden();

        $this->actingAs($instructor)->get('/account/public-profile')->assertForbidden();
        $this->get('/dashboard/public-profiles')->assertForbidden();

        $this->actingAs($admin)->get('/dashboard/public-profiles')->assertOk();
        $this->get('/account/public-profile')->assertForbidden();
    }

    public function test_unsafe_markup_is_rendered_as_text_in_approved_profile(): void
    {
        $admin=$this->person('admin');
        $instructor=$this->person('instructor');
        $profile=$this->saveProfile($instructor,'instructor',[
            'display_name'=>'Template Safe',
            'bio'=>'<script>window.stolen=true</script>',
        ]);
        $this->approve($profile,$admin);
        $this->get('/instructors/'.$profile->id)->assertOk()
            ->assertDontSee('<script>window.stolen=true</script>',false)
            ->assertSee('&lt;script&gt;window.stolen=true&lt;/script&gt;',false);
    }
}
