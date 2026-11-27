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
        $type = ExamType::firstOrCreate(['kind' => $kind, 'name' => 'QA '.$kind]);
        return ['kind' => $kind, 'request_id' => (string) Str::uuid(), 'settings' => [
            'exam_type_id' => $type->id, 'title' => 'QA standalone '.$kind, 'instructions' => 'Choose an answer.',
            'time_limit' => $kind === 'quiz' ? 20 : 120, 'passing_percent' => 80, 'attempt_limit' => 2,
            'shuffle' => false, 'gamified' => false, 'live_feedback' => true,
            'content_language' => 'English', 'public_requirement' => 'QA only.',
        ], 'questions' => array_fill(0, $count, $this->question())];
    }

    public function test_advanced_native_append_audio_ownership_and_scoring(): void
    {
        config()->set(['mcp.oauth_assessment_advanced_rehearsal' => true, 'filesystems.managed' => 'local']);
        $advanced = \App\Support\McpOAuthAssessmentAdvanced::class;
        $scope = ['mcp:read', 'mcp:write', 'mcp:assessment', 'mcp:assessment-advanced'];
        $this->assertCount(2, $advanced::catalog());
        $this->assertTrue(Policy::hasAssessmentAdvancedScopes($scope));
        $this->assertTrue(Policy::hasAssessmentScopes($scope));
        $this->assertFalse(Policy::hasAssessmentAdvancedScopes(array_slice($scope, 0, -1)));
        foreach ([ [...$scope, '*'], [...$scope, 'mcp:assessment-advanced'],
            array_values(array_diff($scope, ['mcp:assessment']))] as $invalid) {
            $this->assertFalse(Policy::permittedWriteScopes($invalid));
        }
        $disk = \Illuminate\Support\Facades\Storage::fake('local');
        $other = $this->admin();
        $makeAudio = function (User $owner, string $context, string $mime, string $path, bool $exists = true) use ($disk) {
            if ($exists) {
                $disk->put($path, 'RIFF'.pack('V', 36).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 0));
            }
            return \App\Models\EditorMedia::create(['context' => $context, 'uploaded_by' => $owner->id,
                'path' => $path, 'mime' => $mime, 'original_name' => basename($path), 'size' => 44]);
        };
        $audio = $makeAudio($this->actor, 'exam', 'audio/wav', 'editor-media/qa-owned.wav');
        $foreign = $makeAudio($other, 'exam', 'audio/wav', 'editor-media/qa-other.wav');
        $wrongContext = $makeAudio($this->actor, 'lesson', 'audio/wav', 'editor-media/qa-lesson.wav');
        $wrongMime = $makeAudio($this->actor, 'exam', 'image/png', 'editor-media/qa-image.png');
        $missing = $makeAudio($this->actor, 'exam', 'audio/wav', 'editor-media/qa-missing.wav', false);
        $listed = $advanced::execute($this->actor, $advanced::LIST, ['limit' => 100]);
        $this->assertEqualsCanonicalizing([$audio->id, $missing->id], array_column($listed['items'], 'id'));
        $this->assertArrayNotHasKey('path', $listed['items'][0]);
        $manual = ['format' => 'writing', 'prompt' => 'Write a greeting.', 'a' => '', 'b' => '', 'c' => '', 'd' => '',
            'correct' => '', 'points' => 5, 'feedback' => 'Use a polite greeting.', 'media_id' => 0];
        $speaking = array_replace($manual, ['format' => 'speaking', 'prompt' => 'Say a greeting.']);
        $likert = array_replace($manual, ['format' => 'likert', 'prompt' => 'I feel confident greeting people.', 'points' => 1]);
        $listening = array_replace($manual, ['format' => 'listening', 'prompt' => 'Select the greeting.', 'a' => 'Hello',
            'b' => 'Goodbye', 'correct' => 'a', 'points' => 2, 'media_id' => $audio->id]);
        $reject = function (array $args, int $status, ?User $actor = null) use ($advanced): void {
            try {
                $advanced::execute($actor ?? $this->actor, $advanced::APPEND, $args);
                $this->fail('Unsafe advanced operation accepted.');
            } catch (ValidationException) {
                $this->assertSame(422, $status);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $this->assertSame(404, $status);
            } catch (HttpExceptionInterface $e) {
                $this->assertSame($status, $e->getStatusCode());
            }
        };
        foreach (['quiz', 'exam'] as $kind) {
            $created = Builder::execute($this->actor, 'create_draft_assessment', $this->fixture($kind, 1));
            $lookup = ['kind' => $kind, 'assessment_id' => $created['resource_id']];
            $before = Builder::execute($this->actor, 'get_draft_assessment', $lookup);
            $args = $lookup + ['request_id' => (string) Str::uuid(), 'expected_version' => $before['version'],
                'questions' => [$listening, $manual, $speaking, $likert]];
            foreach ([$foreign->id, $wrongContext->id, $wrongMime->id, $missing->id] as $id) {
                $reject(array_replace($args, ['questions' => [array_replace($listening, ['media_id' => $id])]]),
                    $id === $missing->id ? 422 : 404);
            }
            $reject(array_replace($args, ['questions' => [array_replace($manual, ['correct' => 'a'])]]), 422);
            $reject(array_replace($args, ['questions' => [array_replace($likert, ['points' => 5])]]), 422);
            $reject(array_replace($args, ['questions' => [array_replace($listening, ['b' => 'hello'])]]), 422);
            $reject(array_replace($args, ['questions' => array_fill(0, 11, $speaking)]), 422);
            $reject(array_replace($args, ['questions' => array_fill(0, $kind === 'quiz' ? 50 : 200, $manual)]), 422);
            $reject(array_replace($args, ['expected_version' => str_repeat('0', 64)]), 409);
            $reject($args, 404, $other);
            $this->assertSame($before['version'], Builder::execute($this->actor, 'get_draft_assessment', $lookup)['version']);
            $this->assertCount(0, CourseExam::findOrFail($created['resource_id'])->media()->get());
            $result = $advanced::execute($this->actor, $advanced::APPEND, $args);
            $this->assertSame(5, $result['question_count']);
            $this->assertSame(14, $result['total_points']);
            $this->assertSame(2, $result['manual_question_count']);
            $this->assertSame(1, $result['unscored_question_count']);
            $this->assertTrue($advanced::execute($this->actor, $advanced::APPEND, $args)['replayed']);
            $reject(array_replace($args, ['questions' => [$manual]]), 409);
            $reject(array_replace($args, ['request_id' => (string) Str::uuid()]), 409);
            $after = Builder::execute($this->actor, 'get_draft_assessment', $lookup);
            $this->assertSame($before['settings'], $after['settings']);
            $this->assertSame($before['questions'][0], $after['questions'][0]);
            $this->assertSame($before['featured_media_id'], $after['featured_media_id']);
            $this->assertTrue($after['rich_content']);
            $this->assertEquals($audio->id, $after['questions'][1]['media_id']);
            $this->assertSame('a', $after['questions'][1]['correct']);
            foreach ([2, 3, 4] as $i) {
                $this->assertArrayNotHasKey('correct', $after['questions'][$i]);
            }
            $this->assertEquals([$audio->id], CourseExam::findOrFail($created['resource_id'])->media()->allRelatedIds()->all());
            $snapshot = ['questions' => $after['questions'], 'passing_percent' => 80];
            $this->assertNull(\App\Support\AssessmentQuestions::result($snapshot, [0 => 'a', 1 => 'a', 4 => '5'])['score']);
            $this->assertSame(['score' => 100, 'passed' => true], \App\Support\AssessmentQuestions::result(
                $snapshot, [0 => 'a', 1 => 'a', 4 => '5'], [2 => ['points' => 5], 3 => ['points' => 5]]));
            try {
                Builder::execute($this->actor, 'replace_draft_assessment_questions', $lookup + [
                    'request_id' => (string) Str::uuid(), 'expected_version' => $after['version'], 'questions' => [$this->question()]]);
                $this->fail('Legacy replacement of advanced content accepted.');
            } catch (HttpExceptionInterface $e) {
                $this->assertSame(409, $e->getStatusCode());
            }
            CourseExam::findOrFail($created['resource_id'])->update(['published' => true]);
            $reject($args, 404);
        }
        config()->set('mcp.oauth_assessment_advanced_rehearsal', false);
        $this->assertSame([], $advanced::catalog());
        $this->assertTrue(Policy::permittedWriteScopes(array_slice($scope, 0, -1)));
        $this->assertFalse(Policy::permittedWriteScopes($scope));
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

    public function test_direct_assessment_audio_upload_validation_ownership_and_rollback(): void
    {
        config()->set([
            'mcp.oauth_assessment_advanced_rehearsal' => true,
            'mcp.oauth_assessment_audio_rehearsal' => true,
            'filesystems.managed' => 'local',
        ]);
        $disk = \Illuminate\Support\Facades\Storage::fake('local');
        $class = \App\Support\McpOAuthAssessmentAudioUpload::class;
        $scope = ['mcp:read', 'mcp:write', 'mcp:assessment', 'mcp:assessment-advanced', 'mcp:assessment-audio'];
        $this->assertCount(1, $class::catalog());
        $this->assertTrue(Policy::hasAssessmentAudioScopes($scope));
        $this->assertTrue(Policy::hasAssessmentAdvancedScopes($scope));
        $this->assertTrue(Policy::hasAssessmentScopes($scope));
        foreach ([[...$scope, '*'], [...$scope, 'mcp:assessment-audio'],
            array_values(array_diff($scope, ['mcp:assessment-advanced'])),
            array_values(array_diff($scope, ['mcp:assessment']))] as $invalid) {
            $this->assertFalse(Policy::permittedWriteScopes($invalid));
        }
        $wav = static function (int $frames = 8000, int $rate = 8000, int $channels = 1): string {
            $length = $frames * $channels * 2;
            return 'RIFF'.pack('V', 36 + $length).'WAVEfmt '
                .pack('VvvVVvv', 16, 1, $channels, $rate, $rate * $channels * 2, $channels * 2, 16)
                .'data'.pack('V', $length).str_repeat("\0", $length);
        };
        $bytes = $wav();
        $encoded = base64_encode($bytes);
        $this->assertSame([$bytes, 1000, 8000, 1], $class::sanitize($encoded));
        $stereo = $wav(4800, 48000, 2);
        $this->assertSame([$stereo, 100, 48000, 2], $class::sanitize(base64_encode($stereo)));
        $other = $this->admin();
        $reject = function (array $args, int $status, ?User $actor = null) use ($class): void {
            $uploader = new $class;
            try {
                $uploader->execute($actor ?? $this->actor, $args);
                $this->fail('Unsafe audio upload accepted.');
            } catch (ValidationException) {
                $this->assertSame(422, $status);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $this->assertSame(404, $status);
            } catch (HttpExceptionInterface $e) {
                $this->assertSame($status, $e->getStatusCode());
            } finally {
                $uploader->cleanupUncommitted();
            }
        };
        foreach (['quiz', 'exam'] as $kind) {
            $created = Builder::execute($this->actor, 'create_draft_assessment', $this->fixture($kind, 1));
            $lookup = ['kind' => $kind, 'assessment_id' => $created['resource_id']];
            $before = Builder::execute($this->actor, 'get_draft_assessment', $lookup);
            $args = $lookup + ['request_id' => (string) Str::uuid(), 'expected_version' => $before['version'],
                'audio_base64' => $encoded, 'prompt' => 'Identify the QA tone.', 'a' => 'Tone A', 'b' => 'Tone B',
                'c' => '', 'd' => '', 'correct' => 'a', 'points' => 3, 'feedback' => 'QA only.'];
            $files = $disk->allFiles('editor-media');
            $mediaCount = \App\Models\EditorMedia::count();
            foreach (['!!!!', 'data:audio/wav;base64,'.$encoded, base64_encode('<svg/>'),
                base64_encode($bytes.'trailing'), base64_encode(substr($bytes, 0, -1)),
                base64_encode(substr_replace($bytes, pack('v', 3), 20, 2)),
                base64_encode(substr_replace($bytes, pack('V', 1), 28, 4)),
                base64_encode($wav(10)), base64_encode($wav(248000)),
                base64_encode($wav(270000)), base64_encode($wav(8000, 96000))] as $invalid) {
                $reject(array_replace($args, ['audio_base64' => $invalid]), 422);
            }
            $reject(array_replace($args, ['b' => 'tone a']), 422);
            $reject(array_replace($args, ['correct' => 'c']), 422);
            $reject(array_replace($args, ['expected_version' => str_repeat('0', 64)]), 409);
            $reject($args, 404, $other);
            $this->assertSame($files, $disk->allFiles('editor-media'));
            $this->assertSame($mediaCount, \App\Models\EditorMedia::count());
            $this->assertSame($before['version'], Builder::execute($this->actor, 'get_draft_assessment', $lookup)['version']);

            $uploader = new $class;
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
            $this->assertSame($files, $disk->allFiles('editor-media'));
            $this->assertSame($mediaCount, \App\Models\EditorMedia::count());
            $this->assertSame($before['version'], Builder::execute($this->actor, 'get_draft_assessment', $lookup)['version']);
            $uploader = new $class;
            $result = $uploader->execute($this->actor, $args);
            $uploader->cleanupUncommitted();
            $this->assertSame('draft', $result['publication_status']);
            $this->assertSame('audio/wav', $result['mime']);
            $this->assertSame(1000, $result['duration_ms']);
            $this->assertSame(8000, $result['sample_rate']);
            $this->assertSame(1, $result['channels']);
            $this->assertSame(2, $result['question_count']);
            $this->assertSame(5, $result['total_points']);
            $this->assertSame(1, $result['question_index']);
            $media = \App\Models\EditorMedia::findOrFail($result['media_id']);
            $this->assertSame('exam', $media->context);
            $this->assertEquals($this->actor->id, $media->uploaded_by);
            $this->assertSame($bytes, $disk->get($media->path));
            $this->assertTrue((new $class)->execute($this->actor, $args)['replayed']);
            $after = Builder::execute($this->actor, 'get_draft_assessment', $lookup);
            $this->assertSame($before['settings'], $after['settings']);
            $this->assertSame($before['questions'][0], $after['questions'][0]);
            $this->assertSame($before['featured_media_id'], $after['featured_media_id']);
            $this->assertSame('listening', $after['questions'][1]['format']);
            $this->assertSame('a', $after['questions'][1]['correct']);
            $this->assertEquals($media->id, $after['questions'][1]['media_id']);
            $this->assertEquals([$media->id], CourseExam::findOrFail($created['resource_id'])->media()->allRelatedIds()->all());
            $listed = \App\Support\McpOAuthAssessmentAdvanced::execute($this->actor,
                \App\Support\McpOAuthAssessmentAdvanced::LIST, ['limit' => 100]);
            $this->assertContains($media->id, array_column($listed['items'], 'id'));
            $this->assertArrayNotHasKey('path', $listed['items'][0]);
            $reject(array_replace($args, ['prompt' => 'Changed replay']), 409);
            $reject(array_replace($args, ['request_id' => (string) Str::uuid()]), 409);
            $this->assertSame($mediaCount + 1, \App\Models\EditorMedia::count());
            $this->assertCount(count($files) + 1, $disk->allFiles('editor-media'));
            $full = Builder::execute($this->actor, 'create_draft_assessment', $this->fixture($kind, $kind === 'quiz' ? 50 : 200));
            $reject(array_replace($args, ['assessment_id' => $full['resource_id'], 'expected_version' => $full['version'],
                'request_id' => (string) Str::uuid()]), 422);
            CourseExam::findOrFail($created['resource_id'])->update(['published' => true]);
            $reject($args, 404);
        }
        config()->set('filesystems.managed', 'r2');
        $this->assertSame([], $class::catalog());
        config()->set('filesystems.managed', 'local');
        config()->set('mcp.oauth_assessment_audio_rehearsal', false);
        $this->assertSame([], $class::catalog());
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
