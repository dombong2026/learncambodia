<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardReviewCardTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        return $user;
    }

    public function test_admin_review_cards_keep_real_counts_icons_and_links_without_redundant_copy(): void
    {
        $this->seed(CategorySeeder::class);
        Course::create([
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'slug' => 'pending-review-card-test',
            'title' => 'Pending review card test',
            'summary' => 'A real pending record for the review count.',
            'description' => 'Course description.',
            'status' => 'pending_review',
        ]);

        $html = $this->actingAs($this->account('admin'))->get('/dashboard?lang=en')
            ->assertOk()->assertSee('data-dashboard-card-grid', false)
            ->assertDontSee(__('dashboard.review_note'))
            ->getContent();

        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $rows = $xpath->query('//*[@data-dashboard-review-row]');
        $this->assertCount(4, $rows);
        $this->assertSame(3, $xpath->query('//*[@data-dashboard-card-grid]/section')->length);

        foreach (['Course reviews', 'Enrollment reviews', 'Homework reviews', 'Appearance reviews'] as $index => $label) {
            $row = $rows->item($index);
            $this->assertStringContainsString($label, $row->textContent);
            $this->assertStringNotContainsString(__('dashboard.awaiting_review'), $row->textContent);
            $this->assertSame(1, $xpath->query('.//*[local-name()="svg"]', $row)->length);
            $this->assertSame($index === 0 ? '1' : '0', trim($xpath->query('.//*[@data-dashboard-review-count]', $row)->item(0)->textContent));
            $this->assertStringStartsWith(url('/dashboard'), $row->getAttribute('href'));
        }

        $this->assertSame(route('admin.reviews.index', ['focus' => 'courses']), $rows->item(0)->getAttribute('href'));
    }

    public function test_other_roles_keep_their_existing_timeline_and_shared_card_grid(): void
    {
        foreach (['student' => '/account', 'instructor' => '/instructor', 'developer' => '/developer'] as $role => $url) {
            $this->actingAs($this->account($role))->get($url.'?lang=en')
                ->assertOk()->assertSee('data-dashboard-card-grid', false)
                ->assertDontSee('data-dashboard-review-row', false)
                ->assertDontSee(__('dashboard.review_note'));
        }
    }
}
