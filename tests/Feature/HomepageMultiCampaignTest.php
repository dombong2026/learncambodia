<?php

namespace Tests\Feature;

use App\Http\Controllers\Workspace\HomepageCampaignManagerController;
use App\Models\HomepageCampaignItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HomepageMultiCampaignTest extends TestCase
{
    use RefreshDatabase;

    private function rows(): array
    {
        return [[
            'course' => 'English Grammar', 'lesson' => 'Simple Present',
            'type' => 'quiz', 'body' => '<p>Choose the best answer.</p>',
            'url' => '', 'asset' => '', 'language' => '', 'feedback' => '',
            'questions' => [[
                'prompt' => 'She ___ every day.',
                'options' => ['go', 'goes', 'going'],
                'correct' => 1,
                'feedback' => 'Third-person singular uses -s.',
            ]],
        ]];
    }

    private function routes(): void
    {
        Route::middleware('web')->group(function () {
            Route::get('/_test/campaigns/editor', fn () => 'Editor')->name('admin.appearance.campaign.edit');
            Route::post('/_test/campaigns/save', [HomepageCampaignManagerController::class, 'save']);
            Route::get('/_test/campaigns/{campaign}/play', [HomepageCampaignManagerController::class, 'play']);
            Route::post('/_test/campaigns/{campaign}/check', [HomepageCampaignManagerController::class, 'check']);
            Route::post('/_test/campaigns/{campaign}/status', [HomepageCampaignManagerController::class, 'status']);
        });
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_multiple_independent_campaign_drafts_do_not_overwrite_each_other(): void
    {
        $this->routes();
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $payload = [
            'version' => 0, 'kind' => 'quiz', 'cover_asset' => null,
            'action' => 'draft', 'rows' => $this->rows(),
        ];
        $this->actingAs($student)->postJson('/_test/campaigns/save',
            $payload + ['title' => 'Student attempt'])->assertForbidden();

        $first = $this->actingAs($admin)->postJson('/_test/campaigns/save',
            $payload + ['title' => 'Grammar Quiz'])->assertOk()->json();
        $second = $this->postJson('/_test/campaigns/save',
            array_replace($payload, ['title' => 'Grammar Exam', 'kind' => 'exam']))->assertOk()->json();
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertDatabaseCount('homepage_campaign_items', 2);
        $this->assertSame('draft', HomepageCampaignItem::findOrFail($first['id'])->status);
        $this->assertSame('Grammar Exam', HomepageCampaignItem::findOrFail($second['id'])->title);
    }

    public function test_published_answers_are_not_revealed_before_checking(): void
    {
        $this->routes();
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = HomepageCampaignItem::create([
            'title' => 'Public Grammar Quiz',
            'slug' => 'public-grammar-quiz',
            'kind' => 'quiz',
            'status' => 'published',
            'published' => $this->rows(),
            'draft' => null, 'version' => 1,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);
        $url = '/_test/campaigns/'.$campaign->slug;
        $this->get($url.'/play')->assertOk()
            ->assertJsonPath('questions.0.prompt', 'She ___ every day.')
            ->assertJsonMissingPath('questions.0.correct')
            ->assertJsonMissingPath('questions.0.feedback');
        $this->postJson($url.'/check', ['row' => 0, 'question' => 0, 'answer' => 1])
            ->assertOk()->assertJsonPath('correct', true)
            ->assertJsonPath('feedback', 'Third-person singular uses -s.');
        $this->postJson($url.'/check', ['row' => 0, 'question' => 0, 'answer' => 0])
            ->assertOk()->assertJsonPath('correct', false)
            ->assertJsonPath('answer', 'goes');
    }

    public function test_saved_draft_does_not_mutate_the_public_campaign_details(): void
    {
        $this->routes();
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = HomepageCampaignItem::create([
            'title' => 'Published English Quiz',
            'slug' => 'published-english-quiz',
            'kind' => 'quiz', 'status' => 'published',
            'published' => $this->rows(), 'draft' => null,
            'version' => 3, 'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->postJson('/_test/campaigns/save', [
            'id' => $campaign->id,
            'version' => 3, 'title' => 'Unpublished draft title',
            'kind' => 'exam', 'cover_asset' => null,
            'action' => 'draft', 'rows' => $this->rows(),
        ])->assertOk()->assertJsonPath('status', 'published');

        $campaign->refresh();
        $this->assertSame('Published English Quiz', $campaign->title);
        $this->assertSame('quiz', $campaign->kind);
        $this->assertSame('Unpublished draft title', $campaign->draft_title);
        $this->assertSame('exam', $campaign->draft_kind);
        $this->assertNotNull($campaign->draft);
        $this->assertNotNull($campaign->published);
    }

    public function test_unpublished_campaign_is_private(): void
    {
        $this->routes();
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = HomepageCampaignItem::create([
            'title' => 'Unpublished', 'slug' => 'unpublished',
            'kind' => 'quiz', 'status' => 'draft',
            'draft' => $this->rows(), 'published' => null,
            'version' => 1, 'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);
        $this->get('/_test/campaigns/unpublished/play')->assertNotFound();
        $this->postJson('/_test/campaigns/unpublished/check', [
            'row'=>0,'question'=>0,'answer'=>1,
        ])->assertNotFound();
    }
}
