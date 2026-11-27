<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\McpOAuthDraftController;
use App\Models\Category;
use App\Models\Course;
use App\Models\EditorMedia;
use App\Models\User;
use App\Support\McpOAuthNativeCourseBuilder as Builder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

final class McpOAuthNativeCourseBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        config()->set([
            'mcp.gateway_enabled' => true,
            'mcp.oauth_provider_staging_enabled' => true,
            'mcp.oauth_provider_accepted' => true,
            'mcp.oauth_ready' => true,
            'mcp.oauth_draft_scope_issuance_enabled' => true,
            'mcp.oauth_draft_writes_accepted' => true,
            'mcp.oauth_draft_writes_enabled' => true,
            'mcp.oauth_native_course_builder_rehearsal' => true,
            'filesystems.managed' => 'local',
        ]);
        $this->actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()]);
        $this->actor->forceFill(['two_factor_secret' => encrypt('TEST-ONLY-BASE32-SECRET'),
            'two_factor_confirmed_at' => now()])->save();
        $this->course = Course::create(['title' => 'Native course test', 'slug' => 'native-course-test',
            'summary' => 'A course summary.', 'description' => 'A draft.', 'status' => 'draft',
            'created_by' => $this->actor->id, 'delivery_mode' => 'online',
            'category_id' => Category::where('code', 'GE')->valueOrFail('id')]);
    }

    private function payload(int $index): array
    {
        $lessons = [];
        for ($i = 1; $i <= 10; $i++) {
            $questions = [];
            for ($j = 1; $j <= 10; $j++) {
                $questions[] = ['prompt' => "Question {$index}.{$i}.{$j}",
                    'choices' => ['a' => 'Correct choice', 'b' => 'Choice two', 'c' => 'Choice three', 'd' => 'Choice four'],
                    'correct' => 'a', 'feedback' => 'Explanation of the answer.'];
            }
            $lessons[] = ['title' => "Lesson {$index}.{$i}", 'content' => 'Practical English lesson.',
                'duration_minutes' => 20, 'quiz_questions' => $questions];
        }
        return ['course_id' => $this->course->id, 'request_id' => (string) Str::uuid(),
            'expected_module_count' => $index - 1, 'module' => ['title' => "Module {$index}",
                'summary' => 'A module objective.', 'lessons' => $lessons]];
    }

    public function test_ten_module_native_import_with_replay_and_cover(): void
    {
        $this->assertCount(12, McpOAuthDraftController::catalog());
        for ($i = 1; $i <= 10; $i++) {
            $payload = $this->payload($i);
            $result = Builder::execute($this->actor, 'append_draft_module', $payload);
            $this->assertSame($i, $result['module_count']);
            $this->assertSame($i * 100, $result['question_count']);
            $this->assertFalse($result['replayed']);
            $this->assertTrue(Builder::execute($this->actor, 'append_draft_module', $payload)['replayed']);
        }
        $result = Builder::execute($this->actor, 'get_draft_curriculum', ['course_id' => $this->course->id]);
        $this->assertSame(100, $result['lesson_count']);
        $this->assertSame(100, $result['knowledge_check_count']);
        $this->assertSame(1000, $result['question_count']);
        $this->assertStringNotContainsString('Correct choice', json_encode($result));
        $this->assertDatabaseCount('course_modules', 10);
        $this->assertDatabaseCount('course_lessons', 100);
        $this->assertDatabaseCount('curriculum_lesson_revisions', 100);
        $this->assertDatabaseCount('mcp_oauth_draft_receipts', 10);
        $this->assertSame('a', $this->course->modules()->firstOrFail()->lessons()->firstOrFail()->quiz_questions[0]['correct']);

        Storage::fake('local');
        Storage::disk('local')->put('editor-media/test.png', 'fixture');
        $media = EditorMedia::create(['context' => 'library', 'uploaded_by' => $this->actor->id,
            'path' => 'editor-media/test.png', 'mime' => 'image/png', 'original_name' => 'test.png', 'size' => 7]);
        $cover = ['course_id' => $this->course->id, 'request_id' => (string) Str::uuid(), 'media_id' => $media->id, 'alt' => 'Course cover'];
        $this->assertSame($media->id, Builder::execute($this->actor, 'attach_draft_course_cover', $cover)['featured_media_id']);
        $this->assertTrue(Builder::execute($this->actor, 'attach_draft_course_cover', $cover)['replayed']);
        $this->assertSame('draft', $this->course->fresh()->status);
        $this->assertSame('image', $this->course->fresh()->featured_kind);
    }

    public function test_invalid_last_lesson_rolls_back_entire_batch_and_receipt(): void
    {
        $payload = $this->payload(1);
        $payload['module']['lessons'][9]['quiz_questions'][9]['choices']['d'] = 'Correct choice';
        try {
            Builder::execute($this->actor, 'append_draft_module', $payload);
            $this->fail('Invalid choices were accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('course_modules', 0);
            $this->assertDatabaseCount('course_lessons', 0);
            $this->assertDatabaseCount('curriculum_lesson_revisions', 0);
            $this->assertDatabaseCount('mcp_oauth_draft_receipts', 0);
        }
    }

    public function test_stale_count_and_changed_request_id_conflict(): void
    {
        $payload = $this->payload(1);
        Builder::execute($this->actor, 'append_draft_module', $payload);
        foreach ([$this->payload(1), array_replace($payload, ['expected_module_count' => 1])] as $invalid) {
            try {
                Builder::execute($this->actor, 'append_draft_module', $invalid);
                $this->fail('Conflicting request accepted.');
            } catch (HttpExceptionInterface $e) {
                $this->assertSame(409, $e->getStatusCode());
            }
        }
        $this->assertDatabaseCount('course_modules', 1);
        $this->assertDatabaseCount('mcp_oauth_draft_receipts', 1);
    }

    public function test_default_closed_and_production_closed(): void
    {
        config()->set('mcp.oauth_native_course_builder_rehearsal', false);
        $this->assertSame([], Builder::catalog());
        $this->assertCount(9, McpOAuthDraftController::catalog());
        config()->set('mcp.oauth_native_course_builder_rehearsal', true);
        $this->app->instance('env', 'production');
        try {
            $this->assertSame([], Builder::catalog());
            $this->assertFalse(Builder::enabled());
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_other_owners_and_published_courses_are_rejected(): void
    {
        $other = User::factory()->create(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()]);
        $other->forceFill(['two_factor_secret' => encrypt('TEST-ONLY-BASE32-SECRET'), 'two_factor_confirmed_at' => now()])->save();
        foreach ([$other, $this->actor] as $actor) {
            if ($actor->is($this->actor)) {
                $this->course->update(['status' => 'published', 'published_at' => now()]);
            }
            try {
                Builder::execute($actor, 'append_draft_module', $this->payload(1));
                $this->fail('Unauthorized course was accepted.');
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $this->assertDatabaseCount('course_modules', 0);
            }
        }
    }
}
