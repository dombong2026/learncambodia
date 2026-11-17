<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\EditorMedia;
use App\Models\User;
use App\Support\CourseReadiness;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CefrLevelSeeder;
use Database\Seeders\StarterCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class StarterCourseMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_courses_have_their_own_curricula_and_valid_featured_images(): void
    {
        Storage::fake('local');
        $this->seed([CategorySeeder::class, CefrLevelSeeder::class]);
        $author = User::factory()->create();
        $author->forceFill(['role' => User::ROLE_ADMIN])->save();
        $seeder = app(StarterCourseSeeder::class);
        $seeder->run($author->id);
        $seeder->run($author->id);

        $courses = Course::with(['category', 'featuredMedia'])->withCount(['modules', 'lessons'])->get();
        $this->assertCount(20, $courses);
        $this->assertCount(17, $courses->pluck('category_id')->unique());
        $this->assertSame(9, EditorMedia::count());
        foreach ($courses as $course) {
            $this->assertTrue($course->category->is_published);
            $this->assertSame('draft', $course->status);
            $this->assertSame(3, $course->modules_count);
            $this->assertSame(3, $course->lessons_count);
            $this->assertTrue(CourseReadiness::ready($course));
            $this->assertSame('image/webp', $course->featuredMedia->mime);
            Storage::disk('local')->assertExists($course->featuredMedia->path);
        }

        $course = $courses->firstWhere('slug', 'english-for-hotel-reception-and-guest-service');
        $this->assertSame('ESP', $course->category->code);
        $this->assertSame('B1', $course->cefr_level);
        $image = route('editor-media.show', $course->featuredMedia);
        $this->get($image)->assertForbidden();
        $this->get(route('courses.show', $course->slug))->assertNotFound();
        $this->actingAs($author)->get(route('admin.course-builder.edit', ['course' => $course, 'step' => 'media']))
            ->assertOk()->assertSee('Featured Media')->assertSee($image);

        $this->delete(route('admin.media-library.destroy', $course->featuredMedia))
            ->assertSessionHasErrors('media');

        $course->update(['status' => 'published', 'published_at' => now()]);
        $this->app['auth']->forgetGuards();
        $this->get(route('courses.show', $course->slug))->assertOk()
            ->assertSeeInOrder(['data-detail-heading', $course->title, '<div class="detail-cover mt-4">', $image], false)
            ->assertSeeInOrder(['3 lessons', 'Online', 'Published after academic review', 'Start learning'])
            ->assertSeeInOrder(['Course instructors', 'Getting started', 'Continue exploring', 'Study readiness'])
            ->assertDontSee('aria-label="Breadcrumb"', false);
        $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/webp');
    }
}
