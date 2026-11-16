<?php

namespace Tests\Feature;

use App\Models\AssessmentAccess;
use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Models\User;
use App\Support\CoursePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class StandaloneAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_independent_quiz_catalog_feedback_formats_and_grading(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create();
        $other = User::factory()->create();
        $type = ExamType::create(['name' => 'Language skills']);
        $this->actingAs($admin)->get(route('admin.quizzes-manager.create'))->assertOk()->assertSee('Quiz Builder')->assertDontSee('Course (optional)');
        $wav = 'RIFF'.pack('V', 40).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 4)."\0\0\0\0";
        $audio = $this->postJson(route('editor-media.store'), [
            'context' => 'exam', 'file' => UploadedFile::fake()->createWithContent('listen.wav', $wav),
        ])->assertCreated();
        $options = ['a' => 'First choice', 'b' => 'Second choice', 'correct' => 'a'];
        $questions = [
            ['format' => 'choice', 'prompt' => 'Pick one', 'feedback' => 'Review this choice.'] + $options,
            ['format' => 'reading', 'prompt' => 'Read this', 'passage' => 'A reading passage.'] + $options,
            ['format' => 'writing', 'prompt' => 'Write a response'],
            ['format' => 'boolean', 'prompt' => 'Choose Yes or No', 'correct' => 'true'],
            ['format' => 'order', 'prompt' => 'Put these in order', 'a' => 'Second', 'b' => 'First', 'correct' => 'b,a'],
            ['format' => 'likert', 'prompt' => 'How much do you agree?'],
            ['format' => 'listening', 'prompt' => 'Listen to this', 'media_id' => $audio->json('id')] + $options,
            ['format' => 'speaking', 'prompt' => 'Introduce yourself'],
            ['format' => 'fill', 'prompt' => 'The capital is ____', 'correct' => 'Phnom Penh|PP'],
        ];
        $data = ['title' => 'Independent language quiz', 'exam_type_id' => $type->id, 'time_limit' => 9,
            'passing_percent' => 70, 'attempt_limit' => 1, 'gamified' => 1, 'live_feedback' => 1, 'published' => 1,
            'questions_payload' => json_encode(array_map(fn ($question) => $question + ['points' => 1], $questions))];
        $this->post(route('admin.quizzes-manager.store'), $data)->assertSessionHasNoErrors();
        $quiz = CourseExam::sole();
        $this->assertNull($quiz->course_id);
        $this->assertSame('quiz', $quiz->kind);
        $this->post(route('admin.quizzes-manager.store'), array_replace($data, ['time_limit' => 31]))->assertSessionHasErrors('time_limit');
        $this->get(route('admin.exams.edit', $quiz))->assertNotFound();

        $this->actingAs($student)->get(route('assessments.quizzes'))->assertOk()->assertSee($quiz->title);
        $this->get(route('assessments.exams'))->assertOk()->assertDontSee($quiz->title);
        $this->get(route('assessments.show', $quiz))->assertOk()->assertSee('Start quiz')->assertDontSee('Pick one');
        $this->get($audio->json('url'))->assertForbidden();
        $this->post(route('account.assessment-attempts.start', $quiz))->assertRedirect();
        $attempt = ExamAttempt::sole();
        $show = route('account.assessment-attempts.show', $attempt);
        $feedback = route('account.assessment-attempts.feedback', $attempt);
        $this->post(route('account.assessment-attempts.start', $quiz))->assertRedirect($show);
        $this->get($show)->assertOk()->assertHeader('Permissions-Policy', 'camera=(), microphone=(self), geolocation=(), payment=()')->assertSee('A reading passage.')->assertSee('Strongly agree')->assertDontSee('Review this choice.');
        $this->get($audio->json('url'))->assertOk();
        $this->actingAs($other)->get($show)->assertNotFound();
        $this->postJson($feedback, ['question' => 0, 'answer' => 'a'])->assertNotFound();
        $this->actingAs($student)->postJson($feedback, ['question' => 0, 'answer' => 'b'])
            ->assertOk()->assertExactJson(['correct' => false, 'feedback' => 'Review this choice.', 'answer' => 'b']);
        $this->postJson($feedback, ['question' => 0, 'answer' => 'a'])->assertOk()->assertJsonPath('answer', 'b');
        $this->post(route('account.assessment-attempts.submit', $attempt), [
            'answers' => [0 => 'a', 1 => 'a', 2 => 'My written response', 3 => 'true', 4 => 'b,a', 5 => '4', 6 => 'a', 8 => 'PP'],
            'recordings' => [7 => UploadedFile::fake()->createWithContent('speak.wav', $wav)],
        ])->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Waiting for writing or speaking grades');
        $this->get($audio->json('url'))->assertForbidden();
        $recording = route('assessments.recording', [$attempt, 7]);
        $this->actingAs($other)->get($recording)->assertNotFound();
        $this->actingAs($admin)->get(route('admin.quizzes-manager.edit', $quiz))->assertOk()->assertSee('My written response');
        $this->get($recording)->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $grade = route('admin.assessment-attempts.grade', $attempt);
        $this->patch($grade, ['grades' => [2 => ['points' => 2]]])->assertSessionHasErrors('grades.2.points');
        $this->patch($grade, ['grades' => [2 => ['points' => 1, 'feedback' => 'Clear writing.']]])->assertSessionHasNoErrors();
        $this->assertNull($attempt->fresh()->score);
        $this->patch($grade, ['grades' => [7 => ['points' => 1]]])->assertSessionHasNoErrors();
        $this->actingAs($student)->get($show)->assertSee('87% scored')->assertSee('Clear writing.')->assertSee('Milestone earned: Quiz passed');
    }

    public function test_standalone_exam_pricing_and_access_require_verification(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $student = User::factory()->create();
        $type = ExamType::create(['name' => 'Timed test']);
        $base = ['title' => 'Independent exam', 'exam_type_id' => $type->id, 'time_limit' => 120,
            'passing_percent' => 75, 'attempt_limit' => 2, 'published' => 1,
            'questions' => [['prompt' => 'Select A', 'points' => 1, 'a' => 'A', 'b' => 'B', 'correct' => 'a']]];
        foreach ([
            ['model' => 'free'],
            ['model' => 'paid', 'strategy' => 'fixed', 'currency' => 'USD', 'base' => '15.00'],
            ['model' => 'membership', 'plan' => 'English Plus'],
        ] as $pricing) {
            $this->actingAs($admin)->post(route('admin.exams.store'), $base + ['pricing' => $pricing])->assertSessionHasNoErrors();
            $exam = CourseExam::latest('id')->firstOrFail();
            $this->assertNull($exam->course_id);
            $start = route('account.assessment-attempts.start', $exam);
            $this->actingAs($student)->get(route('assessments.show', $exam))->assertOk();
            if ($pricing['model'] !== 'free') {
                $this->post($start)->assertForbidden();
                $request = route('account.assessment-access.request', $exam);
                $this->post($request, ['pricing_quote' => '{}'])->assertConflict();
                $this->post($request, ['pricing_quote' => json_encode(CoursePricing::quote($exam->pricing))])->assertRedirect();
                $this->post($start)->assertForbidden();
                $access = AssessmentAccess::where('course_exam_id', $exam->id)->sole();
                $review = route('admin.assessment-access.review', $access);
                $this->patch($review, ['decision' => 'approved', 'reference' => 'AA-123'])->assertForbidden();
                $this->actingAs($admin)->patch($review, ['decision' => 'approved', 'reference' => 'AA-123'])->assertSessionHasNoErrors();
            }
            $this->actingAs($student)->post($start)->assertRedirect();
            $attempt = ExamAttempt::where('course_exam_id', $exam->id)->sole();
            $this->postJson(route('account.assessment-attempts.feedback', $attempt), ['question' => 0, 'answer' => 'a'])->assertNotFound();
        }
    }
}
