<?php

namespace Tests\Feature;

use App\Models\CourseExam;
use App\Models\EditorMedia;
use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Models\User;
use App\Support\GuestAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class GuestAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private function assessment(array $extra = []): CourseExam
    {
        $type = ExamType::firstOrCreate(['kind' => 'quiz', 'name' => 'Guest QA']);
        return CourseExam::create($extra + [
            'kind' => 'quiz', 'exam_type_id' => $type->id, 'title' => 'Guest QA assessment',
            'course_id' => null, 'pricing' => null, 'published' => true, 'guest_enabled' => true,
            'time_limit' => 2, 'passing_percent' => 70, 'attempt_limit' => 1,
            'shuffle' => false, 'gamified' => false, 'live_feedback' => true,
            'questions' => [['format' => 'choice', 'prompt' => 'Choose one', 'a' => 'First', 'b' => 'Second',
                'correct' => 'a', 'points' => 2, 'feedback' => 'Private answer explanation']],
        ])->refresh();
    }

    public function test_guest_is_default_closed_and_paid_draft_disabled_and_speaking_assessments_are_ineligible(): void
    {
        $exam = $this->assessment();
        $this->get($exam->publicUrl())->assertOk()->assertDontSee('Start as guest');
        $this->post(route('guest-assessments.start', $exam))->assertNotFound();
        config()->set('guest-assessment.rehearsal', true);
        foreach ([
            ['guest_enabled' => false],
            ['published' => false],
            ['pricing' => ['model' => 'membership', 'plan' => 'QA']],
            ['pricing' => ['model' => 'paid', 'strategy' => 'fixed', 'currency' => 'USD', 'base_minor' => 1000]],
            ['questions' => [['format' => 'speaking', 'prompt' => 'Record', 'points' => 1]]],
        ] as $extra) {
            $item = $this->assessment($extra);
            $this->assertFalse(GuestAssessment::allowed($item));
            $this->post(route('guest-assessments.start', $item))->assertNotFound();
        }
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_guest_session_isolation_scoring_attempt_limit_and_audio_access(): void
    {
        config()->set(['guest-assessment.rehearsal' => true, 'filesystems.managed' => 'local']);
        $disk = Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $wav = 'RIFF'.pack('V', 16036).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16)
            .'data'.pack('V', 16000).str_repeat("\0", 16000);
        $disk->put('editor-media/guest-qa.wav', $wav);
        $media = EditorMedia::create(['context' => 'exam', 'uploaded_by' => $admin->id,
            'mime' => 'audio/wav', 'path' => 'editor-media/guest-qa.wav', 'original_name' => 'qa.wav', 'size' => strlen($wav)]);
        $unrelated = EditorMedia::create(['context' => 'exam', 'uploaded_by' => $admin->id,
            'mime' => 'audio/wav', 'path' => 'editor-media/guest-qa.wav', 'original_name' => 'other.wav', 'size' => strlen($wav)]);
        $exam = $this->assessment(['questions' => [
            ['format' => 'listening', 'prompt' => 'Listen to the audio', 'a' => 'First', 'b' => 'Second',
                'correct' => 'a', 'points' => 2, 'media_id' => $media->id, 'feedback' => 'Private answer explanation'],
            ['format' => 'likert', 'prompt' => 'Agree?', 'points' => 1],
        ]]);
        $exam->media()->attach([$media->id, $unrelated->id]);
        $secret = str_repeat('a', 64);
        $this->withSession(['guest_assessment_secret' => $secret]);
        $this->get($exam->publicUrl())->assertOk()->assertSee('Start as guest')->assertDontSee('Listen to the audio');
        $this->get(route('editor-media.show', $media))->assertForbidden();
        $start = route('guest-assessments.start', $exam);
        $this->post($start)->assertRedirect();
        $attempt = ExamAttempt::sole();
        $show = route('guest-assessments.show', $attempt);
        $submit = route('guest-assessments.submit', $attempt);
        $audio = route('guest-assessments.audio', [$attempt, $media]);
        $this->assertSame(hash('sha256', $secret), $attempt->guest_identity_hash);
        $this->assertNull($attempt->student_id);
        $this->assertNull($attempt->course_enrollment_id);
        $this->assertFalse($attempt->snapshot['live_feedback']);
        $this->assertArrayNotHasKey('guest_identity_hash', $attempt->toArray());
        $this->post($start)->assertRedirect($show);
        $this->assertDatabaseCount('exam_attempts', 1);
        $this->get($show)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('Listen to the audio')->assertDontSee('Private answer explanation')->assertDontSee('Check answer');
        $this->get($audio)->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->get(route('guest-assessments.audio', [$attempt, $unrelated]))->assertNotFound();
        $this->withSession(['guest_assessment_secret' => str_repeat('b', 64)]);
        $this->get($show)->assertNotFound();
        $this->get($audio)->assertNotFound();
        $this->post($submit, ['answers' => ['a', '5']])->assertNotFound();
        $this->withSession(['guest_assessment_secret' => $secret]);
        $this->postJson($submit, ['answers' => ['c', '5']])->assertUnprocessable();
        $this->post($submit, ['answers' => ['a', '5']])->assertRedirect($show);
        $this->assertSame(100, $attempt->fresh()->score);
        $this->assertTrue($attempt->fresh()->passed);
        $this->get($show)->assertOk()->assertSee('100% scored')->assertDontSee('Private answer explanation');
        $this->get($audio)->assertNotFound();
        $this->post($submit, ['answers' => ['b', '1']])->assertConflict();
        $this->post($start)->assertConflict();
        $this->actingAs(User::factory()->create())->get(route('account.assessment-attempts.show', $attempt))->assertNotFound();
        $this->assertDatabaseCount('exam_attempts', 1);
    }

    public function test_expired_attempt_counts_and_access_is_revocable(): void
    {
        config()->set('guest-assessment.rehearsal', true);
        $exam = $this->assessment();
        $this->post(route('guest-assessments.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::sole();
        $this->travel(3)->minutes();
        $this->get(route('guest-assessments.show', $attempt))->assertOk()->assertSee('time limit expired');
        $this->post(route('guest-assessments.submit', $attempt), ['answers' => ['a']])->assertConflict();
        $this->post(route('guest-assessments.start', $exam))->assertConflict();
        $exam->update(['guest_enabled' => false]);
        $this->get(route('guest-assessments.show', $attempt))->assertNotFound();
        $this->assertNull($attempt->fresh()->submitted_at);
    }

    public function test_guest_writing_uses_native_admin_grading_without_creating_account(): void
    {
        config()->set('guest-assessment.rehearsal', true);
        $exam = $this->assessment(['questions' => [['format' => 'writing', 'prompt' => 'Write a response', 'points' => 4]]]);
        $this->post(route('guest-assessments.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::sole();
        $this->post(route('guest-assessments.submit', $attempt), ['answers' => ['A guest response']])->assertRedirect();
        $this->assertNull($attempt->fresh()->score);
        $this->get(route('guest-assessments.show', $attempt))->assertOk()->assertSee('Waiting for writing grades');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.quizzes-manager.edit', $exam))->assertOk()
            ->assertSee('Guest participant')->assertSee('A guest response');
        $this->patch(route('admin.assessment-attempts.grade', $attempt), ['grades' => [0 => ['points' => 4, 'feedback' => 'Clear response.']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(100, $attempt->fresh()->score);
        $this->assertTrue($attempt->fresh()->passed);
        $this->assertNull($attempt->fresh()->student_id);
    }
}
