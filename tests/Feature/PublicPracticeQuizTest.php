<?php

namespace Tests\Feature;

use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PublicPracticeQuizTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function test_ten_quizzes_publish_and_guests_get_feedback_and_two_timed_attempts(): void
    {
        $this->freezeTime();
        $quiz = $this->publishPracticeQuizzes();
        $launch = route('specialized-exams.show', $quiz);
        $start = route('specialized-exams.start', $quiz);
        $this->get($quiz->publicUrl())->assertRedirect(route('specialized-exams.show', [$quiz, 'lang' => 'en']));
        $this->get($launch)->assertOk()->assertCookie('specialized_exam_browser')
            ->assertSee('20 questions')->assertSee('20 minutes')->assertSee('2 attempts')->assertSee('Start quiz');

        $this->withCookie('specialized_exam_browser', self::BROWSER)->post($start)->assertRedirect();
        $first = ExamAttempt::sole();
        $result = route($first->resultRoute(), $first);
        $this->assertNull($first->student_id);
        $this->assertSame(1200, $first->expires_at->timestamp - now()->timestamp);
        $this->post($start)->assertRedirect($result);
        $this->get($result)->assertOk()->assertSee('Check answer')->assertDontSee($quiz->questions[0]['feedback']);
        $this->postJson(route('specialized-exams.attempts.feedback', $first), [
            'question' => 0, 'answer' => $quiz->questions[0]['correct'],
        ])->assertOk()->assertJson(['correct' => true, 'feedback' => $quiz->questions[0]['feedback']]);
        $this->post(route('specialized-exams.attempts.submit', $first), [
            'answers' => array_column($quiz->questions, 'correct'),
        ])->assertRedirect($result);
        $this->get($result)->assertOk()->assertSee('Quiz submitted')->assertSee('100%')
            ->assertSee($quiz->questions[19]['feedback'])->assertDontSee('Create an account');

        $this->post($start)->assertRedirect();
        $second = ExamAttempt::latest('id')->firstOrFail();
        $this->travel(21)->minutes();
        $this->post(route('specialized-exams.attempts.submit', $second))->assertConflict();
        $this->post($start)->assertConflict();
        $this->get($launch)->assertOk()->assertSee('You have used all available attempts.');
        $this->withCookie('specialized_exam_browser', str_repeat('a', 64))->get($result)->assertNotFound();
        $this->assertDatabaseCount('exam_attempts', 2);
    }

    public function test_signed_in_quiz_attempts_are_limited_to_the_account_across_browsers(): void
    {
        $quiz = $this->publishPracticeQuizzes();
        $learner = User::factory()->create();
        $start = route('specialized-exams.start', $quiz);
        $this->actingAs($learner)->post($start)->assertRedirect();
        $first = ExamAttempt::sole();
        $this->assertSame($learner->id, $first->student_id);
        $this->assertNull($first->guest_browser);

        $this->withCookie('specialized_exam_browser', str_repeat('a', 64))
            ->post($start)->assertRedirect(route($first->resultRoute(), $first));
        $this->post(route('specialized-exams.attempts.submit', $first))->assertRedirect();
        $this->post($start)->assertRedirect();
        $second = ExamAttempt::latest('id')->firstOrFail();
        $this->post(route('specialized-exams.attempts.submit', $second))->assertRedirect();
        $this->withCookie('specialized_exam_browser', str_repeat('b', 64))->post($start)->assertConflict();
        $this->assertDatabaseCount('exam_attempts', 2);

        $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($staff)->get(route($first->resultRoute(), $first))->assertNotFound();
        $this->post($start)->assertRedirect();
        $this->assertSame($staff->id, ExamAttempt::latest('id')->firstOrFail()->student_id);
    }

    private function publishPracticeQuizzes(): CourseExam
    {
        Storage::fake('local');
        User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->artisan('quizzes:import-starter')->assertSuccessful();
        $this->artisan('quizzes:publish-practice')->expectsOutput('Public practice quizzes: 10')->assertSuccessful();
        $this->artisan('quizzes:publish-practice')->expectsOutput('Public practice quizzes: 10')->assertSuccessful();

        $quizzes = CourseExam::where('published', true)->get();
        $this->assertCount(10, $quizzes);
        $this->assertDatabaseCount('course_exams', 50);
        foreach ($quizzes as $quiz) {
            $this->assertTrue($quiz->isSpecialized());
            $this->assertTrue($quiz->live_feedback);
            $this->assertSame(20, $quiz->time_limit);
            $this->assertSame(2, $quiz->attempt_limit);
            $this->assertCount(20, $quiz->questions);
            $this->assertCount(20, array_filter(array_column($quiz->questions, 'feedback')));
        }

        return $quizzes->firstWhere('starter_key', 'a1-present');
    }
}
