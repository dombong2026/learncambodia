<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
