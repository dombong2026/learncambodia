<?php

namespace Tests\Feature;

use App\Models\CourseExam;
use App\Models\ExamType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AssessmentTypesTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private refreshTransactionalDatabase;
    }

    public function refreshDatabase(): void
    {
        if (
            $this->name() !==
                'test_migration_preserves_existing_exam_and_quiz_assignments'
            || DB::getDriverName() !== 'mysql'
        ) {
            $this->refreshTransactionalDatabase();

            return;
        }

        // MySQL DDL implicitly commits transactions.
        // This migration test therefore requires a fresh
        // schema without RefreshDatabase's transaction.
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(
            0, DB::connection()->transactionLevel()
        );

        // Force the following transactional test to
        // rebuild the schema rather than reuse our data.
        RefreshDatabaseState::$migrated = false;

        $this->assertSame(
            0,
            Artisan::call('migrate:fresh', ['--force' => true])
        );

        $this->assertSame(
            0, DB::connection()->transactionLevel()
        );
    }

    public function test_exam_and_quiz_types_have_separate_pages_and_builders(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('admin.exam-types.store'), ['name' => 'Shared label'])->assertSessionHasNoErrors();
        $this->post(route('admin.quiz-types.store'), ['name' => 'Shared label'])->assertSessionHasNoErrors();
        $this->post(route('admin.quiz-types.store'), ['name' => 'Shared label'])->assertSessionHasErrors('name');
        $examType = ExamType::where('kind', 'exam')->sole();
        $quizType = ExamType::where('kind', 'quiz')->sole();

        $this->patch(route('admin.exam-types.update', $quizType), ['name' => 'Incorrect edit'])->assertNotFound();
        $this->patch(route('admin.quiz-types.update', $examType), ['name' => 'Incorrect edit'])->assertNotFound();
        $this->patch(route('admin.quiz-types.update', $quizType), ['name' => 'Quiz-only label'])->assertSessionHasNoErrors();

        $this->get(route('admin.exam-types.index'))->assertOk()->assertSee('Exam Types')->assertSee('Shared label')
            ->assertSee('Create Exam')->assertDontSee('Quiz-only label');
        $this->get(route('admin.quiz-types.index'))->assertOk()->assertSee('Quiz Types')->assertSee('Quiz-only label')
            ->assertDontSee('Shared label');
        $this->get(route('admin.exams.create'))->assertOk()->assertSee('Shared label')->assertDontSee('Quiz-only label');
        $this->get(route('admin.quizzes-manager.create'))->assertOk()->assertSee('Quiz-only label')->assertDontSee('Shared label');
        $this->get(route('admin.certificate-templates.index'))->assertOk()->assertSee('Certificate Templates')->assertDontSee('Certificate Templates & Builder');

        $payload = [
            'title' => 'Independent assessment', 'time_limit' => 5, 'passing_percent' => 70, 'attempt_limit' => 1,
            'questions' => [['prompt' => 'Pick one', 'a' => 'Yes', 'b' => 'No', 'correct' => 'a', 'points' => 1]],
        ];
        $this->post(route('admin.exams.store'), $payload + ['exam_type_id' => $quizType->id])->assertSessionHasErrors('exam_type_id');
        $this->post(route('admin.quizzes-manager.store'), $payload + ['exam_type_id' => $examType->id])->assertSessionHasErrors('exam_type_id');
        $this->post(route('admin.exams.store'), $payload + ['exam_type_id' => $examType->id])->assertSessionHasNoErrors();
        $this->post(route('admin.quizzes-manager.store'), $payload + ['exam_type_id' => $quizType->id])->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->create())->get(route('admin.quiz-types.index'))->assertForbidden();
    }

    public function test_migration_preserves_existing_exam_and_quiz_assignments(): void
    {
        $this->assertContains(
            DB::getDriverName(), ['sqlite', 'mysql']
        );

        if (DB::getDriverName() === 'mysql') {
            $this->assertSame(
                0, DB::connection()->transactionLevel()
            );
        }
        $migration = require database_path('migrations/2026_11_17_000040_separate_assessment_types.php');
        $migration->down();
        try {
            $type = DB::table('exam_types')->insertGetId(['name' => 'Original label', 'description' => 'Original description']);
            $payload = [
                'exam_type_id' => $type, 'title' => 'Existing assessment',
                'questions' => [], 'time_limit' => 5, 'passing_percent' => 70, 'attempt_limit' => 1,
            ];
            $exam = CourseExam::create($payload + ['kind' => 'exam']);
            $quiz = CourseExam::create($payload + ['kind' => 'quiz']);
        } finally {
            $migration->up();
        }

        $this->assertEquals($type, $exam->fresh()->type->id);
        $this->assertNotEquals($type, $quiz->fresh()->type->id);
        $this->assertSame('quiz', $quiz->fresh()->type->kind);
        $this->assertSame('Original label', $quiz->fresh()->type->name);
        $this->assertSame('Original description', $quiz->fresh()->type->description);
    }
}
