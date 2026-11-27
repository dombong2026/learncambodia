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

    public function test_owned_cover_attachment_and_rich_content_protection(): void
    {
        $payload = $this->fixture('quiz', 1);
        $created = Builder::execute($this->actor, 'create_draft_assessment', $payload);
        $args = ['kind' => 'quiz', 'assessment_id' => $created['resource_id']];
        \Illuminate\Support\Facades\Storage::fake('local');
        config()->set('filesystems.managed', 'local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('editor-media/assessment.png', 'fixture');
        $media = \App\Models\EditorMedia::create(['context' => 'library', 'uploaded_by' => $this->actor->id,
            'path' => 'editor-media/assessment.png', 'mime' => 'image/png', 'original_name' => 'assessment.png', 'size' => 7]);
        $cover = $args + ['request_id' => (string) Str::uuid(), 'expected_version' => $created['version'],
            'media_id' => $media->id, 'alt' => 'Assessment cover'];
        $attached = Builder::execute($this->actor, 'attach_draft_assessment_cover', $cover);
        $this->assertSame($media->id, $attached['featured_media_id']);
        $this->assertTrue(Builder::execute($this->actor, 'attach_draft_assessment_cover', $cover)['replayed']);
        $exam = CourseExam::findOrFail($created['resource_id']);
        $exam->update(['instructions_html' => '<p><strong>Keep this rich text.</strong></p>']);
        $read = Builder::execute($this->actor, 'get_draft_assessment', $args);
        $this->assertTrue($read['rich_content']);
        try {
            Builder::execute($this->actor, 'update_draft_assessment_settings', $args + [
                'request_id' => (string) Str::uuid(), 'expected_version' => $read['version'], 'settings' => $payload['settings']]);
            $this->fail('Rich content overwritten.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame('<p><strong>Keep this rich text.</strong></p>', $exam->fresh()->instructions_html);
        $this->assertSame($media->id, $exam->fresh()->featured_media_id);
    }

    public function test_direct_assessment_cover_upload_validation_and_rollback(): void
    {
        config()->set([
            'mcp.oauth_native_course_builder_rehearsal' => true,
            'mcp.oauth_draft_media_rehearsal' => true,
            'mcp.oauth_assessment_media_rehearsal' => true,
            'filesystems.managed' => 'local',
        ]);
        $disk = \Illuminate\Support\Facades\Storage::fake('local');
        $uploadClass = \App\Support\McpOAuthAssessmentCoverUpload::class;
        $this->assertCount(1, $uploadClass::catalog());
        $this->assertCount(7, Builder::catalog());
        $scope = ['mcp:read', 'mcp:write', 'mcp:curriculum', 'mcp:media', 'mcp:assessment', 'mcp:assessment-media'];
        $this->assertTrue(Policy::hasAssessmentMediaScopes($scope));
        $this->assertTrue(Policy::hasMediaScopes($scope));
        $this->assertTrue(Policy::hasAssessmentScopes($scope));
        $this->assertFalse(Policy::hasAssessmentMediaScopes(array_slice($scope, 0, -1)));
        foreach ([
            [...$scope, '*'],
            [...$scope, 'mcp:assessment-media'],
            array_values(array_diff($scope, ['mcp:media'])),
            array_values(array_diff($scope, ['mcp:assessment'])),
        ] as $invalidScope) {
            $this->assertFalse(Policy::permittedWriteScopes($invalidScope));
        }

        $image = imagecreatetruecolor(1920, 1080);
        imagefill($image, 0, 0, imagecolorallocate($image, 35, 80, 120));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        $encoded = base64_encode($bytes.'QA-TRAILING-METADATA');
        $smallImage = imagecreatetruecolor(200, 100);
        ob_start();
        imagepng($smallImage);
        $wrongDimensions = base64_encode(ob_get_clean());

        foreach (['quiz', 'exam'] as $kind) {
            $created = Builder::execute($this->actor, 'create_draft_assessment', $this->fixture($kind, 1));
            $args = ['kind' => $kind, 'assessment_id' => $created['resource_id'], 'request_id' => (string) Str::uuid(),
                'expected_version' => $created['version'], 'image_base64' => $encoded, 'alt' => 'QA upload'];
            $lookup = ['kind' => $kind, 'assessment_id' => $created['resource_id']];
            $before = Builder::execute($this->actor, 'get_draft_assessment', $lookup);
            $initialFiles = $disk->allFiles('editor-media');
            $initialMedia = \App\Models\EditorMedia::count();

            foreach (['data:image/png;base64,'.$encoded, base64_encode('<svg/>'), base64_encode(str_repeat('x', 524289)), '!!!!', $wrongDimensions] as $invalid) {
                $uploader = new $uploadClass;
                try {
                    $uploader->execute($this->actor, array_replace($args, ['image_base64' => $invalid]));
                    $this->fail('Invalid image accepted.');
                } catch (ValidationException) {
                    $this->assertSame($initialMedia, \App\Models\EditorMedia::count());
                    $this->assertSame($initialFiles, $disk->allFiles('editor-media'));
                } finally {
                    $uploader->cleanupUncommitted();
                }
            }

            $uploader = new $uploadClass;
            try {
                DB::transaction(function () use ($uploader, $args): void {
                    $uploader->execute($this->actor, $args);
                    throw new \RuntimeException('Injected outer transaction rollback');
                });
                $this->fail('Rollback not triggered.');
            } catch (\RuntimeException $e) {
                $this->assertSame('Injected outer transaction rollback', $e->getMessage());
            } finally {
                $uploader->cleanupUncommitted();
            }
            $this->assertSame($initialFiles, $disk->allFiles('editor-media'));
            $this->assertSame($initialMedia, \App\Models\EditorMedia::count());
            $this->assertNull(CourseExam::findOrFail($created['resource_id'])->featured_media_id);

            $uploader = new $uploadClass;
            $result = $uploader->execute($this->actor, $args);
            $uploader->cleanupUncommitted();
            $this->assertSame('draft', $result['publication_status']);
            $this->assertSame(1920, $result['width']);
            $this->assertSame(1080, $result['height']);
            $this->assertSame('image/png', $result['mime']);
            $media = \App\Models\EditorMedia::findOrFail($result['media_id']);
            $stored = $disk->get($media->path);
            $this->assertStringNotContainsString('QA-TRAILING-METADATA', $stored);
            $this->assertSame([1920, 1080], array_slice(getimagesizefromstring($stored), 0, 2));
            $this->assertEquals($this->actor->id, $media->uploaded_by);
            $this->assertTrue((new $uploadClass)->execute($this->actor, $args)['replayed']);
            $after = Builder::execute($this->actor, 'get_draft_assessment', $lookup);
            $this->assertSame($before['settings'], $after['settings']);
            $this->assertSame($before['questions'], $after['questions']);
            $this->assertEquals($media->id, $after['featured_media_id']);
            $this->assertNotSame($before['version'], $after['version']);

            foreach ([
                array_replace($args, ['alt' => 'Changed replay']),
                array_replace($args, ['request_id' => (string) Str::uuid()]),
                array_replace($args, ['request_id' => (string) Str::uuid(), 'expected_version' => $after['version']]),
            ] as $invalid) {
                try {
                    (new $uploadClass)->execute($this->actor, $invalid);
                    $this->fail('Unsafe replacement accepted.');
                } catch (HttpExceptionInterface $e) {
                    $this->assertSame(409, $e->getStatusCode());
                }
            }
            $this->assertSame($initialMedia + 1, \App\Models\EditorMedia::count());
            $this->assertCount(count($initialFiles) + 1, $disk->allFiles('editor-media'));

            try {
                (new $uploadClass)->execute($this->admin(), $args);
                $this->fail('Other owner upload accepted.');
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $this->assertTrue(true);
            }
            CourseExam::findOrFail($created['resource_id'])->update(['published' => true]);
            try {
                (new $uploadClass)->execute($this->actor, $args);
                $this->fail('Published assessment accepted.');
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
        config()->set('filesystems.managed', 'r2');
        $this->assertSame([], $uploadClass::catalog());
        config()->set('filesystems.managed', 'local');
        config()->set('mcp.oauth_assessment_media_rehearsal', false);
        $this->assertSame([], $uploadClass::catalog());
        $this->assertTrue(Policy::permittedWriteScopes(array_slice($scope, 0, -1)));
        $this->assertFalse(Policy::permittedWriteScopes($scope));
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
