<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CurriculumLessonRevision;
use App\Models\User;
use App\Support\CourseReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class RichLessonContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_rich_lesson_content_and_attachments_follow_enrollment_access(): void
    {
        $this->seed(\Database\Seeders\CategorySeeder::class);
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::create([
            'category_id' => Category::where('code', 'GE')->valueOrFail('id'),
            'slug' => 'lesson-test', 'title' => 'Lesson test', 'summary' => 'Lesson overview',
            'description' => 'A complete description.', 'status' => 'draft', 'created_by' => $admin->id,
        ]);
        $module = $course->modules()->create(['title' => 'Module']);
        $this->actingAs($admin);
        $upload = $this->postJson(route('editor-media.store'), [
            'context' => 'lesson',
            'file' => UploadedFile::fake()->createWithContent('handout.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertCreated();
        $media = $upload->json('url');
        $fields = ['title' => 'Learn with examples', 'position' => 1, 'duration_minutes' => 15, 'is_preview' => false];
        $this->post(route('admin.course-builder.lessons.store', [$course, $module]), $fields + [
            'content_html' => '<h2>Instructions</h2><figure data-youtube="dQw4w9WgXcQ"></figure>'
                .'<pre><code data-language="html">&lt;button&gt;Example&lt;/button&gt;</code></pre>'
                .'<a data-resource="file" href="'.$media.'">Handout</a><a data-resource="button" href="/reference">Read more</a>'
                .'<iframe src="https://example.test"></iframe><script>alert(1)</script>',
        ])->assertSessionHasNoErrors();
        $lesson = $module->lessons()->sole();
        $this->assertSame([$upload->json('id')], $lesson->media()->allRelatedIds()->all());
        $this->assertSame($lesson->content_html, CurriculumLessonRevision::sole()->content_html);
        $this->assertStringNotContainsString('example.test', $lesson->content_html);
        $this->assertStringNotContainsString('<script>', $lesson->content_html);

        $originalFingerprint = CourseReadiness::fingerprint($course);
        $lesson->update(['content_html' => str_replace('data-language="html"', 'data-language="css"', $lesson->content_html)]);
        $this->assertNotSame($originalFingerprint, CourseReadiness::fingerprint($course));

        $student = User::factory()->create(['role' => 'student']);
        $enrollment = CourseEnrollment::create(['course_id' => $course->id, 'student_id' => $student->id, 'status' => 'approved']);
        $this->actingAs($student)->get($media)->assertForbidden();

        $course->update(['status' => 'published', 'published_at' => now()->subMinute()]);
        $this->get(route('account.lessons.watch', [$enrollment, $lesson]))->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('data-resource="button"', false)
            ->assertSee('&lt;button&gt;Example&lt;/button&gt;', false);
        $this->get($media)->assertOk()->assertDownload('handout.pdf');
        $this->actingAs(User::factory()->create(['role' => 'student']))->get($media)->assertForbidden();
        $this->actingAs($student);
        $enrollment->update(['status' => 'rejected']);
        $this->get($media)->assertForbidden();

        $this->actingAs($admin)->post(route('admin.course-builder.revise', $course))->assertRedirect();
        $copy = Course::where('source_course_id', $course->id)->sole();
        $copiedLesson = $copy->modules()->sole()->lessons()->sole();
        $this->assertSame($lesson->fresh()->content_html, $copiedLesson->content_html);
        $this->assertSame([$upload->json('id')], $copiedLesson->media()->allRelatedIds()->all());
    }
}
