<?php

namespace Tests\Feature;

use App\Models\AssessmentAccess;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseExam;
use App\Models\CourseInstructorAssignment;
use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LearningManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_lists_search_by_public_id_and_page_at_ten_rows(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $firstQuiz = $this->assessment('quiz', 'Set 01');
        $firstExam = $this->assessment('exam', 'Set 01');
        $category = $this->category();
        $firstCourse = $this->course($category, $admin, 'Set 01');
        for ($i = 2; $i <= 12; $i++) {
            $label = sprintf('Set %02d', $i);
            $this->assessment('quiz', $label);
            $this->assessment('exam', $label);
            $this->course($category, $admin, $label);
        }

        $this->actingAs($admin);
        foreach (['admin.courses' => $firstCourse, 'admin.quizzes-manager' => $firstQuiz, 'admin.exams' => $firstExam] as $manager => $record) {
            $route = route($manager.'.index', ['lang' => 'en', 'q' => 'Set']);
            $this->get($route)->assertOk()
                ->assertSeeInOrder(['Showing 1–10 of 12', 'Previous', 'Next', 'Go to'])
                ->assertSee('Set 12')->assertDontSee('Set 01')->assertDontSee('Edition 1')
                ->assertSee('Assign to user')->assertSee('Activate')->assertSee('Delete')->assertSee('Preview')
                ->assertSee('q=Set');
            $this->get($route.'&page=2')->assertOk()->assertSee('Showing 11–12 of 12')->assertSee('Set 01')->assertDontSee('Set 12');
            $this->get(route($manager.'.index', ['q' => $record->public_id]))->assertOk()
                ->assertSee('Showing 1–1 of 1')->assertSee((string) $record->public_id)->assertSee('Set 01')->assertDontSee('Set 02');
            $this->get($route.'&page=9999')->assertOk()->assertSee('Showing 11–12 of 12');
        }
    }

    public function test_assessment_actions_preserve_attempts_and_publish_only_when_activated(): void
    {
        $quiz = $this->assessment('quiz', 'Personal practice');
        $unused = $this->assessment('exam', 'Unused exam');
        $other = User::factory()->create();
        $student = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $manager = 'admin.quizzes-manager';

        $this->actingAs($student)->get(route($manager.'.preview', $quiz))->assertForbidden();
        $this->delete(route($manager.'.destroy', $quiz))->assertForbidden();
        $this->actingAs($admin)->get(route($manager.'.preview', $quiz))->assertOk()->assertSee('Choose an answer');
        $this->get(route('admin.exams.preview', $quiz))->assertNotFound();
        $this->get(route($manager.'.users', [$quiz, 'q' => $student->public_id]))->assertOk()->assertSee($student->email)->assertDontSee($other->email);
        $this->post(route($manager.'.assign', $quiz), ['user' => $student->public_id])->assertSessionHasNoErrors();
        $this->post(route($manager.'.assign', $quiz), ['user' => $student->public_id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('assessment_access', 1);
        $this->assertSame('approved', AssessmentAccess::sole()->status);
        $this->actingAs($student)->get(route('account.assessments.index'))->assertOk()->assertSee($quiz->title)
            ->assertSee('Awaiting activation')->assertDontSee('Choose an answer');
        $this->actingAs($other)->get(route('account.assessments.index'))->assertOk()->assertDontSee($quiz->title);

        $this->actingAs($admin)->patch(route($manager.'.status', $quiz), ['active' => true])->assertSessionHasNoErrors();
        $this->actingAs($student)->get($quiz->publicUrl())->assertOk()->assertSee('Start quiz');
        $this->post(route('account.assessment-attempts.start', $quiz))->assertRedirect();
        $attempt = ExamAttempt::sole();
        $this->actingAs($admin)->patch(route($manager.'.status', $quiz), ['active' => false])->assertSessionHasNoErrors();
        $this->delete(route($manager.'.destroy', $quiz))->assertSessionHasErrors('manager');
        $this->get(route($manager.'.index'))->assertOk()->assertSee('Inactive');
        $this->actingAs($student)->post(route('account.assessment-attempts.start', $quiz))->assertNotFound();
        $this->get(route('account.assessment-attempts.show', $attempt))->assertOk();
        $this->actingAs($admin)->delete(route('admin.exams.destroy', $unused))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($unused);
    }

    public function test_course_deactivation_keeps_existing_access_and_assignment_respects_role(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create();
        $teacher = User::factory()->create(['role' => User::ROLE_INSTRUCTOR]);
        $category = $this->category();
        $course = $this->course($category, $admin, 'Example curriculum', 'published');
        $draft = $this->course($category, $admin, 'Unused draft');

        $this->actingAs($admin)->get(route('admin.courses.preview', $draft))->assertOk()->assertSee('Unused draft');
        $this->post(route('admin.courses.assign', $course), ['user' => $student->public_id])->assertSessionHasNoErrors();
        $this->post(route('admin.courses.assign', $course), ['user' => $teacher->public_id])->assertSessionHasNoErrors();
        $enrollment = CourseEnrollment::sole();
        $this->assertSame('approved', $enrollment->status);
        $this->assertSame($teacher->id, CourseInstructorAssignment::sole()->instructor_id);
        $this->actingAs($teacher)->get(route('instructor.courses.index', ['q' => $course->public_id]))
            ->assertOk()->assertSee($course->title)->assertSee('Open teaching workspace')->assertDontSee('Assign to user');
        $this->actingAs($admin)->patch(route('admin.courses.status', $course), ['active' => false])->assertSessionHasNoErrors();
        $this->delete(route('admin.courses.destroy', $course))->assertSessionHasErrors('manager');
        $this->delete(route('admin.courses.destroy', $draft))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($draft);
        $this->actingAs($student)->get($href = route('courses.show', $course))->assertNotFound();
        $this->get(route('account.courses.index'))->assertOk()->assertViewHas('courses', fn ($courses) => $courses->isEmpty());
        $this->get(route('account.enrollments.index'))->assertOk()->assertSee($course->title)->assertDontSee('Assign to user');
        $this->get(route('account.enrollments.show', $enrollment))->assertOk();
        $this->actingAs($admin)->patch(route('admin.courses.status', $course), ['active' => true])->assertSessionHasNoErrors();
        $this->get($href)->assertOk();
    }

    private function assessment(string $kind, string $title): CourseExam
    {
        $type = ExamType::firstOrCreate(['kind' => $kind, 'name' => 'A1 English practice']);

        return CourseExam::create([
            'kind' => $kind, 'title' => $title, 'exam_type_id' => $type->id,
            'time_limit' => 20, 'passing_percent' => 70, 'attempt_limit' => 3, 'published' => false,
            'questions' => [['prompt' => 'Choose an answer', 'format' => 'choice', 'a' => 'One', 'b' => 'Two', 'points' => 1, 'correct' => 'a']],
        ]);
    }

    private function category(): Category
    {
        return Category::create(['name' => 'General English', 'code' => 'GE', 'slug' => 'general-english', 'description' => 'English courses', 'group' => 'general', 'is_published' => true]);
    }

    private function course(Category $category, User $author, string $title, string $status = 'draft'): Course
    {
        return Course::create([
            'title' => $title, 'slug' => str($title)->slug()->toString(), 'category_id' => $category->id,
            'summary' => 'Course summary.', 'description' => 'Course description.', 'delivery_mode' => 'online',
            'status' => $status, 'created_by' => $author->id, 'published_at' => $status === 'published' ? now()->subMinute() : null,
        ]);
    }
}
