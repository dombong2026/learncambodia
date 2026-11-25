<?php

namespace App\Support;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseExam;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CourseQuiz;
use App\Models\HomepageCampaignItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Explicit public metadata projections; no private content or write engine. */
final class McpOAuthReadCatalog
{
    private const DESCRIPTIONS = [
        'list_courses' => 'List discoverable published course metadata.',
        'list_modules' => 'List module titles and positions in discoverable courses.',
        'list_lessons' => 'List lesson titles and durations in discoverable courses. No lesson bodies or answers.',
        'list_quizzes' => 'List published standalone quiz metadata. No questions or answer keys.',
        'list_exams' => 'List published standalone exam metadata. No questions or answer keys.',
        'list_categories' => 'List categories visible in the public catalog.',
        'list_articles' => 'List published article metadata.',
        'list_campaigns' => 'List published homepage campaign metadata. No campaign payload or answer keys.',
        'get_platform_analytics' => 'Read aggregate platform statistics and assessment count breakdowns.',
    ];

    public static function tools(): array
    {
        $tools = [];
        foreach (self::DESCRIPTIONS as $name => $description) {
            $properties = $name === 'get_platform_analytics' ? (object) [] : [
                'id' => ['type' => 'integer', 'minimum' => 1],
                'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 1],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 25],
            ];
            $tools[] = [
                'name' => $name,
                'description' => $description,
                'inputSchema' => ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false],
                'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
            ];
        }

        return $tools;
    }

    public static function supports(string $name): bool
    {
        return isset(self::DESCRIPTIONS[$name]);
    }

    public static function read(string $name, array $args): array
    {
        if (! self::supports($name)) {
            throw ValidationException::withMessages(['tool' => 'Unsupported read tool.']);
        }
        if ($name === 'get_platform_analytics') {
            self::rejectUnknown($args, []);
            return self::analytics();
        }

        self::rejectUnknown($args, ['id', 'page', 'limit']);
        $data = Validator::make($args, [
            'id' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ])->validate();

        [$query, $columns] = match ($name) {
            'list_courses' => [Course::query()->discoverable(),
                ['id', 'title', 'slug', 'summary', 'status', 'category_id', 'content_language', 'updated_at']],
            'list_modules' => [CourseModule::query()->whereHas('course', fn (Builder $q) => $q->discoverable()),
                ['id', 'course_id', 'title', 'position']],
            'list_lessons' => [CourseLesson::query()->whereHas('module.course', fn (Builder $q) => $q->discoverable()),
                ['id', 'course_module_id', 'title', 'position', 'duration_minutes', 'is_preview']],
            'list_quizzes', 'list_exams' => [self::standalone($name === 'list_quizzes' ? 'quiz' : 'exam'),
                ['id', 'title', 'kind', 'content_language', 'time_limit', 'passing_percent', 'attempt_limit']],
            'list_categories' => [Category::query()->visibleInCatalog(), ['id', 'name', 'slug', 'group']],
            'list_articles' => [ContentEntry::query()->publishedArticles(),
                ['id', 'title', 'slug', 'summary', 'category_id', 'content_language', 'published_at']],
            'list_campaigns' => [HomepageCampaignItem::query()->published()->where('published_at', '<=', now()),
                ['id', 'title', 'slug', 'kind', 'version', 'published_at']],
        };

        $project = static fn ($model) => collect($columns)
            ->mapWithKeys(fn ($column) => [$column => $model->{$column}])->all();
        if (isset($data['id'])) {
            return ['items' => [$project($query->findOrFail((int) $data['id'], $columns))], 'total' => 1];
        }

        $page = (int) ($data['page'] ?? 1);
        $limit = (int) ($data['limit'] ?? 25);
        $total = (clone $query)->count();
        return [
            'items' => $query->orderBy('id')->forPage($page, $limit)->get($columns)->map($project)->all(),
            'total' => $total, 'page' => $page, 'limit' => $limit,
        ];
    }

    private static function rejectUnknown(array $args, array $allowed): void
    {
        if (array_diff(array_keys($args), $allowed)) {
            throw ValidationException::withMessages(['arguments' => 'Unknown read argument.']);
        }
    }

    private static function standalone(string $kind): Builder
    {
        return CourseExam::query()->whereNull('course_id')->where('kind', $kind)
            ->where('published', true)->whereNull('deactivated_at');
    }

    public static function analytics(): array
    {
        $courseQuizzes = CourseQuiz::query()->count();
        $assessmentQuizzes = CourseExam::query()->where('kind', 'quiz')->count();
        return ['items' => [
            'courses_total' => Course::query()->count(),
            'courses_published' => Course::query()->where('status', 'published')->count(),
            'courses_discoverable' => Course::query()->discoverable()->count(),
            'lessons_total' => CourseLesson::query()->count(),
            'quizzes_total' => $courseQuizzes + $assessmentQuizzes,
            'course_practice_quizzes_total' => $courseQuizzes,
            'assessment_quizzes_total' => $assessmentQuizzes,
            'standalone_quizzes_published' => self::standalone('quiz')->count(),
            'exams_total' => CourseExam::query()->where('kind', 'exam')->count(),
            'standalone_exams_published' => self::standalone('exam')->count(),
            'enrollments_total' => CourseEnrollment::query()->count(),
            'students_total' => User::query()->where('role', User::ROLE_STUDENT)->count(),
            'editorial_published' => ContentEntry::query()->where('status', 'published')->count(),
            'campaigns_published' => HomepageCampaignItem::query()->where('status', 'published')->count(),
            'homepage_campaign_items_visible' => HomepageCampaignItem::query()->published()->where('published_at', '<=', now())->count(),
        ], 'note' => 'Aggregate counts only; no personal student data. Quizzes total combines course practice quizzes and assessment quiz records; excludes embedded lesson questions and campaign units. Campaign counts cover HomepageCampaignItem records only. Published course status and discoverable catalog counts are distinct.'];
    }
}
