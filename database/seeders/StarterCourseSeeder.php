<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\CurriculumLessonRevision;
use App\Models\EditorMedia;
use App\Models\User;
use App\Support\CourseReadiness;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StarterCourseSeeder extends Seeder
{
    private const COVER_ALT = [
        'foundations' => 'Adult English learners practising in a classroom',
        'community' => 'A learner studying a route map',
        'conversation' => 'Two university students talking on campus',
        'workplace' => 'Colleagues discussing a project at a meeting table',
        'academic' => 'A university student reading at a library desk',
        'hospitality' => 'A receptionist welcoming a guest in a hotel lobby',
        'exams' => 'A learner preparing with a laptop, headphones, and a notebook',
        'teaching' => 'English teachers planning a lesson together',
        'young-learners' => 'A teacher arranging picture cards and a storybook',
    ];

    public function run(int $authorId): void
    {
        $author = User::where('role', User::ROLE_ADMIN)->findOrFail($authorId);
        $source = base_path('support/editorial/course-starter');
        $courses = $this->readCourses($source.'/courses.txt');
        $categories = Category::published()->whereIn('code', array_column($courses, 'category'))->get()->keyBy('code');
        $levels = CefrLevel::pluck('code')->all();
        $paths = [];

        try {
            $created = DB::transaction(function () use ($author, $source, $courses, $categories, $levels, &$paths): int {
                $covers = [];
                $total = 0;
                foreach ($courses as $row) {
                    $category = $categories->get($row['category']);
                    if (!$category) {
                        throw new RuntimeException('Missing active category '.$row['category']);
                    }
                    if ($row['level'] !== '-' && !in_array($row['level'], $levels, true)) {
                        throw new RuntimeException('Unknown CEFR level '.$row['level']);
                    }
                    $slug = Str::slug($row['title']);
                    if (Course::where('slug', $slug)->exists()) {
                        continue;
                    }

                    $cover = $covers[$row['cover']] ??= $this->storeCover($source, $row['cover'], $author, $paths);
                    $description = $row['summary']."\n\nThree online modules combine a worked example with hands-on practice.\n"
                        .implode("\n", array_map(fn ($module) => '• '.$module[0].' — '.$module[1], $row['modules']));
                    if ($category->group === 'exam-preparation') {
                        $description .= "\n\nIndependent preparation using original practice activities.";
                    }
                    $course = Course::create([
                        'category_id' => $category->id,
                        'slug' => $slug,
                        'title' => $row['title'],
                        'summary' => $row['summary'],
                        'description' => $description,
                        'cefr_level' => $row['level'] === '-' ? null : $row['level'],
                        'delivery_mode' => 'online',
                        'status' => 'draft',
                        'created_by' => $author->id,
                        'featured_kind' => 'image',
                        'featured_media_id' => $cover->id,
                        'featured_alt' => self::COVER_ALT[$row['cover']],
                    ]);
                    foreach ($row['modules'] as $index => [$title, $lesson, $example, $practice]) {
                        $module = $course->modules()->create(['title' => $title, 'summary' => $lesson, 'position' => $index + 1]);
                        $entry = $module->lessons()->create([
                            'title' => $lesson,
                            'content' => $example."\n\nPractice\n".$practice,
                            'position' => 1,
                            'duration_minutes' => 15,
                            'is_preview' => $index === 0,
                        ]);
                        CurriculumLessonRevision::capture($course, $entry, $author, 'created');
                    }
                    if (!CourseReadiness::ready($course)) {
                        throw new RuntimeException('Course failed readiness checks: '.$course->title);
                    }
                    $total++;
                }

                return $total;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        $this->command?->info('New draft courses: '.$created);
    }

    private function readCourses(string $file): array
    {
        $source = file_get_contents($file);
        if ($source === false) {
            throw new RuntimeException('Could not read course material.');
        }
        $courses = [];
        $slugs = [];
        foreach (preg_split('/\R{2,}/u', trim($source)) as $block) {
            $lines = preg_split('/\R/u', $block);
            $head = array_map('trim', explode('|', array_shift($lines)));
            if (count($head) !== 5 || in_array('', $head, true) || count($lines) !== 3) {
                throw new RuntimeException('Invalid course material.');
            }
            [$category, $level, $cover, $title, $summary] = $head;
            $slug = Str::slug($title);
            if (!$slug || isset($slugs[$slug]) || !isset(self::COVER_ALT[$cover]) || Str::length($title) > 180) {
                throw new RuntimeException('Invalid or duplicate course title: '.$title);
            }
            $slugs[$slug] = true;
            $modules = [];
            foreach ($lines as $line) {
                $fields = array_map('trim', explode('|', $line));
                if (count($fields) !== 4 || in_array('', $fields, true)) {
                    throw new RuntimeException('Invalid module for '.$title);
                }
                $modules[] = $fields;
            }
            $courses[] = compact('category', 'level', 'cover', 'title', 'summary', 'modules');
        }
        if (count($courses) !== 20) {
            throw new RuntimeException('The course pack must contain 20 courses.');
        }

        return $courses;
    }

    private function storeCover(string $source, string $cover, User $author, array &$paths): EditorMedia
    {
        $bytes = file_get_contents($source.'/'.$cover.'.webp');
        if ($bytes === false || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'image/webp') {
            throw new RuntimeException('Invalid cover: '.$cover);
        }
        $path = 'editor-media/course-'.Str::uuid().'.webp';
        $paths[] = $path;
        if (!Storage::disk('local')->put($path, $bytes)) {
            throw new RuntimeException('Could not store cover '.$cover);
        }

        return EditorMedia::create([
            'uploaded_by' => $author->id,
            'context' => 'library',
            'path' => $path,
            'mime' => 'image/webp',
            'original_name' => 'Course Cover - '.Str::headline($cover).'.webp',
            'size' => strlen($bytes),
        ]);
    }
}
