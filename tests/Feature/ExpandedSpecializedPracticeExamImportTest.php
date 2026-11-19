<?php

namespace Tests\Feature;

use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Support\AssessmentQuestions;
use Database\Seeders\ExpandedSpecializedPracticeExamSeeder;
use Database\Seeders\SpecializedPracticeExamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ExpandedSpecializedPracticeExamImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_expanded_catalog_adds_twenty_distinct_timed_guest_exams(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->artisan('quizzes:import-starter')->assertSuccessful();
        $this->artisan('exams:publish-specialized-practice')->assertSuccessful();

        $original = CourseExam::where('starter_key', 'like', SpecializedPracticeExamSeeder::KEY_PREFIX.'%')->get();
        $this->assertCount(10, $original);
        $editedQuiz = CourseExam::where('starter_key', 'b2-tech')->sole();
        $editedQuiz->update(['questions' => []]);

        $command = 'exams:publish-expanded-specialized-practice';
        $this->artisan($command)->expectsOutput('Additional Specialized practice exams: 20')->assertSuccessful();
        $this->artisan($command)->expectsOutput('Additional Specialized practice exams: 20')->assertSuccessful();
        $this->assertDatabaseCount('course_exams', 80);
        $this->assertSame([], $editedQuiz->fresh()->questions);

        $exams = CourseExam::where('starter_key', 'like', ExpandedSpecializedPracticeExamSeeder::KEY_PREFIX.'%')
            ->with('type')->orderBy('title')->get();
        $this->assertCount(20, $exams);
        $this->assertSame(['A1' => 4, 'A2' => 4, 'B1' => 4, 'B2' => 4, 'C1' => 4],
            $exams->countBy(fn (CourseExam $exam): ?string => $exam->managerLevel())->all());

        $allPrompts = [];
        foreach ($original->concat($exams) as $exam) {
            $this->assertTrue($exam->published);
            $this->assertTrue($exam->isSpecialized());
            $this->assertSame(120, $exam->time_limit);
            $this->assertSame(2, $exam->attempt_limit);
            $this->assertSame(70, $exam->passing_percent);
            $this->assertCount(50, $exam->questions);
            foreach ($exam->questions as $question) {
                $this->assertNotEmpty($question['feedback']);
                $allPrompts[] = mb_strtolower(trim(($question['passage'] ?? '')."\n".$question['prompt']));
            }
            $this->assertSame(['score' => 100, 'passed' => true], AssessmentQuestions::result(
                ['questions' => $exam->questions, 'passing_percent' => 70],
                array_column($exam->questions, 'correct'),
            ));
        }
        $this->assertCount(1500, array_unique($allPrompts));

        foreach ($exams as $exam) {
            $this->assertSame([5, 5, 5], array_values(array_count_values(array_column($exam->questions, 'passage'))));
            $this->get($exam->publicUrl())->assertRedirect(route('specialized-exams.show', [$exam, 'lang' => 'en']));
        }

        $exam = $exams->first();
        $this->get(route('specialized-exams.show', $exam))->assertOk()
            ->assertSee('50 questions')->assertSee('120 minutes')->assertSee('Start exam');
        $this->withCookie('specialized_exam_browser', str_repeat('b', 64))
            ->post(route('specialized-exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::sole();
        $this->assertNull($attempt->student_id);
        $this->assertSame(7200, $attempt->expires_at->timestamp - now()->timestamp);
        $this->assertCount(50, $attempt->snapshot['questions']);
    }
}
