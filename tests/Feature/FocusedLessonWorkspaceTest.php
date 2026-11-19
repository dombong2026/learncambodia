<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseExam;
use App\Models\CourseLesson;
use App\Models\EditorMedia;
use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Models\LessonComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class FocusedLessonWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private CourseLesson $lesson;
    private User $student;
    private CourseEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CategorySeeder::class);
        $this->course = Course::create([
            'category_id' => Category::where('code', 'GE')->valueOrFail('id'),
            'slug' => 'movement-course', 'title' => 'Learning through movement',
            'summary' => 'Movement activities', 'description' => 'Practical movement activities.',
            'status' => 'published', 'published_at' => now()->subMinute(),
        ]);
        $module = $this->course->modules()->create(['title' => 'Movement activities', 'position' => 1]);
        $this->lesson = $module->lessons()->create([
            'title' => 'Recognize action words', 'content' => 'Read the activity instructions.',
            'position' => 1, 'duration_minutes' => 15,
        ]);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->enrollment = $this->enroll($this->student);
    }

    public function test_enrollment_url_opens_the_next_incomplete_lesson_and_has_a_curriculum_link(): void
    {
        $next = $this->lesson->module->lessons()->create([
            'title' => 'Practice the action words', 'content' => 'Practice without prompts.',
            'position' => 2, 'duration_minutes' => 12,
        ]);
        $entry = route('account.enrollments.show', [$this->enrollment, 'lang' => 'en']);
        $curriculum = route('account.enrollments.show', [$this->enrollment, 'view' => 'curriculum']);

        $this->actingAs($this->student)->get($entry)->assertRedirect($this->watch(['lang' => 'en']));
        $this->followingRedirects()->get($entry)->assertOk()
            ->assertSee('Course outline')->assertSee('Lesson instructions')->assertDontSee('workspace-sidebar')
            ->assertSee('href="'.e($curriculum).'"', false);

        $this->get($curriculum)->assertOk()->assertSee('Module 1: Movement activities')
            ->assertSee('Open lesson');

        $this->enrollment->lessonProgress()->create(['lesson_id' => $this->lesson->id, 'completed_at' => now()]);
        $this->get($entry)->assertRedirect(route('account.lessons.watch', [$this->enrollment, $next, 'lang' => 'en']));

        $this->enrollment->lessonProgress()->create(['lesson_id' => $next->id, 'completed_at' => now()]);
        $this->get($entry)->assertRedirect($this->watch(['lang' => 'en']));
    }

    public function test_resume_uses_the_last_unfinished_lesson_across_sessions(): void
    {
        $later = $this->lesson->module->lessons()->create([
            'title' => 'Mixed-ability movement', 'content' => 'Choose two adaptations.',
            'position' => 2, 'duration_minutes' => 12,
        ]);
        $laterUrl = route('account.lessons.watch', [$this->enrollment, $later, 'lang' => 'en']);
        $entry = route('account.enrollments.show', [$this->enrollment, 'lang' => 'en']);
        $curriculum = route('account.enrollments.show', [$this->enrollment, 'view' => 'curriculum', 'lang' => 'en']);

        $this->actingAs($this->student)->get($curriculum)->assertOk()->assertSee('Start learning')
            ->assertSee('0%')->assertSee('About this course')->assertSee('Practical movement activities.');
        $this->get($laterUrl)->assertOk()->assertSee('Pause and exit')
            ->assertSee('href="'.e($curriculum).'"', false);

        $this->flushSession();
        $this->actingAs($this->student)->get($curriculum)->assertOk()->assertSee('Resume learning')
            ->assertSeeInOrder(['Last opened lesson', 'Mixed-ability movement']);
        $this->get($entry)->assertRedirect($laterUrl);

        $this->get($this->watch(['viewer' => 'missing-material']))->assertNotFound();
        $otherStudent = User::factory()->create(['role' => 'student']);
        $this->actingAs($otherStudent)->get($this->watch())->assertNotFound();
        $this->assertSame($later->id, $this->enrollment->fresh()->last_opened_lesson_id);

        // Removing a bookmarked lesson lets the learner continue in curriculum order.
        $later->delete();
        $this->assertNull($this->enrollment->fresh()->last_opened_lesson_id);
        $this->actingAs($this->student)->get($entry)->assertRedirect($this->watch(['lang' => 'en']));
    }

    public function test_viewers_and_resources_use_protected_assets_and_remove_site_navigation(): void
    {
        Storage::fake('local');
        $pdf = $this->attach('application/pdf', 'Worksheet.pdf', "%PDF-1.4\n%%EOF");
        $audio = $this->attach('audio/mpeg', 'Action words.mp3', 'Audio fixture');
        $video = $this->attach('video/mp4', 'Model lesson.mp4', 'Video fixture');
        $this->lesson->update([
            'content_html' => '<p>Read the <strong>activity instructions</strong>.</p>'
                .'<figure data-youtube="dQw4w9WgXcQ"></figure>'
                .'<a href="https://example.test/reference">Course reference</a>'
                .'<script>unsafeScript()</script>',
        ]);

        $this->actingAs($this->student)->get($this->watch(['viewer' => 'read']))
            ->assertOk()->assertSeeInOrder(['id="lesson-viewer"', '<h1>', 'class="fl-panel fl-tabs"'], false)
            ->assertSee('Course outline')->assertSee('activity instructions')->assertSee('Full curriculum')
            ->assertDontSee('class="fl-back"', false)->assertDontSee('class="fl-lesson-meta"', false)
            ->assertSee('aria-current="page"', false)->assertDontSee('<header', false)
            ->assertDontSee('<footer', false)->assertDontSee('workspace-sidebar')
            ->assertDontSee('unsafeScript()');
        $this->get($this->watch(['viewer' => 'file-'.$video->id]))
            ->assertOk()->assertSee('<video-skin>', false)->assertSee('<video controls playsinline', false)
            ->assertSee('Lesson instructions');
        $this->get($this->watch(['viewer' => 'file-'.$audio->id]))
            ->assertOk()->assertSee('<audio-skin>', false)->assertSee('<audio controls', false);
        $this->get($this->watch(['viewer' => 'file-'.$pdf->id, 'tab' => 'resources']))
            ->assertOk()->assertSee('Worksheet.pdf')->assertSee('Course reference')->assertSee('Download PDF')
            ->assertSee(route('editor-media.show', [$pdf, 'preview' => 1]));
        $this->get($this->watch(['viewer' => 'video-dQw4w9WgXcQ']))
            ->assertOk()->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertDontSee('<video-skin>', false);
        $preview = route('editor-media.show', [$pdf, 'preview' => 1]);
        $this->get($preview)->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'inline');
        $this->get(route('editor-media.show', $pdf))->assertDownload('Worksheet.pdf')->assertHeader('X-Frame-Options', 'DENY');
        $this->actingAs(User::factory()->create(['role' => 'student']))->get($preview)->assertForbidden();
        $this->get($this->watch(['tab' => 'support', 'fragment' => 1]))->assertNotFound();
    }

    public function test_uploaded_docx_is_rendered_as_escaped_text(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('The optional ZIP extension is unavailable.');
        }
        Storage::fake('local');
        $temporary = tempnam(sys_get_temp_dir(), 'lesson-docx-');
        try {
            $archive = new ZipArchive;
            $archive->open($temporary, ZipArchive::OVERWRITE);
            $archive->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                .'<w:body><w:p><w:r><w:t>Practice &lt;action words&gt;</w:t></w:r></w:p></w:body></w:document>');
            $archive->close();
            $doc = $this->attach('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Warm-up.docx', file_get_contents($temporary));
        } finally {
            unlink($temporary);
        }

        $this->actingAs($this->student)->get($this->watch(['viewer' => 'file-'.$doc->id]))
            ->assertOk()->assertSee('Practice &lt;action words&gt;', false)->assertSee('Download original');
    }

    public function test_lesson_discussion_is_shared_with_enrolled_students_and_reactions_are_idempotent(): void
    {
        $commentUrl = route('account.lessons.comment', [$this->enrollment, $this->lesson]);
        $this->actingAs($this->student)->post($commentUrl, ['comment' => '<script>Example</script>'])->assertRedirect();
        $comment = LessonComment::sole();

        $other = User::factory()->create(['role' => 'student']);
        $otherEnrollment = $this->enroll($other);
        $otherWatch = route('account.lessons.watch', [$otherEnrollment, $this->lesson, 'tab' => 'comment']);
        $this->actingAs($other)->get($otherWatch)->assertOk()->assertSee('&lt;script&gt;Example&lt;/script&gt;', false)
            ->assertDontSee('<script>Example</script>', false);
        $this->post($commentUrl, ['comment' => 'Use a different enrollment'])->assertNotFound();
        $this->post(route('account.lessons.comment', [$otherEnrollment, $this->lesson]), [
            'comment' => 'An enrolled learner replied.', 'parent_id' => $comment->id,
        ])->assertRedirect();

        $reaction = route('account.lessons.react', [$otherEnrollment, $this->lesson, $comment]);
        $this->post($reaction, ['kind' => 'helpful', 'active' => 1])->assertRedirect();
        $this->post($reaction, ['kind' => 'helpful', 'active' => 1])->assertRedirect();
        $this->assertDatabaseCount('lesson_comment_reactions', 1);
        $this->get($otherWatch)->assertOk()->assertSee('An enrolled learner replied.')->assertSee('aria-pressed="true"', false);
        $this->post($reaction, ['kind' => 'helpful', 'active' => 0])->assertRedirect();
        $this->assertDatabaseCount('lesson_comment_reactions', 0);

        $otherEnrollment->update(['status' => 'cancelled']);
        $this->get($otherWatch)->assertForbidden();
        $this->post($reaction, ['kind' => 'helpful', 'active' => 1])->assertForbidden();
    }

    public function test_only_the_participants_can_read_and_reply_to_private_support(): void
    {
        $instructor = $this->assignInstructor();
        $otherInstructor = $this->assignInstructor();
        $student = $this->student;
        $support = route('account.lessons.support', [$this->enrollment, $this->lesson]);
        $inbox = route('instructor.lesson-support.index');
        $thread = route('instructor.lesson-support.show', [$this->enrollment, $this->lesson]);

        $this->actingAs($student)->post($support, [
            'instructor_id' => $instructor->id, 'message' => 'A private question about the activity.',
        ])->assertRedirect();
        $this->get($this->watch(['tab' => 'comment']))->assertOk()->assertDontSee('A private question about the activity.');
        $this->actingAs($otherInstructor)->get($inbox)->assertOk()->assertSee('No private lesson conversations yet.');
        $this->get($thread)->assertNotFound();
        $this->post($thread, ['message' => 'An unauthorized instructor reply'])->assertNotFound();

        $otherStudent = User::factory()->create(['role' => 'student']);
        $otherEnrollment = $this->enroll($otherStudent);
        $this->actingAs($otherStudent)->get(route('account.lessons.watch', [
            $otherEnrollment, $this->lesson, 'tab' => 'support', 'instructor' => $instructor->id, 'fragment' => 1,
        ]))->assertOk()->assertDontSee('A private question about the activity.');

        $this->actingAs($instructor)->get($inbox)->assertOk()->assertSee($this->lesson->title);
        $this->get($thread)->assertOk()->assertSee('A private question about the activity.');
        $this->post($thread, ['message' => 'Try two action words first.'])->assertRedirect($thread);
        $this->actingAs($student)->get($this->watch(['tab' => 'support', 'instructor' => $instructor->id, 'fragment' => 1]))
            ->assertOk()->assertSee('Try two action words first.')->assertDontSee('<html', false);

        $this->course->instructorAssignments()->where('instructor_id', $instructor->id)->update(['status' => 'revoked']);
        $this->post($support, ['instructor_id' => $instructor->id, 'message' => 'An old assignment'])->assertForbidden();
        $this->actingAs($instructor)->get($thread)->assertForbidden();
    }

    public function test_exam_runs_inside_the_focused_layout_and_keeps_working_after_submission(): void
    {
        $type = ExamType::create(['name' => 'Timed assessment', 'kind' => 'exam']);
        $exam = CourseExam::create([
            'course_id' => $this->course->id, 'exam_type_id' => $type->id,
            'kind' => 'exam', 'title' => 'Movement assessment', 'published' => true,
            'time_limit' => 20, 'passing_percent' => 60, 'attempt_limit' => 1,
            'questions' => [['format' => 'choice', 'prompt' => 'Choose the action word.', 'choices' => ['a' => 'Run', 'b' => 'Chair'],
                'correct' => 'a', 'points' => 1, 'feedback' => 'Run is an action.']],
        ]);
        $this->actingAs($this->student)->get($this->watch(['viewer' => 'exam-'.$exam->id]))
            ->assertOk()->assertSee('Start exam')->assertDontSee('Choose the action word.');
        $source = ['from_lesson' => $this->lesson->id];
        $response = $this->post(route('account.exam-attempts.start', [$this->enrollment, $exam] + $source));
        $attempt = ExamAttempt::sole();
        $show = route('account.exam-attempts.show', [$this->enrollment, $attempt] + $source);
        $response->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Choose the action word.')->assertSee('Course outline')
            ->assertDontSee('Run is an action.')->assertDontSee('workspace-sidebar');
        $this->post(route('account.exam-attempts.submit', [$this->enrollment, $attempt] + $source), ['answers' => ['a']])
            ->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Run is an action.');
    }

    private function watch(array $query = []): string
    {
        return route('account.lessons.watch', [$this->enrollment, $this->lesson] + $query);
    }

    private function enroll(User $student): CourseEnrollment
    {
        return CourseEnrollment::create([
            'course_id' => $this->course->id, 'student_id' => $student->id, 'status' => 'approved',
        ]);
    }

    private function assignInstructor(): User
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $this->course->instructorAssignments()->create([
            'instructor_id' => $instructor->id, 'assigned_by' => User::factory()->create(['role' => 'admin'])->id,
            'status' => 'active', 'assigned_at' => now(),
        ]);

        return $instructor;
    }

    private function attach(string $mime, string $filename, string $contents): EditorMedia
    {
        $path = 'editor-media/'.$filename;
        Storage::disk('local')->put($path, $contents);
        $file = EditorMedia::create([
            'uploaded_by' => User::factory()->create(['role' => 'admin'])->id, 'context' => 'lesson',
            'path' => $path, 'mime' => $mime, 'original_name' => $filename, 'size' => strlen($contents),
        ]);
        $this->lesson->media()->attach($file);

        return $file;
    }
}
