<?php

namespace Tests\Feature;

use App\Models\HomeCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeCampaignBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function modules(): string
    {
        return json_encode([[
            'id' => 'module-test-123',
            'title' => 'Grammar in context',
            'lessons' => [
                [
                    'id' => 'grammar-choice-123', 'title' => 'Choose the correct form',
                    'type' => 'multiple_choice',
                    'content_html' => '<p>She ___ to school every day.</p><script>alert(1)</script>',
                    'explanation_html' => '<p>Third person singular takes <strong>-s</strong>.</p>',
                    'feedback_correct' => 'Correct: goes describes a routine.',
                    'feedback_incorrect' => 'Review the third-person singular rule.',
                    'options' => ['go', 'goes', 'going'],
                    'correct_index' => 1,
                ],
                [
                    'id' => 'grammar-match-123', 'title' => 'Match tense to use',
                    'type' => 'matching',
                    'pairs' => [
                        ['left' => 'Simple present', 'right' => 'Regular habits'],
                        ['left' => 'Present continuous', 'right' => 'Happening now'],
                    ],
                ],
                [
                    'id' => 'grammar-order-123', 'title' => 'Arrange the sentence',
                    'type' => 'ordering',
                    'sequence' => ['She', 'is', 'reading'],
                ],
            ],
        ]], JSON_THROW_ON_ERROR);
    }

    public function test_only_admin_can_create_and_directly_publish_covered_campaigns(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $path = '/dashboard/appearance/home/campaign-builder';

        $this->actingAs($student)->get($path)->assertForbidden();
        $this->actingAs($admin)->get($path)->assertOk()->assertSee('Publish campaign');

        $payload = [
            'title' => 'English Grammar in Context',
            'summary' => 'Grammar quiz with real feedback.',
            'kind' => 'quiz', 'modules_json' => $this->modules(),
            'publication_intent' => 'publish',
        ];
        $this->post($path, $payload)->assertSessionHasErrors('cover');
        $this->post($path, $payload + ['cover' => UploadedFile::fake()->image('grammar.png', 1920, 1080)])
            ->assertRedirect();

        $campaign = HomeCampaign::firstOrFail();
        $this->assertSame('published', $campaign->status);
        $this->assertNotNull($campaign->published_at);
        $this->assertStringNotContainsString('<script>', $campaign->modules[0]['lessons'][0]['content_html']);
        $this->actingAs($student)->get('/campaigns/'.$campaign->slug)->assertOk()
            ->assertSee('Grammar in context')->assertSee('Check answer');
        $this->get('/campaigns/'.$campaign->slug.'/cover')->assertOk();

        $this->postJson('/campaigns/'.$campaign->slug.'/lessons/grammar-choice-123/check', ['answer' => 1])
            ->assertOk()->assertJsonPath('correct', true)
            ->assertJsonPath('feedback', 'Correct: goes describes a routine.');
        $this->postJson('/campaigns/'.$campaign->slug.'/lessons/grammar-choice-123/check', ['answer' => 0])
            ->assertOk()->assertJsonPath('correct', false)
            ->assertJsonPath('expected', 'goes');
        $this->postJson('/campaigns/'.$campaign->slug.'/lessons/grammar-match-123/check', [
            'answer' => ['Regular habits', 'Happening now'],
        ])->assertOk()->assertJsonPath('correct', true);
        $this->postJson('/campaigns/'.$campaign->slug.'/lessons/grammar-order-123/check', [
            'answer' => ['She', 'reading', 'is'],
        ])->assertOk()->assertJsonPath('correct', false);
        $this->get('/')->assertOk()->assertSee('English Grammar in Context');
    }

    public function test_draft_is_private_and_can_be_published_without_review(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $endpoint = '/dashboard/appearance/home/campaign-builder';

        $this->actingAs($admin)->post($endpoint, [
            'title' => 'Saved English Quiz', 'kind' => 'quiz',
            'modules_json' => $this->modules(), 'publication_intent' => 'draft',
        ])->assertRedirect();

        $campaign = HomeCampaign::firstOrFail();
        $this->actingAs($student)->get('/campaigns/'.$campaign->slug)->assertNotFound();
        $this->postJson('/campaigns/'.$campaign->slug.'/lessons/grammar-choice-123/check', ['answer'=>1])->assertNotFound();

        $this->actingAs($admin)->post($endpoint, [
            'campaign_id' => $campaign->id,
            'title' => $campaign->title, 'kind' => 'quiz',
            'modules_json' => $this->modules(), 'publication_intent' => 'publish',
            'cover' => UploadedFile::fake()->image('grammar.webp', 1920, 1080),
        ])->assertRedirect();

        $this->assertSame('published', $campaign->fresh()->status);
        $this->assertDatabaseMissing('appearance_revisions', ['section' => 'home_campaign']);
        $this->actingAs($student)->get('/campaigns/'.$campaign->slug)->assertOk();
    }
}
