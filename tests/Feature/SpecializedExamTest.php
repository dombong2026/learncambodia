<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Models\IssuedCertificate;
use App\Models\User;
use App\Support\SpecializedExamAttempts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class SpecializedExamTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function test_viewer_selection_is_exam_only_standalone_and_free(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::create([
            'title' => 'Course', 'slug' => 'course', 'summary' => 'Course summary', 'created_by' => $admin->id,
            'category_id' => Category::create(['code' => 'LC', 'name' => 'Language', 'slug' => 'language', 'group' => 'english-courses', 'description' => 'Category description'])->id,
        ]);
        $type = ExamType::create(['name' => 'English exam', 'kind' => 'exam']);
        $quizType = ExamType::create(['name' => 'English quiz', 'kind' => 'quiz']);
        $values = $this->examData($type);
        $this->actingAs($admin)->get(route('admin.exams.create'))->assertOk()->assertSee('Exam viewer')->assertSee('Specialized');
        $this->get(route('admin.quizzes-manager.create'))->assertOk()->assertDontSee('Exam viewer');
        $this->postJson(route('admin.quizzes-manager.store'), array_replace($values, ['exam_type_id' => $quizType->id, 'viewer_mode' => 'specialized']))
            ->assertUnprocessable()->assertJsonValidationErrors('viewer_mode');
        $this->postJson(route('admin.exams.store'), $values + ['viewer_mode' => 'specialized', 'course_id' => $course->id])
            ->assertUnprocessable()->assertJsonValidationErrors('course_id');
        $this->post(route('admin.exams.store'), $values + ['viewer_mode' => 'specialized', 'pricing' => ['model' => 'paid', 'base' => '15']])
            ->assertSessionHasNoErrors();
        $specialized = CourseExam::sole();
        $this->assertTrue($specialized->isSpecialized());
        $this->assertNull($specialized->pricing);
        $this->post(route('admin.exams.store'), $values + ['course_id' => $course->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('course_exams', ['course_id' => $course->id, 'viewer_mode' => 'default']);
    }

    public function test_guest_can_complete_and_recover_exam_in_the_same_browser(): void
    {
        $exam = $this->exam();
        $this->get($exam->publicUrl())->assertRedirect(route('specialized-exams.show', [$exam, 'lang' => 'en']));
        $launch = route('specialized-exams.show', $exam);
        $this->get($launch)->assertOk()->assertCookie('specialized_exam_browser')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('public-site-header', false)->assertSee('<footer', false);
        $attempt = $this->beginGuest($exam);
        $result = route('specialized-exams.attempts.show', $attempt);
        $submit = route('specialized-exams.attempts.submit', $attempt);
        $this->assertNull($attempt->student_id);
        $this->get($result)->assertOk()->assertSee('First question')->assertSee('Second question')
            ->assertSee('public-site-header', false)->assertSee('<footer', false)->assertDontSee('assessment-result-summary', false);
        $this->post(route('specialized-exams.start', $exam))->assertRedirect($result);
        $this->withCookie('specialized_exam_browser', str_repeat('a', 64))->get($result)->assertNotFound();
        $this->post($submit, ['answers' => ['a', 'true']])->assertNotFound();
        $this->post(route('specialized-exams.attempts.email', $attempt), ['email' => 'other@example.test'])->assertNotFound();
        $this->withCookie('specialized_exam_browser', self::BROWSER)->post($submit, ['answers' => ['a', 'true']])->assertRedirect($result);
        $this->get($result)->assertOk()->assertSee('Exam submitted')->assertSee('100%')->assertSee('Create an account')
            ->assertSee('Questionnaire')->assertSee('Completed')->assertSee('Your answer')->assertDontSee('data-exam-attempt', false);
        $this->get($launch)->assertOk()->assertSee('Your attempts');
        $this->post($submit, ['answers' => ['b', 'false']])->assertConflict();
        $this->assertDatabaseCount('issued_certificates', 0);
        $next = route('specialized-exams.attempts.show', [$attempt, 'lang' => 'en'], absolute: false);
        $this->get(route('register', ['next' => $next]))->assertOk();
        $this->post(route('register'), [
            'name' => 'Registered Learner', 'email' => 'new-learner@example.test',
            'password' => 'PracticeExam!52177', 'password_confirmation' => 'PracticeExam!52177', 'terms' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect($next);
        $student = User::where('email', 'new-learner@example.test')->sole();
        $this->assertSame($student->id, $attempt->fresh()->student_id);
        $this->assertNull($attempt->fresh()->guest_browser);
        $this->assertSame($student->id, IssuedCertificate::sole()->student_id);
        $this->get($result)->assertOk()->assertSee('This result is saved to your learner account.');
        $this->get(route('account.assessments.index'))->assertOk()->assertSee('Specialized exam results')->assertSee($exam->title);
    }

    public function test_email_claim_requires_verification_without_the_original_browser(): void
    {
        $exam = $this->exam();
        $attempt = $this->beginGuest($exam);
        $submit = route('specialized-exams.attempts.submit', $attempt);
        $this->post($submit, ['answers' => ['a', 'true']])->assertRedirect();
        $this->post(route('specialized-exams.attempts.email', $attempt), ['email' => 'Learner@Example.test'])
            ->assertSessionHasNoErrors();
        $this->assertSame(SpecializedExamAttempts::emailKey('learner@example.test'), $attempt->fresh()->guest_email);

        $unrelated = User::factory()->create();
        $this->actingAs($unrelated)->post(route('specialized-exams.attempts.claim', $attempt))->assertSessionHasErrors('email');
        $this->withCookie('specialized_exam_browser', str_repeat('a', 64));
        $learner = User::factory()->unverified()->create(['email' => 'learner@example.test']);
        $this->actingAs($learner)->get(route('specialized-exams.attempts.show', $attempt))->assertNotFound();
        $this->post(route('specialized-exams.attempts.claim', $attempt))->assertNotFound();
        $this->assertNull($attempt->fresh()->student_id);

        $verify = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $learner->id, 'hash' => sha1($learner->email)]);
        $this->get($verify)->assertRedirect(route('account.overview'));
        $this->assertSame($learner->id, $attempt->fresh()->student_id);
        $this->assertNull($attempt->fresh()->guest_email);
        $this->assertSame($learner->id, IssuedCertificate::sole()->student_id);
        $this->get(route('specialized-exams.attempts.show', $attempt))->assertOk()->assertSee('100%');
    }

    public function test_manual_grade_and_certificate_follow_a_claimed_guest_attempt(): void
    {
        $exam = $this->exam();
        $exam->update(['questions' => [
            ['format' => 'writing', 'prompt' => 'Write your response', 'points' => 2],
        ]]);
        $attempt = $this->beginGuest($exam);
        $this->post(route('specialized-exams.attempts.submit', $attempt), ['answers' => ['Example answer']])->assertRedirect();
        $this->assertNull($attempt->fresh()->score);
        $this->assertFalse($attempt->fresh()->passed);
        $learner = User::factory()->create();
        $this->actingAs($learner)->post(route('specialized-exams.attempts.claim', $attempt))->assertRedirect();
        $this->assertDatabaseCount('issued_certificates', 0);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.assessment-attempts.grade', $attempt), ['grades' => [['points' => 2, 'feedback' => 'Good.']]])
            ->assertSessionHasNoErrors();
        $this->assertTrue($attempt->fresh()->passed);
        $this->assertSame($learner->id, IssuedCertificate::sole()->student_id);
        $this->actingAs($learner)->get(route('specialized-exams.attempts.show', $attempt))->assertOk()->assertSee('Good.');
    }

    private function beginGuest(CourseExam $exam): ExamAttempt
    {
        $this->withCookie('specialized_exam_browser', self::BROWSER)
            ->post(route('specialized-exams.start', $exam))->assertRedirect();
        return ExamAttempt::where('course_exam_id', $exam->id)->sole();
    }

    private function exam(): CourseExam
    {
        return CourseExam::create($this->examData(ExamType::create(['name' => 'English exam']))
            + ['kind' => 'exam', 'viewer_mode' => 'specialized']);
    }

    private function examData(ExamType $type): array
    {
        return ['title' => 'Standalone proficiency exam', 'exam_type_id' => $type->id,
            'attempt_limit' => 1, 'time_limit' => 60, 'passing_percent' => 70, 'published' => 1, 'questions' => [
                ['format' => 'choice', 'prompt' => 'First question', 'points' => 1, 'a' => 'A', 'b' => 'B', 'correct' => 'a'],
                ['format' => 'boolean', 'prompt' => 'Second question', 'points' => 1, 'correct' => 'true'],
            ],
        ];
    }
}
