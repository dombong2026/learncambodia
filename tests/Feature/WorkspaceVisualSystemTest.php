<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkspaceVisualSystemTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role): User
    {
        $member = User::factory()->create();
        $member->forceFill(['role' => $role])->save();

        return $member;
    }

    public function test_shared_shell_reaches_every_authorized_role_without_changing_navigation(): void
    {
        foreach ([
            'admin' => '/dashboard',
            'student' => '/account',
            'instructor' => '/instructor',
            'developer' => '/developer',
        ] as $role => $url) {
            $response = $this->actingAs($this->member($role))->get($url)->assertOk()
                ->assertSee('data-workspace-main', false)
                ->assertSee('data-workspace-heading', false)
                ->assertSee('data-workspace-content', false)
                ->assertSee('id="workspace-search"', false)
                ->assertSee('id="workspace-search-mobile"', false)
                ->assertSee('data-workspace-nav', false)
                ->assertSee('aria-label="Mobile workspace navigation"', false);
            $this->assertSame(2, substr_count($response->getContent(), 'data-workspace-search type="search"'));
        }
    }

    public function test_public_information_pages_share_chrome_and_keep_unapproved_claims_out(): void
    {
        foreach (['faq', 'partners', 'placement', 'scores', 'careers', 'terms', 'privacy'] as $page) {
            $this->get(route('information.show', $page))->assertOk()
                ->assertSee('data-information-page="'.$page.'"', false)
                ->assertSee('class="information-page', false)
                ->assertSee('Explore categories')
                ->assertSee('View CEFR levels')
                ->assertSee('Learn Cambodia');
        }

        $this->get(route('information.show','terms'))->assertOk()
            ->assertSee('preliminary draft and requires legal review');
        $this->get(route('information.show','privacy'))->assertOk()
            ->assertSee('preliminary draft and requires legal review');
        $this->get(route('information.show','faq'))->assertDontSee('preliminary draft and requires legal review');
        $this->get(route('information.show','invalid-page'))->assertNotFound();
    }

    public function test_empty_course_workspaces_offer_only_real_authorized_actions(): void
    {
        $this->actingAs($this->member('admin'))->get(route('admin.courses.index'))->assertOk()
            ->assertSee('data-workspace-empty-state', false)
            ->assertSee('No courses yet.')
            ->assertSee('href="'.route('admin.course-builder.create').'"', false);

        $student = $this->member('student');
        $this->actingAs($student)->get(route('account.courses.index'))->assertOk()
            ->assertSee('No courses have passed academic review')
            ->assertSee('href="'.route('categories.index').'"', false)
            ->assertSee('data-workspace-empty-state', false);
        $this->actingAs($student)->get(route('account.saved-courses.index'))->assertOk()
            ->assertSee('data-workspace-empty-state', false)
            ->assertSee('href="'.route('account.courses.index').'"', false);
    }

    public function test_information_labels_are_translated_without_changing_legal_review_status(): void
    {
        foreach (['en', 'km', 'zh'] as $locale) {
            app()->setLocale($locale);
            $this->get(route('information.show','privacy'))->assertOk()
                ->assertSee(__('ui.information'))
                ->assertSee(__('ui.draft_legal_notice'))
                ->assertSee(__('ui.page_overview'));
        }
    }
}
