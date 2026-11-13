<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\PracticeAttempt;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function courseAndQuiz(string $status = 'published'): array
    {
        $this->seed(CategorySeeder::class);
        $course = Course::create([
            'category_id' => Category::where('code', 'GE')->firstOrFail()->id,
            'slug' => 'practice-reading',
            'title' => 'Reading Practice',
            'summary' => 'Course for a reading quiz',
            'description' => 'Complete the reading exercises.',
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);
        $module = $course->modules()->create(['title' => 'Reading unit', 'position' => 1]);
        $module->lessons()->create([
            'title' => 'Reading lesson', 'content' => 'Learn reading here.',
            'position' => 1, 'duration_minutes' => 10,
        ]);
        $quiz = $course->quizzes()->create([
            'title' => 'Reading check', 'position' => 1, 'passing_percent' => 70,
            'instructions' => 'Choose exactly one option.',
        ]);
        $question = $quiz->questions()->create([
            'prompt' => 'Which word means a greeting?',
            'option_a' => 'Goodbye', 'option_b' => 'Hello',
            'option_c' => 'Blue', 'option_d' => 'Tree',
            'correct_option' => 'B', 'position' => 1,
            'explanation' => 'Hello is a greeting.',
        ]);

        return compact('course', 'quiz', 'question');
    }

    public function test_admin_can_build_quiz_only_while_course_is_draft(): void
    {
        ['course' => $course] = $this->courseAndQuiz('draft');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/dashboard/course-builder/'.$course->id.'/quizzes', [
            'title' => 'Second quiz', 'position' => 2, 'passing_percent' => 70,
        ])->assertRedirect();

        $this->assertDatabaseCount('course_quizzes', 2);
        $newQuiz = $course->quizzes()->where('title', 'Second quiz')->firstOrFail();

        $this->post('/dashboard/course-builder/'.$course->id.'/quizzes/'.$newQuiz->id.'/questions', [
            'prompt' => 'Choose a color',
            'option_a' => 'Red', 'option_b' => 'Blue',
            'option_c' => 'Green', 'option_d' => 'Yellow',
            'correct_option' => 'A', 'position' => 1,
        ])->assertRedirect();

        $this->assertDatabaseCount('course_quiz_questions', 2);
        $this->get('/dashboard/course-builder/'.$course->id.'/quizzes/'.$newQuiz->id.'/edit')
            ->assertOk()->assertSee('Choose a color');

        $course->update(['status' => 'pending_review']);
        $this->post('/dashboard/course-builder/'.$course->id.'/quizzes', [
            'title' => 'Illegal mutation', 'position' => 3, 'passing_percent' => 70,
        ])->assertStatus(409);
        $this->assertDatabaseCount('course_quizzes', 2);
    }

    public function test_invalid_duplicate_choices_and_cross_course_quiz_references_are_blocked(): void
    {
        ['course' => $course, 'quiz' => $quiz] = $this->courseAndQuiz('draft');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/dashboard/course-builder/'.$course->id.'/quizzes/'.$quiz->id.'/questions', [
                'prompt' => 'Invalid duplicate answers',
                'option_a' => 'Red', 'option_b' => 'red',
                'option_c' => 'Blue', 'option_d' => 'Green',
                'correct_option' => 'A', 'position' => 2,
            ])->assertSessionHasErrors('option_a');
        $this->assertDatabaseCount('course_quiz_questions', 1);

        $second = Course::create([
            'category_id' => $course->category_id,
            'slug' => 'second-course',
            'title' => 'Second course',
            'summary' => 'Other course',
            'status' => 'draft',
        ]);
        $this->post('/dashboard/course-builder/'.$second->id.'/quizzes/'.$quiz->id.'/questions', [
            'prompt' => 'Forbidden parent',
            'option_a' => 'A', 'option_b' => 'B',
            'option_c' => 'C', 'option_d' => 'D',
            'correct_option' => 'A', 'position' => 1,
        ])->assertNotFound();
    }

    public function test_pending_and_published_quizzes_are_immutable(): void
    {
        ['course' => $course, 'quiz' => $quiz, 'question' => $question] =
            $this->courseAndQuiz('published');
        $admin = $this->admin();

        $this->actingAs($admin)->delete('/dashboard/course-builder/'.$course->id.'/quizzes/'.$quiz->id)
            ->assertStatus(409);
        $this->post('/dashboard/course-builder/'.$course->id.'/quizzes/'.$quiz->id.'/questions', [
            'prompt' => 'Forbidden', 'option_a' => 'A', 'option_b' => 'B',
            'option_c' => 'C', 'option_d' => 'D',
            'correct_option' => 'A', 'position' => 2,
        ])->assertStatus(409);
        $this->assertDatabaseCount('course_quiz_questions', 1);
    }

    public function test_student_can_submit_and_review_private_server_graded_result(): void
    {
        ['course' => $course, 'quiz' => $quiz, 'question' => $question] =
            $this->courseAndQuiz();
        $student = User::factory()->create();
        $other = User::factory()->create();
        $enrollment = CourseEnrollment::create([
            'course_id' => $course->id, 'student_id' => $student->id,
            'status' => 'approved',
        ]);

        $uri = '/account/enrollments/'.$enrollment->id.'/quizzes/'.$quiz->id;
        $this->actingAs($student)->get($uri)->assertOk()
            ->assertSee('Which word means a greeting?')
            ->assertDontSee('name="correct_option"', false);

        $this->post($uri.'/attempts', [
            'answers' => [(string) $question->id => 'B'],
        ])->assertRedirect();

        $attempt = PracticeAttempt::firstOrFail();
        $this->assertSame(100, $attempt->score_percent);
        $this->assertSame(1, $attempt->correct_count);
        $this->assertTrue($attempt->passed);

        $result = $uri.'/attempts/'.$attempt->id;
        $this->get($result)->assertOk()
            ->assertSee('100%')->assertSee('Hello is a greeting.');
        $this->get('/account/practice/history')->assertSee('Reading check');

        $this->actingAs($other)->get($result)->assertNotFound();
        $this->get($uri)->assertNotFound();
    }

    public function test_quiz_answers_with_missing_or_forged_question_ids_are_rejected(): void
    {
        ['course' => $course, 'quiz' => $quiz] = $this->courseAndQuiz();
        $student = User::factory()->create();
        $enrollment = CourseEnrollment::create([
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => 'approved',
        ]);

        $this->actingAs($student)->post('/account/enrollments/'.$enrollment->id.'/quizzes/'.$quiz->id.'/attempts', [
            'answers' => ['999999' => 'B'],
        ])->assertStatus(422);
        $this->assertDatabaseCount('practice_attempts', 0);
    }

    public function test_cancelled_enrollment_and_unpublished_course_cannot_take_quiz(): void
    {
        ['course' => $course, 'quiz' => $quiz] = $this->courseAndQuiz();
        $student = User::factory()->create();
        $enrollment = CourseEnrollment::create([
            'course_id' => $course->id, 'student_id' => $student->id,
            'status' => 'cancelled',
        ]);

        $url = '/account/enrollments/'.$enrollment->id.'/quizzes/'.$quiz->id;
        $this->actingAs($student)->get($url)->assertForbidden();
        $enrollment->update(['status' => 'approved']);
        $course->update(['status' => 'draft', 'published_at' => null]);
        $this->get($url)->assertNotFound();
    }

    public function test_practice_attempt_cap_is_enforced(): void
    {
        ['course' => $course, 'quiz' => $quiz, 'question' => $question] =
            $this->courseAndQuiz();
        $student = User::factory()->create();
        $enrollment = CourseEnrollment::create([
            'course_id' => $course->id, 'student_id' => $student->id,
            'status' => 'approved',
        ]);

        foreach (range(1, 5) as $number) {
            $enrollment->attempts()->create([
                'course_quiz_id' => $quiz->id,
                'correct_count' => 0,
                'question_count' => 1,
                'score_percent' => 0,
                'passed' => false,
                'submitted_at' => now(),
            ]);
        }

        $this->actingAs($student)
            ->post('/account/enrollments/'.$enrollment->id.'/quizzes/'.$quiz->id.'/attempts', [
                'answers' => [(string)$question->id => 'B'],
            ])->assertStatus(409);

        $this->assertDatabaseCount('practice_attempts', 5);
    }

    public function test_student_cannot_change_admin_practice_question_bank(): void
    {
        ['course' => $course] = $this->courseAndQuiz('draft');
        $this->actingAs(User::factory()->create())
            ->post('/dashboard/course-builder/'.$course->id.'/quizzes', [
                'title' => 'Forged quiz', 'position' => 2, 'passing_percent' => 70,
            ])->assertForbidden();

        $this->assertDatabaseCount('course_quizzes', 1);
    }
}
