<?php

namespace Tests\Feature;

use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Support\AssessmentQuestions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class StarterQuizImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_50_complete_quiz_drafts_with_distinct_featured_covers(): void
    {
        $disk = Storage::fake('local');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->artisan('quizzes:import-starter')->expectsOutput('New draft quizzes: 50')->assertSuccessful();
        $quizzes = CourseExam::with(['type', 'featuredMedia'])->get();
        $this->assertCount(50, $quizzes);
        $this->assertCount(50, $quizzes->pluck('public_id')->unique());
        $this->assertCount(50, $quizzes->pluck('featured_media_id')->unique());
        $this->assertSame([10, 10, 10, 10, 10], $quizzes->groupBy('type.name')->map->count()->values()->all());
        $this->assertCount(50, $disk->files('editor-media'));

        foreach ($quizzes as $quiz) {
            $this->assertFalse($quiz->published);
            $this->assertNull($quiz->course_id);
            $this->assertSame('quiz', $quiz->kind);
            $this->assertMatchesRegularExpression('/^[1-9][0-9]{5}$/', (string) $quiz->public_id);
            $this->assertCount(20, $quiz->questions);
            $this->assertNotContains('', array_column($quiz->questions, 'feedback'));
            $this->assertSame($admin->id, $quiz->featuredMedia->uploaded_by);
            $this->assertSame('image/webp', $quiz->featuredMedia->mime);
            $disk->assertExists($quiz->featuredMedia->path);
            $this->assertSame(['score' => 100, 'passed' => true], AssessmentQuestions::result(
                ['questions' => $quiz->questions, 'passing_percent' => $quiz->passing_percent],
                array_column($quiz->questions, 'correct')
            ));
        }

        $quiz = $quizzes->firstWhere('starter_key', 'c1-reading');
        $mediaUrl = route('editor-media.show', $quiz->featuredMedia);
        $this->get(route('assessments.quizzes'))->assertOk()->assertDontSee($quiz->title);
        $this->get($quiz->publicUrl())->assertNotFound();
        $this->get($mediaUrl)->assertForbidden();
        $this->actingAs($admin)->get(route('admin.quizzes-manager.edit', $quiz))->assertOk()
            ->assertSee('Featured media')->assertSee($quiz->featuredMedia->original_name);
        $this->patch(route('admin.quizzes-manager.update', $quiz), [
            'title' => 'Edited reading practice',
            'exam_type_id' => $quiz->exam_type_id,
            'time_limit' => $quiz->time_limit,
            'passing_percent' => $quiz->passing_percent,
            'attempt_limit' => $quiz->attempt_limit,
            'live_feedback' => true,
            'published' => true,
            'questions_payload' => json_encode($quiz->questions, JSON_THROW_ON_ERROR),
            'featured_kind' => 'image',
            'featured_media_id' => $quiz->featured_media_id,
            'featured_alt' => $quiz->featured_alt,
        ])->assertSessionHasNoErrors();
        $quiz->refresh();
        $this->assertTrue($quiz->published);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->artisan('quizzes:import-starter', ['author' => $admin->id])->expectsOutput('New draft quizzes: 0')->assertSuccessful();
        $this->assertCount(50, $disk->files('editor-media'));
        $this->assertDatabaseCount('course_exams', 50);
        $this->assertDatabaseCount('editor_media', 50);

        $this->get($mediaUrl)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get(route('assessments.quizzes'))->assertOk()->assertSee($quiz->title)->assertSee(parse_url($mediaUrl, PHP_URL_PATH));
        $this->get($quiz->publicUrl())->assertOk()->assertSee(parse_url($mediaUrl, PHP_URL_PATH))
            ->assertDontSee($quiz->questions[0]['prompt']);

        $student = User::factory()->create();
        $this->actingAs($student)->post(route('account.assessment-attempts.start', $quiz))->assertRedirect();
        $attempt = ExamAttempt::sole();
        $this->get(route('account.assessment-attempts.show', $attempt))->assertOk()
            ->assertSee($quiz->questions[0]['passage'])->assertDontSee($quiz->questions[0]['feedback']);
        $this->post(route('account.assessment-attempts.submit', $attempt), ['answers' => array_column($quiz->questions, 'correct')])
            ->assertRedirect();
        $this->assertSame(100, $attempt->fresh()->score);
    }
}
