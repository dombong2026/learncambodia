<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Course;
use App\Models\CurriculumLessonRevision;
use App\Models\EditorMedia;
use App\Models\User;
use App\Support\CourseReadiness;
use App\Support\EditorHtml;
use App\Support\LessonQuiz;
use App\Support\ManagedFiles;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Run from the application's root as its normal service user.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$title = 'How to write a professional CV & cover letter';
$slug = 'how-to-write-a-professional-cv-and-cover-letter';
$source = __DIR__;
$rows = json_decode(file_get_contents($source.'/course.json'), true, flags: JSON_THROW_ON_ERROR);

if (count($rows) !== 8 || count(array_unique(array_column($rows, 'module'))) !== 4) {
    throw new RuntimeException('The course bundle must contain four modules and eight lessons.');
}

$chosenId = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT);
$admins = User::query()->where('role', User::ROLE_ADMIN);
if ($chosenId) {
    $author = $admins->findOrFail($chosenId);
} else {
    $matches = (clone $admins)->get(['id', 'name'])->filter(
        fn (User $user) => in_array(Str::slug($user->name), ['ton-samith', 'samith-ton'], true)
    )->values();
    $author = $matches->count() === 1 ? $matches->first() : $admins->sole();
}

$category = Category::published()->where('code', 'BE')->first()
    ?? Category::published()->where('code', 'GE')->firstOrFail();

function storeCourseVisual(string $file, string $name, string $context, User $author, array &$paths): EditorMedia
{
    $bytes = file_get_contents($file);
    if ($bytes === false || (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'image/webp') {
        throw new RuntimeException('Invalid course visual '.$name);
    }
    $path = 'editor-media/course-'.Str::uuid().'.webp';
    $paths[] = $path;
    if (!ManagedFiles::put(ManagedFiles::disk(), $path, $bytes)) {
        throw new RuntimeException('Failed to store '.$name);
    }

    return EditorMedia::create([
        'uploaded_by' => $author->id,
        'context' => $context,
        'path' => $path,
        'mime' => 'image/webp',
        'original_name' => $name.'.webp',
        'size' => strlen($bytes),
    ]);
}

$paths = [];
try {
    $course = DB::transaction(function () use ($category, $author, $title, $slug, $source, $rows, &$paths): Course {
        $existing = Course::withTrashed()->where('slug', $slug)->lockForUpdate()->first();
        if ($existing) {
            if ($existing->trashed() || $existing->title !== $title || $existing->lessons()->count() !== 8) {
                throw new RuntimeException('The course slug is already in use.');
            }
            return $existing;
        }

        $cover = storeCourseVisual($source.'/assets/cover.webp', 'Professional CV and cover letter', 'library', $author, $paths);
        $description = <<<'HTML'
<h2>Build an application for a real vacancy</h2>
<p>Use one job posting to plan and write a targeted CV and a one-page cover letter. Follow a fictional Phnom Penh applicant through each stage, from reading the advert to checking the final files.</p>
<h3>What you will practise</h3>
<ol>
<li>Read the vacancy, choose a format, and map requirements to verifiable evidence.</li>
<li>Order CV sections and turn duties into evidence bullets.</li>
<li>Write an employer-specific opening, two evidence paragraphs, and a courteous close.</li>
<li>Check the files and score your finished application with a 20-point rubric.</li>
</ol>
<p>Four modules and eight lessons, about 4¼ hours. Includes visual walkthroughs, a worked CV and letter, planning worksheets, and a knowledge check with feedback.</p>
HTML;
        $description = EditorHtml::clean($description);
        $course = Course::create([
            'category_id' => $category->id,
            'slug' => $slug,
            'title' => $title,
            'summary' => 'Plan, write, and check a tailored job application using visual guides, worked examples, and practice worksheets.',
            'description' => EditorHtml::courseText($description),
            'description_html' => $description,
            'delivery_mode' => 'online',
            'content_language' => 'English',
            'public_requirement' => 'Bring an English-language vacancy. A fictional practice vacancy is provided.',
            'status' => 'draft',
            'created_by' => $author->id,
            'featured_kind' => 'image',
            'featured_media_id' => $cover->id,
            'featured_alt' => 'Cambodian job applicant reviewing two documents at a desk',
        ]);

        foreach (array_chunk($rows, 2) as $moduleIndex => $lessons) {
            if ($lessons[0]['module'] !== $lessons[1]['module']) {
                throw new RuntimeException('Invalid module ordering.');
            }
            $module = $course->modules()->create([
                'title' => $lessons[0]['module'],
                'position' => $moduleIndex + 1,
            ]);
            foreach ($lessons as $index => $row) {
                if (!preg_match('/^0[1-8]\.webp$/D', $row['visual'])) {
                    throw new RuntimeException('Invalid artwork name.');
                }
                $media = storeCourseVisual($source.'/assets/'.$row['visual'], $row['title'], 'lesson', $author, $paths);
                $html = EditorHtml::clean(str_replace('VISUAL_SOURCE', '/editor-media/'.$media->id, $row['html']), lesson: true);
                $content = EditorHtml::courseText($html);
                if (EditorHtml::sourceIds($html, lesson: true) !== [$media->id] || blank($content) || mb_strlen($content) > 50000) {
                    throw new RuntimeException('Invalid lesson content for '.$row['title']);
                }
                $questions = isset($row['quiz'])
                    ? LessonQuiz::validateQuestions(Request::create('/', 'POST', ['quiz_questions' => $row['quiz']]))
                    : null;
                $lesson = $module->lessons()->create([
                    'title' => $row['title'],
                    'content' => $content,
                    'content_html' => $html,
                    'quiz_questions' => $questions,
                    'position' => $index + 1,
                    'duration_minutes' => $row['minutes'],
                    'is_preview' => $moduleIndex === 0 && $index === 0,
                ]);
                $lesson->media()->attach($media->id);
                CurriculumLessonRevision::capture($course, $lesson, $author, 'created');
            }
        }
        if (!CourseReadiness::ready($course)) {
            throw new RuntimeException('The imported course did not pass readiness checks.');
        }

        return $course;
    });
} catch (Throwable $exception) {
    if ($paths !== []) {
        ManagedFiles::disk()->delete($paths);
    }
    throw $exception;
}

$lessons = $course->lessons()->with('media')->get();
$mediaCount = $lessons->sum(fn ($lesson) => $lesson->media->count());
$questionCount = $lessons->sum(fn ($lesson) => count($lesson->quiz_questions ?? []));
$allMedia = $lessons->flatMap(fn ($lesson) => $lesson->media)->push($course->featuredMedia);
if ($mediaCount !== 8 || $questionCount !== 8 || $lessons->sum('duration_minutes') !== 255
    || $allMedia->contains(fn ($item) => !$item || !ManagedFiles::disk()->exists($item->path))) {
    throw new RuntimeException('Course verification failed.');
}

echo json_encode([
    'status' => $course->status,
    'category' => $course->category->code,
    'modules' => $course->modules()->count(),
    'lessons' => $lessons->count(),
    'minutes' => $lessons->sum('duration_minutes'),
    'lesson_visuals' => $mediaCount,
    'checkpoint_questions' => $questionCount,
    'ready_for_review' => CourseReadiness::ready($course),
    'dashboard' => route('admin.course-builder.edit', ['course' => $course, 'step' => 'curriculum'], absolute: false),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), PHP_EOL;
