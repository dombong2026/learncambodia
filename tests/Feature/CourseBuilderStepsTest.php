<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\EditorMedia;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CourseBuilderStepsTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_steps_save_and_advance_without_overwriting_previous_sections(): void
    {
        $this->seed(CategorySeeder::class);
        $author = User::factory()->create();
        $author->forceFill(['role' => 'admin'])->save();
        $this->actingAs($author);

        $this->get(route('admin.course-builder.create'))->assertOk()
            ->assertSee('Create draft & continue')->assertDontSee('Select from Media Library');

        $this->post(route('admin.course-builder.store'), [
            'title' => 'Stepped English Course',
            'summary' => 'A clear course objective.',
            'description' => 'Full description for review.',
            'category_id' => Category::where('code', 'GE')->value('id'),
            'delivery_mode' => 'online',
            'continue' => '1',
        ])->assertRedirect();
        $course = Course::firstOrFail();
        $edit = fn (string $step, array $params = []) => route('admin.course-builder.edit', ['course' => $course, 'step' => $step, ...$params]);

        $this->get($edit('media'))->assertOk()->assertSee('Select from Media Library');
        $this->from($edit('media'))->patch(route('admin.course-builder.media', $course), [
            'featured_kind' => 'image', 'continue' => '1',
        ])->assertRedirect($edit('media'))->assertSessionHasErrors('featured_media_id');
        $this->patch(route('admin.course-builder.media', $course), [
            'featured_kind' => 'color', 'featured_color' => '#123456', 'continue' => '1',
        ])->assertRedirect($edit('curriculum'));
        $this->assertSame('#123456', $course->fresh()->featured_value);
        $this->assertSame('Stepped English Course', $course->fresh()->title);
        $this->get($edit('review'))->assertOk()->assertSee('Needs attention');

        $this->post(route('admin.course-builder.modules.store', $course), [
            'title' => 'Greetings', 'position' => 1,
        ])->assertRedirect();
        $module = $course->modules()->firstOrFail();
        $this->post(route('admin.course-builder.lessons.store', [$course, $module]), [
            'title' => 'First lesson', 'content' => 'Hello.', 'position' => 1,
            'duration_minutes' => 10, 'is_preview' => '0', 'continue' => '1',
        ])->assertRedirect($edit('quizzes'));
        $lesson = $module->lessons()->firstOrFail();
        $this->get($edit('curriculum', ['module' => $module->id, 'editor' => 'lesson', 'lesson' => $lesson->id]))
            ->assertOk()->assertSee('Lesson content')->assertSee('Hello.');
        $this->get($edit('quizzes'))->assertOk()->assertSee('You can continue without adding one.');

        $this->post(route('admin.course-builder.submit', $course))->assertRedirect($edit('review'));
        $this->get($edit('review'))->assertOk()->assertSee('Pending Review')->assertSee('This edition is read-only.');
        $this->get($edit('media'))->assertOk()->assertDontSee('Select from Media Library');
        $this->patch(route('admin.course-builder.media', $course), ['featured_kind' => 'none'])->assertStatus(409);
        $this->assertSame('pending_review', $course->fresh()->status);
        $this->assertSame('#123456', $course->fresh()->featured_value);
    }
    public function test_description_html_is_sanitized_rendered_copied_and_removable(): void
    {
        $this->seed(CategorySeeder::class);
        $author = User::factory()->create(['role' => 'admin']);
        $this->actingAs($author);
        Storage::fake('local');
        Storage::disk('local')->put('editor-media/course-cover.png', 'course media fixture');

        $media = EditorMedia::create([
            'context' => 'course', 'uploaded_by' => $author->id, 'path' => 'editor-media/course-cover.png',
            'mime' => 'image/png', 'original_name' => 'Example.png', 'size' => 20,
        ]);
        $other = EditorMedia::create([
            'context' => 'ticket', 'uploaded_by' => $author->id, 'path' => 'editor-media/other.png',
            'mime' => 'image/png', 'original_name' => 'Private.png', 'size' => 20,
        ]);
        $fields = [
            'title' => 'Structured Course',
            'summary' => 'A meaningful course summary.',
            'category_id' => Category::where('code', 'GE')->valueOrFail('id'),
            'delivery_mode' => 'online',
        ];

        $this->post(route('admin.course-builder.store'), $fields + [
            'description_html' => '<img src="/editor-media/'.$other->id.'">',
        ])->assertSessionHasErrors('description_html');
        $this->assertDatabaseMissing('courses', ['title' => $fields['title']]);

        $this->post(route('admin.course-builder.store'), $fields + [
            'description_html' => '<h2>Overview</h2><p><strong>Practice</strong> &amp; review.</p>'
                .'<script>unsafe()</script><img src="/editor-media/'.$media->id.'" onerror="unsafe()" alt="Example">'
                .'<a href="javascript:unsafe()">Objectives</a>',
        ])->assertSessionHasNoErrors();
        $course = Course::sole();
        $mediaUrl = route('editor-media.show', $media);
        $public = route('courses.show', $course->slug);

        $this->assertSame("Overview\nPractice & review.\nObjectives", $course->description);
        $this->assertSame([$media->id], $course->descriptionMedia()->allRelatedIds()->all());
        $this->get(route('admin.course-builder.edit', $course))->assertOk()->assertSee('Full course description')->assertSee('Overview');
        $this->get(route('admin.course-builder.edit', ['course' => $course, 'step' => 'review']))
            ->assertOk()->assertSee('<strong>Practice</strong>', false)->assertDontSee('unsafe()', false);
        auth()->forgetGuards();
        $this->get($mediaUrl)->assertForbidden();
        $this->get($public)->assertNotFound();

        $course->update(['status' => 'published', 'published_at' => now()->subMinute()]);
        $this->get($public)->assertOk()->assertSee('<h2>Overview</h2>', false)->assertDontSee('unsafe()', false);
        $this->get($mediaUrl)->assertOk();

        $reviewer = User::factory()->create(['role' => 'admin']);
        $this->actingAs($reviewer)
            ->post(route('admin.course-builder.revise', $course))->assertRedirect();
        $revision = Course::where('source_course_id', $course->id)->sole();
        $this->assertSame($course->description_html, $revision->description_html);
        $this->assertSame([$media->id], $revision->descriptionMedia()->allRelatedIds()->all());
        $this->patch(route('admin.course-builder.update', $revision), $fields + [
            'slug' => $revision->slug, 'description' => null, 'description_html' => null,
        ])->assertSessionHasNoErrors();
        $this->assertNull($revision->fresh()->description_html);
        $this->assertNull($revision->fresh()->description);
        $this->assertCount(0, $revision->descriptionMedia);
        $this->assertCount(1, $course->descriptionMedia);
    }

}
