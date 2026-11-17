<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\CourseExam;
use App\Models\ExamType;
use App\Models\PublicProfile;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PublicRecordIdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_ids_are_unique_and_keep_links_working_when_titles_match_or_change(): void
    {
        $this->seed(CategorySeeder::class);
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Same Name']);
        $teacher = User::factory()->create(['role' => 'instructor', 'name' => 'Same Name']);
        $this->assertMatchesRegularExpression('/^[1-9][0-9]{7}$/', (string) $admin->public_id);
        $this->assertNotEquals($admin->public_id, $teacher->public_id);

        $payload = ['title' => 'English Foundations', 'category_id' => Category::where('code', 'GE')->valueOrFail('id'),
            'summary' => 'Study English.', 'description' => 'Description', 'delivery_mode' => 'online'];
        $this->actingAs($admin)->post(route('admin.course-builder.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('admin.course-builder.store'), $payload)->assertSessionHasNoErrors();
        $courses = Course::orderBy('id')->get();
        $this->assertCount(2, $courses);
        [$first, $second] = $courses;
        Course::query()->update(['status' => 'published', 'published_at' => now()]);
        $this->assertMatchesRegularExpression('/^[1-9][0-9]{5}-english-foundations$/', $first->getRouteKey());
        $this->assertMatchesRegularExpression('/^[1-9][0-9]{5}-english-foundations$/', $second->getRouteKey());
        $savedCoursePath = route('courses.show', $first->getRouteKey());
        $first->update(['title' => 'Renamed English Foundations']);
        $this->get($savedCoursePath)->assertOk()->assertSee('Renamed English Foundations');
        $this->get('/courses/'.$first->slug)->assertOk();
        $this->get(route('courses.show', $second->getRouteKey()))->assertOk()->assertDontSee('Renamed English Foundations</h1>', false);

        $profile = PublicProfile::create(['user_id' => $teacher->id, 'kind' => 'instructor', 'display_name' => 'Same Name', 'headline' => 'English teacher']);
        $profile->forceFill(['status'=>'published', 'consented_at'=>now(), 'published_at'=>now()])->save();
        $this->get('/instructors/'.$teacher->public_id.'-same-name')->assertOk();
        $this->get('/instructors/'.$profile->id)->assertOk();
        $this->get('/instructors/'.$admin->public_id.'-same-name')->assertNotFound();
        $this->get(route('admin.users.index', ['q' => $teacher->public_id]))->assertOk()->assertSee((string) $teacher->public_id);
        $this->get(route('admin.users.show', $teacher))->assertOk()->assertSee('User #'.$teacher->public_id);
        $this->actingAs($teacher)->get(route('admin.users.show', $teacher))->assertForbidden();
    }

    public function test_migration_assigns_ids_to_legacy_rows_and_keeps_existing_ids(): void
    {
        $legacy = DB::table('users')->insertGetId(User::factory()->make()->getAttributes());
        $current = User::factory()->create();
        $migration = require database_path('migrations/2026_11_17_000041_add_public_record_ids.php');
        $migration->up();

        $legacyId = User::findOrFail($legacy)->public_id;
        $this->assertMatchesRegularExpression('/^[1-9][0-9]{7}$/', (string) $legacyId);
        $this->assertNotEquals($legacyId, $current->public_id);
        $this->assertEquals($current->public_id, $current->fresh()->public_id);
    }

    public function test_content_and_assessment_ids_have_no_cross_type_collisions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $ids = [];
        foreach (['policies' => 'policy', 'posts' => 'post', 'pages' => 'page'] as $kind => $type) {
            for ($i = 0; $i < 2; $i++) {
                $this->post(route('admin.editorial.store', ['kind' => $kind]), [
                    'title' => 'Shared title', 'summary' => 'Summary', 'body' => 'Body',
                ])->assertSessionHasNoErrors();
                $record = ContentEntry::where('type', $type)->latest('id')->firstOrFail();
                $record->update(['status' => 'published', 'published_at' => now()]);
                $ids[] = $record->public_id;
                $this->assertStringContainsString($record->public_id.'-shared-title', $record->publicUrl());
                $this->get($record->publicUrl())->assertOk()->assertSee('Shared title');
            }
        }

        $exams = [];
        foreach (['exam', 'quiz'] as $kind) {
            $type = ExamType::create(['kind' => $kind, 'name' => 'Sample']);
            $record = CourseExam::create(['kind' => $kind, 'exam_type_id' => $type->id, 'title' => 'Shared title',
                'questions' => [], 'time_limit' => 5, 'passing_percent' => 70, 'attempt_limit' => 1, 'published' => true]);
            $exams[] = $record;
            $ids[] = $record->public_id;
            $this->get($record->publicUrl())->assertOk();
            $this->get('/assessments/'.$record->id)->assertOk();
        }
        $this->get('/exams/'.$exams[1]->getRouteKey())->assertNotFound();
        $this->get('/quizzes/'.$exams[0]->getRouteKey())->assertNotFound();
        $this->assertCount(8, array_unique($ids));
        $this->assertSame(8, DB::table('public_record_ids')->whereIn('id', $ids)->count());
        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[1-9][0-9]{5}$/', (string) $id);
        }
    }
}
