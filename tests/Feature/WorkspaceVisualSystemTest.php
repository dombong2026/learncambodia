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
        foreach (['faq', 'partners', 'scores', 'careers'] as $page) {
            $this->get(route('information.show', $page).'?lang=en')
                ->assertOk()
                ->assertSee('data-information-page="'.$page.'"', false)
                ->assertSee('class="information-page', false)
                ->assertSee('href="'.route('categories.index').'"', false)
                ->assertSee('href="'.route('cefr.index').'"', false)
                ->assertSee('Learn Cambodia')
                ->assertDontSee(trans('ui.draft_legal_notice', [], 'en'));
        }

        // Placement now has a dedicated public module.
        $this->assertSame(
            route('information.show', 'placement'),
            route('placement.index')
        );

        $this->get(route('placement.index').'?lang=en')
            ->assertOk()
            ->assertSee('English Placement Tests')
            ->assertSee('id="main-content"', false)
            ->assertSee('Learn Cambodia')
            ->assertDontSee(
                trans('ui.draft_legal_notice', [], 'en')
            );

        $this->assertSame(
            'placement.index',
            \Illuminate\Support\Facades\Route::currentRouteName()
        );

        foreach (['about', 'contact'] as $page) {
            $this->get(route('information.show', $page).'?lang=en')
                ->assertOk()
                ->assertSee('id="main-content"', false)
                ->assertSee('aria-label="Breadcrumb"', false)
                ->assertSee(\App\Support\Appearance::value($page, 'heading'))
                ->assertSee('Learn Cambodia');
        }

        // Synthetic fixtures in the isolated test database.
        $admin = $this->member('admin');

        foreach (['terms', 'privacy'] as $page) {
            $policy = \App\Models\ContentEntry::query()
                ->firstOrNew(['type' => 'policy', 'slug' => $page]);

            $policy->forceFill([
                'title' => 'QA reviewed '.$page.' policy',
                'body' => 'Synthetic reviewed policy text.',
                'status' => 'draft',
                'created_by' => $admin->id,
            ])->save();

            $url = route('information.show', $page);

            $this->get($url)->assertOk()
                ->assertSee(trans('ui.draft_legal_notice', [], 'en'))
                ->assertDontSee($policy->title)
                ->assertDontSee('Synthetic reviewed policy text.');

            $this->get($policy->publicUrl())->assertNotFound();

            $policy->forceFill(['status' => 'published'])->save();

            $this->get($url)->assertRedirect($policy->publicUrl());

            $this->get($policy->publicUrl())->assertOk()
                ->assertSee($policy->title)
                ->assertSee('Synthetic reviewed policy text.');
        }

        $this->get(route('information.show', 'invalid-page'))
            ->assertNotFound();
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
            // The SetLocale middleware honors explicit URL language per request.
            $this->get(route('information.show','privacy').'?lang='.$locale)->assertOk()
                ->assertSee(__('ui.information'))
                ->assertSee(__('ui.draft_legal_notice'))
                ->assertSee(__('ui.page_overview'));
        }
    }
}
