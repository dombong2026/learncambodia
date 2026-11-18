<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Models\IssuedCertificate;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ExaminationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_review_is_available_to_its_admin_or_superadmin_author(): void
    {
        foreach (['admin' => 'approve', 'superadmin' => 'reject'] as $role => $decision) {
            $account = User::factory()->create(['role' => $role]);
            $course = $this->course($account, $role);
            $this->actingAs($account)->post(route('admin.course-builder.submit', $course))->assertRedirect();
            $this->get('/dashboard/reviews?focus=courses&lang=en')->assertOk()
                ->assertSee('Review &amp; Approvals', false)->assertDontSee('Review &amp;amp; Approvals', false)
                ->assertSee('Course Manager')->assertSee('Examination');
            $this->post(route('admin.reviews.course', $course), ['decision' => $decision, 'note' => 'Academic review'])->assertRedirect();
            $this->assertSame($decision === 'approve' ? 'published' : 'rejected', $course->fresh()->status);
            $this->assertSame($account->id, $course->fresh()->reviewed_by);
        }
    }

    public function test_exam_and_certificate_full_workflow(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'superadmin']);
        $course = $this->course($admin, 'course');
        $course->update(['status' => 'published', 'published_at' => now()->subMinute()]);
        $learner = User::factory()->create();
        $other = User::factory()->create();
        $enrollment = CourseEnrollment::create(['course_id'=>$course->id, 'student_id'=>$learner->id, 'status'=>'approved']);
        $this->actingAs($admin);
        $this->post(route('admin.exam-types.store'), ['name'=>'Final exam'])->assertSessionHasNoErrors();
        $type = ExamType::sole();
        $this->patch(route('admin.exam-types.update', $type), ['name'=>'Course final'])->assertSessionHasNoErrors();
        $this->get(route('admin.exams.create'))->assertOk()->assertSee('Course final');
        $upload = $this->postJson(route('editor-media.store'), [
            'context' => 'exam', 'file' => UploadedFile::fake()->createWithContent('guide.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertCreated();
        $resource = $upload->json('url');
        $data = [
            'title'=>'End of course exam', 'course_id'=>$course->id, 'exam_type_id'=>$type->id,
            'instructions_html'=>'<h2>Read the guide</h2><a data-resource="file" href="'.$resource.'">Guide</a><script>alert(1)</script>',
            'published'=>true, 'gamified'=>true, 'time_limit'=>30, 'passing_percent'=>70, 'attempt_limit'=>1,
            'questions'=>[
                ['prompt'=>'Choose the example', 'a'=>'An example', 'b'=>'An alternative', 'correct'=>'a', 'points'=>5],
                ['format'=>'boolean', 'prompt'=>'Is this an exercise?', 'correct'=>'true', 'points'=>2],
                ['format'=>'short', 'prompt'=>'Name the country', 'correct'=>'Cambodia', 'points'=>3],
            ],
        ];
        $this->post(route('admin.exams.store'), $data)->assertSessionHasNoErrors();
        $exam = CourseExam::sole();
        $this->assertStringNotContainsString('<script>', $exam->instructions_html);
        $this->get(route('admin.exams.edit', $exam))->assertOk()->assertSee('Questions');
        $this->post(route('admin.certificate-types.store'), ['name'=>'Completion'])->assertSessionHasNoErrors();
        $this->post(route('admin.certificate-templates.store'), [
            'name'=>'Course completion', 'certificate_type_id'=>CertificateType::sole()->id,
            'heading'=>'Completion Certificate', 'body'=>'Awarded to {learner} for {course}.',
            'issuer'=>'LearnCambodia', 'accent'=>'#986310', 'orientation'=>'landscape', 'courses'=>[$course->id],
        ])->assertSessionHasNoErrors();
        $template = CertificateTemplate::sole();
        $this->get(route('admin.certificate-templates.edit', $template))->assertOk()->assertSee('Assigned courses');
        $this->get(route('admin.certificates.index'))->assertOk()->assertSee($learner->name);
        $issue = route('admin.certificates.issue', $enrollment);
        $this->post($issue, ['template_id'=>$template->id])->assertUnprocessable();

        $this->actingAs($learner)->get(route('learning.exam.show', $course->slug))->assertOk()->assertSee($exam->title);
        $this->get(route('account.enrollments.show', [$enrollment, 'view' => 'curriculum']))->assertOk()->assertSee('Start exam');
        $this->get($resource)->assertForbidden();
        $this->post(route('account.exam-attempts.start', [$enrollment,$exam]))->assertRedirect();
        $attempt = ExamAttempt::sole();
        $screen = route('account.exam-attempts.show', [$enrollment,$attempt]);
        $this->get($screen)->assertOk()->assertSee('Choose the example')->assertDontSee('Correct answer');
        $this->get($resource)->assertDownload('guide.pdf');
        $this->actingAs($other)->get($screen)->assertNotFound();
        $this->get($resource)->assertForbidden();
        $this->actingAs($learner)->post(route('account.exam-attempts.submit', [$enrollment,$attempt]), ['answers'=>['a', 'true', '  CAMBODIA  ']])->assertRedirect($screen);
        $this->get($screen)->assertSee('100% scored')->assertSee('Milestone earned: Exam passed');
        $this->get($resource)->assertForbidden();
        $this->post(route('account.exam-attempts.start', [$enrollment,$exam]))->assertConflict();

        $enrollment->lessonProgress()->create(['lesson_id'=>$course->lessons()->sole()->id,'completed_at'=>now()]);
        $this->actingAs($admin)->post($issue, ['template_id'=>$template->id])->assertRedirect();
        $this->post($issue, ['template_id'=>$template->id])->assertRedirect();
        $certificate = IssuedCertificate::sole();
        $url = route('certificates.show', $certificate->code);
        $this->actingAs($learner)->get($url)->assertOk()->assertSee('Completion Certificate')->assertSee($learner->name);
        $this->actingAs($other)->get($url)->assertNotFound();
        $this->actingAs($admin)->patch(route('admin.certificates.revoke', $certificate))->assertRedirect();
        $this->actingAs($learner)->get($url)->assertNotFound();
    }

    private function course(User $author, string $slug): Course
    {
        $this->seed(CategorySeeder::class);
        $course = Course::create([
            'slug'=>$slug, 'title'=>'Assessment Course '.$slug, 'summary'=>'Course summary',
            'description'=>'Course description', 'category_id'=>Category::where('code', 'TEYL')->valueOrFail('id'),
            'created_by'=>$author->id, 'status'=>'draft',
        ]);
        $course->modules()->create(['title'=>'Module'])->lessons()->create([
            'title'=>'Lesson', 'content'=>'Lesson text', 'duration_minutes'=>10,
        ]);

        return $course;
    }
}
