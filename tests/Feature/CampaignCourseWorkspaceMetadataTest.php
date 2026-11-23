<?php

namespace Tests\Feature;

use App\Http\Controllers\Workspace\HomepageCampaignManagerController;
use App\Models\HomepageCampaignItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CampaignCourseWorkspaceMetadataTest extends TestCase
{
    use RefreshDatabase;

    private function registerRoutes(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/_test/course-workspace/editor', fn () => 'Course Editor')
                ->name('admin.appearance.campaign.edit');
            Route::post('/_test/course-workspace/save', [HomepageCampaignManagerController::class, 'save']);
        });
        Route::getRoutes()->refreshNameLookups();
    }

    private function payload(): array
    {
        return [
            'version' => 0,
            'title' => 'Intensive IELTS Preparation',
            'kind' => 'quiz',
            'action' => 'draft',
            'cover_asset' => null,
            'rows' => [[
                'course' => 'Introduction to Academic English',
                'lesson' => 'CEFR Reading Comprehension',
                'type' => 'quiz',
                'body' => '<p>Read the text.</p>',
                'cefr' => 'B2',
                'title_km' => 'ការអានយល់អត្ថបទកម្រិត CEFR',
                'body_km' => 'អានអត្ថបទ និងឆ្លើយសំណួរ',
                'url' => '', 'asset' => '', 'language' => '', 'feedback' => '',
                'questions' => [[
                    'prompt' => 'Choose the correct form.',
                    'options' => ['go', 'goes', 'going'],
                    'correct' => 1,
                    'feedback' => 'Third-person singular requires -s.',
                ]],
            ]],
        ];
    }

    public function test_cefr_and_khmer_translation_are_persisted_in_existing_json_draft(): void
    {
        $this->registerRoutes();
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->postJson('/_test/course-workspace/save', $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'draft');

        $campaign = HomepageCampaignItem::findOrFail($response->json('id'));
        $row = $campaign->draft[0];
        $this->assertSame('B2', $row['cefr']);
        $this->assertSame('ការអានយល់អត្ថបទកម្រិត CEFR', $row['title_km']);
        $this->assertSame('អានអត្ថបទ និងឆ្លើយសំណួរ', $row['body_km']);
        $this->assertSame(1, $row['questions'][0]['correct']);
        $this->assertSame('<p>Read the text.</p>', $row['body']);
        $this->assertDatabaseCount('homepage_campaign_items', 1);
    }

    public function test_invalid_cefr_and_excessive_translations_are_rejected(): void
    {
        $this->registerRoutes();
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = $this->payload();
        $payload['rows'][0]['cefr'] = 'B9';
        $this->actingAs($admin)->postJson('/_test/course-workspace/save', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('rows');

        $payload = $this->payload();
        $payload['rows'][0]['body_km'] = str_repeat('ក', 12001);
        $this->postJson('/_test/course-workspace/save', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->assertDatabaseCount('homepage_campaign_items', 0);
    }
}
