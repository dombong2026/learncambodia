<?php

namespace Tests\Feature;

use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Support\AssessmentQuestions;
use Database\Seeders\SpecializedPracticeExamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SpecializedPracticeExamImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ten_specialized_exams_publish_with_fifty_scored_questions_and_two_hours(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->artisan('quizzes:import-starter')->assertSuccessful();
        $this->artisan('exams:publish-specialized-practice')->expectsOutput('Specialized practice exams: 10')->assertSuccessful();
        $this->artisan('exams:publish-specialized-practice')->expectsOutput('Specialized practice exams: 10')->assertSuccessful();

        $exams = CourseExam::where('starter_key', 'like', SpecializedPracticeExamSeeder::KEY_PREFIX.'%')
            ->with('type')->orderBy('title')->get();
        $this->assertCount(10, $exams);
        $this->assertDatabaseCount('course_exams', 60);
        $this->assertSame(['A1' => 2, 'A2' => 2, 'B1' => 2, 'B2' => 2, 'C1' => 2], $exams->countBy(fn (CourseExam $exam): ?string => $exam->managerLevel())->all());
        foreach ($exams as $exam) {
            $this->assertTrue($exam->isSpecialized());
            $this->assertTrue($exam->published);
            $this->assertFalse($exam->live_feedback);
            $this->assertSame(120, $exam->time_limit);
            $this->assertSame(2, $exam->attempt_limit);
            $this->assertCount(50, $exam->questions);
            $this->assertSame(array_fill(0, 40, 'choice'), array_slice(array_column($exam->questions, 'format'), 0, 40));
            $this->assertSame([5, 5], array_values(array_count_values(array_column($exam->questions, 'passage'))));
            $this->assertSame(['score' => 100, 'passed' => true], AssessmentQuestions::result(
                ['questions' => $exam->questions, 'passing_percent' => $exam->passing_percent],
                array_column($exam->questions, 'correct'),
            ));
            $this->get($exam->publicUrl())->assertRedirect(route('specialized-exams.show', [$exam, 'lang' => 'en']));
        }

        $exam = $exams->first();
        $this->get(route('specialized-exams.show', $exam))->assertOk()
            ->assertSee('50 questions')->assertSee('120 minutes')->assertSee('Start exam');
        $this->withCookie('specialized_exam_browser', str_repeat('a', 64))
            ->post(route('specialized-exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::sole();
        $this->assertNull($attempt->student_id);
        $this->assertSame(7200, $attempt->expires_at->timestamp - now()->timestamp);
        $this->assertCount(50, $attempt->snapshot['questions']);
    }
}
