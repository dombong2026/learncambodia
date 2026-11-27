<?php

namespace Tests\Feature;

use App\Models\CourseExam;
use App\Models\ExamType;
use App\Models\User;
use App\Support\McpOAuthDraftScopePolicy as Policy;
use App\Support\McpOAuthStandaloneAssessment as Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

final class McpOAuthStandaloneAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'mcp.gateway_enabled' => true,
            'mcp.oauth_provider_staging_enabled' => true,
            'mcp.oauth_provider_accepted' => true,
            'mcp.oauth_ready' => true,
            'mcp.oauth_draft_scope_issuance_enabled' => true,
            'mcp.oauth_draft_writes_accepted' => true,
            'mcp.oauth_draft_writes_enabled' => true,
            'mcp.oauth_assessment_rehearsal' => true,
        ]);
        $this->actor = $this->admin();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()]);
        $user->forceFill(['two_factor_secret' => encrypt('TEST-ONLY-BASE32-SECRET'), 'two_factor_confirmed_at' => now()])->save();
        return $user;
    }

    private function question(): array
    {
        return ['format' => 'choice', 'prompt' => 'Choose the greeting.', 'a' => 'Hello', 'b' => 'Run',
            'c' => 'Jump', 'd' => 'Sleep', 'correct' => 'a', 'points' => 2, 'passage' => '', 'feedback' => 'Hello is a greeting.'];
    }

    private function fixture(string $kind, int $count): array
    {
        $type = ExamType::create(['kind' => $kind, 'name' => 'QA '.$kind]);
        return ['kind' => $kind, 'request_id' => (string) Str::uuid(), 'settings' => [
            'exam_type_id' => $type->id, 'title' => 'QA standalone '.$kind, 'instructions' => 'Choose an answer.',
            'time_limit' => $kind === 'quiz' ? 20 : 120, 'passing_percent' => 80, 'attempt_limit' => 2,
            'shuffle' => false, 'gamified' => false, 'live_feedback' => true,
            'content_language' => 'English', 'public_requirement' => 'QA only.',
        ], 'questions' => array_fill(0, $count, $this->question())];
    }

    public function test_native_limits_versions_replays_and_ownership(): void
    {
        $this->assertCount(7, Builder::catalog());
        $this->assertTrue(Policy::hasAssessmentScopes(['mcp:read', 'mcp:write', 'mcp:assessment']));
        $this->assertFalse(Policy::hasAssessmentScopes(['mcp:read', 'mcp:write', 'mcp:assessment', '*']));
        foreach (['quiz' => 50, 'exam' => 200] as $kind => $limit) {
            $payload = $this->fixture($kind, $limit - 1);
            $created = Builder::execute($this->actor, 'create_draft_assessment', $payload);
            $this->assertTrue(Builder::execute($this->actor, 'create_draft_assessment', $payload)['replayed']);
            $args = ['kind' => $kind, 'assessment_id' => $created['resource_id']];
            $read = Builder::execute($this->actor, 'get_draft_assessment', $args);
            $this->assertSame($limit - 1, $read['question_count']);
            $this->assertSame('a', $read['questions'][0]['correct']);
            $this->assertFalse($read['rich_content']);
            $change = $args + ['request_id' => (string) Str::uuid(), 'expected_version' => $read['version'],
                'questions' => [$this->question()]];
            $appended = Builder::execute($this->actor, 'append_draft_assessment_questions', $change);
            $this->assertSame($limit, $appended['question_count']);
            $this->assertTrue(Builder::execute($this->actor, 'append_draft_assessment_questions', $change)['replayed']);
            try {
                Builder::execute($this->actor, 'append_draft_assessment_questions', array_replace($change, [
                    'request_id' => (string) Str::uuid(), 'expected_version' => $appended['version']]));
                $this->fail('Question cap bypassed.');
            } catch (HttpExceptionInterface $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
            try {
                Builder::execute($this->actor, 'replace_draft_assessment_questions', array_replace($change, ['request_id' => (string) Str::uuid()]));
                $this->fail('Stale version accepted.');
            } catch (HttpExceptionInterface $e) {
                $this->assertSame(409, $e->getStatusCode());
            }
            $replaced = Builder::execute($this->actor, 'replace_draft_assessment_questions', array_replace($change, [
                'request_id' => (string) Str::uuid(), 'expected_version' => $appended['version']]));
            $settings = $payload['settings'];
            $settings['title'] = 'Updated '.$kind;
            $updated = Builder::execute($this->actor, 'update_draft_assessment_settings', $args + [
                'request_id' => (string) Str::uuid(), 'expected_version' => $replaced['version'], 'settings' => $settings]);
            $this->assertSame($settings['title'], $updated['title']);
            $this->assertSame(1, $updated['question_count']);
            $exam = CourseExam::findOrFail($created['resource_id']);
            $this->assertFalse($exam->published);
            $this->assertNull($exam->course_id);
            $this->assertNull($exam->pricing);
            try {
                Builder::execute($this->admin(), 'get_draft_assessment', $args);
                $this->fail('Wrong owner accepted.');
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $this->assertTrue(true);
            }
            $exam->update(['published' => true]);
            try {
                Builder::execute($this->actor, 'get_draft_assessment', $args);
                $this->fail('Published assessment accepted.');
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
        $this->assertDatabaseCount('mcp_assessment_owners', 2);
    }

    public function test_invalid_payload_and_production_closed(): void
    {
        $payload = $this->fixture('quiz', 1);
        $payload['questions'][0]['correct'] = 'z';
        try {
            Builder::execute($this->actor, 'create_draft_assessment', $payload);
            $this->fail('Invalid answer accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('mcp_assessment_owners', 0);
            $this->assertDatabaseCount('mcp_oauth_draft_receipts', 0);
        }
        config()->set('mcp.oauth_assessment_rehearsal', false);
        $this->assertSame([], Builder::catalog());
        $this->assertFalse(Policy::permittedWriteScopes(['mcp:read', 'mcp:write', 'mcp:assessment']));
        config()->set('mcp.oauth_assessment_rehearsal', true);
        $this->app->instance('env', 'production');
        try {
            $this->assertSame([], Builder::catalog());
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
