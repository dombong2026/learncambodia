<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CurriculumLessonRevision;
use App\Models\User;
use App\Support\CourseReadiness;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LessonQuizAndCurriculumOrderTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->course = Course::create([
            'category_id' => Category::where('code', 'GE')->valueOrFail('id'),
            'slug' => 'lesson-exercises', 'title' => 'Exercises',
            'summary' => 'Learn with multiple examples.', 'description' => 'Course description.',
            'status' => 'draft', 'created_by' => $admin->id,
        ]);
    }

    public function test_optional_quiz_is_reviewed_copied_and_graded_inside_the_lesson(): void
    {
        $course = $this->course;
        $module = $course->modules()->create(['title' => 'Choices']);
        $fields = ['title' => 'Lesson', 'content' => 'Read this before answering.', 'position' => 1,
            'duration_minutes' => 15, 'is_preview' => false];
        $this->post(route('admin.course-builder.lessons.store', [$course, $module]), $fields)->assertSessionHasNoErrors();
        $lesson = $module->lessons()->sole();
        $this->get(route('admin.course-builder.edit', [
            'course' => $course, 'step' => 'curriculum', 'module' => $module->id,
            'editor' => 'lesson', 'lesson' => $lesson->id,
        ]))->assertOk()->assertSee('Lesson quiz');
        $edit = route('admin.course-builder.lessons.update', [$course, $module, $lesson]);
        $question = [
            'prompt' => 'Which word describes an action?',
            'choices' => ['a' => 'Chair', 'b' => 'Run', 'd' => 'Blue'],
            'correct' => 'b', 'feedback' => 'Run is a verb.',
        ];
        $quiz = ['lesson_quiz_present' => '1', 'quiz_questions' => [$question]];
        $this->patchJson($edit, $fields + array_replace($quiz, [
            'quiz_questions' => [array_replace($question, ['correct' => 'c'])],
        ]))->assertUnprocessable()->assertJsonValidationErrors('quiz_questions.0.correct');

        $initial = CourseReadiness::fingerprint($course);
        $this->patch($edit, $fields + $quiz)->assertSessionHasNoErrors();
        $this->assertSame([$question], $lesson->fresh()->quiz_questions);
        $this->assertArrayNotHasKey('quiz_questions', $lesson->fresh()->toArray());
        $this->assertNotSame($initial, CourseReadiness::fingerprint($course));
        $this->assertSame([$question], CurriculumLessonRevision::latest('id')->firstOrFail()->quiz_questions);
        $this->patch($edit, $fields)->assertSessionHasNoErrors();
        $this->assertSame([$question], $lesson->fresh()->quiz_questions);

        $this->post(route('admin.course-builder.lessons.duplicate', [$course, $module, $lesson]))->assertRedirect();
        $duplicate = $module->lessons()->where('id', '!=', $lesson->id)->sole();
        $this->assertSame([$question], $duplicate->quiz_questions);
        $this->patch(route('admin.course-builder.lessons.update', [$course, $module, $duplicate]),
            $fields + ['lesson_quiz_present' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($duplicate->fresh()->quiz_questions);

        $course->update(['status' => 'published', 'published_at' => now()->subMinute()]);
        $this->post(route('admin.course-builder.revise', $course))->assertRedirect();
        $copy = Course::where('source_course_id', $course->id)->sole();
        $this->assertSame([$question], $copy->modules()->sole()->lessons()->whereNotNull('quiz_questions')->sole()->quiz_questions);

        $student = User::factory()->create(['role' => 'student']);
        $enrollment = CourseEnrollment::create(['course_id' => $course->id, 'student_id' => $student->id, 'status' => 'approved']);
        $attempt = route('account.lessons.quiz', [$enrollment, $lesson]);
        $watch = route('account.lessons.watch', [$enrollment, $lesson, 'viewer' => 'quiz']);
        $study = route('account.enrollments.show', [$enrollment, 'view' => 'curriculum']);
        $this->actingAs(User::factory()->create(['role' => 'student']))->post($attempt, ['answers' => ['b']])->assertNotFound();
        $this->actingAs($student);
        $this->get($watch)->assertOk()->assertSeeInOrder(['Lesson quiz', $question['prompt'], 'Read this before answering.'])
            ->assertDontSee('Correct answer')->assertDontSee($question['feedback']);
        $this->get(route('account.lessons.watch', [$enrollment, $duplicate]))->assertOk()->assertDontSee('Lesson quiz');
        $this->post($attempt, ['answers' => ['c']])->assertRedirect($watch.'#lesson-quiz-'.$lesson->id);
        $this->get($watch)->assertSee('Choose one answer for each question.');
        $this->followingRedirects()->post($attempt, ['answers' => ['b'], 'view' => 'curriculum'])
            ->assertOk()->assertSee('1 of 1 correct.')->assertSee($question['feedback']);
        $this->post($attempt, ['answers' => ['a'], 'view' => 'curriculum'])
            ->assertRedirect($watch.'#lesson-quiz-'.$lesson->id);
        $this->get(route('account.lessons.watch', [$enrollment, $duplicate]))->assertOk()
            ->assertDontSee('0 of 1 correct.');
        $this->get($study)->assertSee('Course curriculum')->assertDontSee('Lesson quiz');
        $this->get($watch)->assertSee('0 of 1 correct.');
        $otherLearner = User::factory()->create(['role' => 'student']);
        $otherEnrollment = CourseEnrollment::create(['course_id' => $course->id,
            'student_id' => $otherLearner->id, 'status' => 'approved']);
        $this->actingAs($otherLearner)->get(route('account.lessons.watch', [$otherEnrollment, $lesson]))
            ->assertOk()->assertDontSee($question['feedback'])->assertDontSee('0 of 1 correct.');
        $this->actingAs($student);
        $enrollment->update(['status' => 'rejected']);
        $this->post($attempt, ['answers' => ['b']])->assertForbidden();
        $this->assertCount(0, $course->quizzes);
    }

    public function test_reordering_validates_scope_and_expected_order_and_respects_course_lock(): void
    {
        $course = $this->course;
        $first = $course->modules()->create(['title' => 'First', 'position' => 3]);
        $second = $course->modules()->create(['title' => 'Second', 'position' => 9]);
        $details = ['content' => 'Lesson text', 'duration_minutes' => 15];
        $lessonA = $first->lessons()->create(['title' => 'One', 'position' => 1] + $details);
        $lessonB = $first->lessons()->create(['title' => 'Two', 'position' => 2] + $details);
        $outsider = $second->lessons()->create(['title' => 'Other module', 'position' => 1] + $details);
        $moduleOrder = route('admin.course-builder.modules.reorder', $course);
        $lessonOrder = route('admin.course-builder.lessons.reorder', [$course, $first]);
        $payload = ['order' => [$second->id, $first->id], 'expected' => [$first->id, $second->id]];

        $this->patchJson($moduleOrder, $payload)->assertOk()->assertJson(['order' => $payload['order']]);
        $this->assertSame([$second->id, $first->id], $course->modules()->pluck('id')->all());
        $this->assertSame([1, 2], $course->modules()->pluck('position')->all());
        $this->patchJson($moduleOrder, $payload)->assertConflict();
        $this->patchJson($lessonOrder, ['order' => [$lessonA->id, $outsider->id], 'expected' => [$lessonA->id, $lessonB->id]])
            ->assertUnprocessable();
        $this->patchJson($lessonOrder, ['order' => [$lessonB->id, $lessonA->id], 'expected' => [$lessonA->id, $lessonB->id]])
            ->assertOk();
        $this->assertSame([$lessonB->id, $lessonA->id], $first->lessons()->pluck('id')->all());
        $this->assertSame(1, $outsider->fresh()->position);
        $this->assertSame(2, CurriculumLessonRevision::where('action', 'reordered')->count());
        $course->update(['status' => 'published', 'published_at' => now()->subMinute()]);
        $this->patchJson($moduleOrder, $payload)->assertConflict();
    }
}
