<?php

namespace Tests\Feature;

use App\Models\CourseExam;
use App\Models\ExamType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AssessmentTypesTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_exam_type_table_can_edit_and_delete_unused_types(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $table = route('admin.exam-types.index', ['lang' => 'en']);
        $this->post(route('admin.exam-types.store'), ['name' => 'Placement', 'description' => 'Places learners by level.'])
            ->assertRedirect($table);
        $type = ExamType::where('kind', 'exam')->sole();
        $action = ['id' => $type->id, 'lang' => 'en'];

        $this->get($table)->assertOk()
            ->assertSeeInOrder(['>No</th>', '>Type</th>', '>Description</th>', '>Action</th>'], false)
            ->assertSee('Places learners by level.')->assertSee('Actions for Placement');
        $this->get($table.'&manage=edit&type='.$type->id)
            ->assertOk()->assertSee('Save changes')->assertSee('value="Placement"', false);
        $this->patch(route('admin.exam-types.update', $action), ['name' => 'Placement Test', 'description' => 'Revised description.'])
            ->assertRedirect($table)->assertSessionHas('status', 'Exam type updated.');

        $this->get($table.'&manage=delete&type='.$type->id)
            ->assertOk()->assertSee('Delete the “Placement Test” exam type?')->assertSee('Cancel');
        $this->delete(route('admin.exam-types.destroy', $action))
            ->assertRedirect($table)->assertSessionHas('status', 'Exam type deleted.');
        $this->assertModelMissing($type);
    }

    public function test_exam_type_deletion_preserves_linked_exams_and_rejects_quiz_types(): void
    {
        $type = ExamType::create(['name' => 'Final Exam', 'kind' => 'exam']);
        $quizType = ExamType::create(['name' => 'Practice', 'kind' => 'quiz']);
        $exam = CourseExam::create([
            'exam_type_id' => $type->id, 'title' => 'End of course exam', 'questions' => [],
            'time_limit' => 5, 'passing_percent' => 70, 'attempt_limit' => 1, 'kind' => 'exam',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.exam-types.index', ['manage' => 'delete', 'type' => $type->id]))
            ->assertOk()->assertSee('This exam type is assigned to an exam.')->assertDontSee(route('admin.exam-types.destroy', $type));
        $this->delete(route('admin.exam-types.destroy', $type))->assertSessionHasErrors('type');
        $exam->delete();
        $this->delete(route('admin.exam-types.destroy', $type))->assertSessionHasErrors('type');
        $this->delete(route('admin.exam-types.destroy', $quizType))->assertNotFound();
        $this->assertModelExists($type);
        $this->assertModelExists($quizType);

        $this->actingAs(User::factory()->create())->delete(route('admin.exam-types.destroy', $type))->assertForbidden();
    }

    public function test_migration_preserves_existing_exam_and_quiz_assignments(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('This test changes the SQLite test schema inside a transaction.');
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
