<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LearningFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private User $admin;
    private User $learner;
    private CourseEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->learner = User::factory()->create(['role' => 'student']);
        $this->course = Course::create([
            'title' => '12 Tenses Pilot', 'slug' => 'tenses-pilot',
            'summary' => 'Practice tenses.', 'description' => 'English tense lessons.',
            'category_id' => Category::where('code', 'GE')->valueOrFail('id'),
            'created_by' => $this->admin->id, 'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $this->enrollment = $this->course->enrollments()->create([
            'student_id' => $this->learner->id, 'status' => 'approved',
        ]);
    }

    public function test_saved_quiz_feedback_survives_a_new_session_and_missed_questions_can_be_retried(): void
    {
        $module = $this->course->modules()->create(['title' => 'Present Simple']);
        $lesson = $module->lessons()->create([
            'title' => 'Daily routines', 'content' => 'Read the daily routines.', 'position' => 1,
            'quiz_questions' => [
                ['prompt' => 'Choose the first answer.', 'choices' => ['a' => 'Yes', 'b' => 'No'], 'correct' => 'a', 'feedback' => 'First explanation.'],
                ['prompt' => 'Choose the second answer.', 'choices' => ['a' => 'Yes', 'b' => 'No'], 'correct' => 'b', 'feedback' => 'Second explanation.'],
            ],
        ]);
        $watch = fn (array $query = []) => route('account.lessons.watch', [$this->enrollment, $lesson, 'viewer' => 'quiz'] + $query);
        $submit = route('account.lessons.quiz', [$this->enrollment, $lesson]);
        $this->actingAs($this->learner)->post($submit, ['answers' => ['a', 'a']])->assertRedirect();
        $first = $this->enrollment->lessonQuizAttempts()->sole();
        $this->travel(2)->hours();
        $this->flushSession();
        $this->actingAs($this->learner)->get($watch())
            ->assertOk()->assertSee('1 of 2 correct.')->assertSee('Second explanation.')->assertSee('Quiz history');

        $this->get($watch(['retry' => $first->id]))->assertOk()
            ->assertSee('Choose the second answer.')->assertDontSee('Choose the first answer.')->assertDontSee('Second explanation.');
        $this->post($submit, ['retry' => $first->id, 'answers' => ['b']])->assertRedirect();
        $this->get($watch())->assertSee('1 of 1 correct.')->assertSee('Quiz completed');
        $this->get($watch(['attempt' => $first->id]))->assertSee('1 of 2 correct.');
        $this->assertSame($first->id, $this->enrollment->lessonQuizAttempts()->latest('id')->firstOrFail()->retry_of_id);
        $this->assertSame(0, $this->enrollment->lessonProgress()->count());

        $otherLearner = User::factory()->create(['role' => 'student']);
        $otherEnrollment = $this->course->enrollments()->create(['student_id' => $otherLearner->id, 'status' => 'approved']);
        $this->actingAs($otherLearner)->get(route('account.lessons.watch', [
            $otherEnrollment, $lesson, 'viewer' => 'quiz', 'attempt' => $first->id,
        ]))->assertNotFound();
        $this->post(route('account.lessons.quiz', [$otherEnrollment, $lesson]), [
            'retry' => $first->id, 'answers' => ['b'],
        ])->assertNotFound();
    }

    public function test_editors_can_manage_resources_and_approved_learners_can_download_them(): void
    {
        $course = $this->course;
        $index = route('admin.course-builder.resources', $course);
        $payload = [
            'title' => 'Course Workbook',
            'file' => UploadedFile::fake()->createWithContent('workbook.pdf', "%PDF-1.4\n%%EOF"),
        ];

        $this->actingAs($this->learner)->post(route('admin.course-builder.resources.store', $course), $payload)->assertForbidden();
        $this->actingAs($this->admin)->get($index)->assertOk()->assertSee('Upload a resource');
        $this->from($index)->post(route('admin.course-builder.resources.store', $course), $payload)
            ->assertRedirect($index)->assertSessionHasNoErrors();
        $workbook = $course->resources()->sole();
        $this->assertSame('application/pdf', $workbook->media->mime);

        $this->from($index)->patch(route('admin.course-builder.resources.update', [$course, $workbook]), [
            'title' => 'Downloadable Tenses Workbook', 'position' => 2,
            'file' => UploadedFile::fake()->createWithContent('12-Tenses-Workbook.pdf', "%PDF-1.4\nupdated\n%%EOF"),
        ])->assertRedirect($index)->assertSessionHasNoErrors();
        $this->assertSame(2, $workbook->fresh()->position);
        $resourceIndex = route('account.enrollments.resources', $this->enrollment);
        $download = route('account.enrollments.resources.download', [$this->enrollment, $workbook]);
        $lesson = $course->modules()->create(['title' => 'Intro'])->lessons()->create([
            'title' => 'Welcome', 'content' => 'Start reading.',
        ]);
        $this->actingAs($this->learner)->get($resourceIndex)->assertOk()->assertSee('Downloadable Tenses Workbook');
        $this->get($download)->assertDownload('12-Tenses-Workbook.pdf')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->get(route('account.enrollments.show', [$this->enrollment, 'view' => 'curriculum']))
            ->assertOk()->assertSee($resourceIndex);
        $this->get(route('account.lessons.watch', [$this->enrollment, $lesson, 'tab' => 'resources']))
            ->assertOk()->assertSee($download)->assertSee('Start reading.');
        $this->actingAs(User::factory()->create(['role' => 'student']))->get($download)->assertNotFound();
        $this->enrollment->update(['status' => 'cancelled']);
        $this->actingAs($this->learner)->get($download)->assertForbidden();
    }

    public function test_public_enrollment_instructions_match_free_and_paid_access(): void
    {
        $url = route('courses.show', $this->course->getRouteKey());
        $this->get($url)->assertOk()->assertSee('Enroll for free')
            ->assertSee('start learning immediately')->assertDontSee('Request enrollment approval');
        $this->course->update(['pricing' => ['model' => 'paid', 'strategy' => 'fixed', 'currency' => 'USD', 'base_minor' => 1500]]);
        $this->get($url)->assertOk()->assertSee('Request enrollment approval')->assertDontSee('Enroll for free');
    }
}
