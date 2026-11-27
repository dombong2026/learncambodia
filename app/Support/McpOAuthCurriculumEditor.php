<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CurriculumLessonRevision;
use App\Models\McpActionAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Owned native draft curriculum edits. No deletion, publication or rich media replacement. */
final class McpOAuthCurriculumEditor
{
    public static function catalog(): array
    {
        if (!McpOAuthDraftScopePolicy::editEnabled()) {
            return [];
        }
        $id = ['type' => 'integer', 'minimum' => 1];
        $str = static fn (int $max): array => ['type' => 'string', 'minLength' => 1, 'maxLength' => $max];
        $obj = static fn (array $p): array => ['type' => 'object', 'properties' => $p, 'required' => array_keys($p), 'additionalProperties' => false];
        $arr = static fn (array $item, int $max): array => ['type' => 'array', 'minItems' => 1, 'maxItems' => $max, 'items' => $item];
        $version = ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'];
        $course = ['course_id' => $id];
        $module = $course + ['module_id' => $id];
        $lesson = $module + ['lesson_id' => $id];
        $write = ['request_id' => ['type' => 'string', 'format' => 'uuid']];
        $rev = $write + ['expected_version' => $version];
        $order = $write + ['expected_order' => $arr($id, 200), 'order' => $arr($id, 200)];
        $question = $obj(['prompt' => $str(500), 'choices' => $obj(array_fill_keys(LessonQuiz::CHOICES, $str(300))),
            'correct' => ['type' => 'string', 'enum' => LessonQuiz::CHOICES], 'feedback' => $str(700)]);
        $fields = $obj(['title' => $str(180), 'content' => $str(50000), 'duration_minutes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 600],
            'quiz_questions' => $arr($question, LessonQuiz::MAX_QUESTIONS)]);
        $tools = [
            ['get_draft_module', 'Inspect owned unpublished module, summary, ordered lessons and revision version. Use version for edits or copies.', $module, true, false],
            ['get_draft_lesson', 'Inspect owned unpublished lesson, plain text, knowledge checks including answer keys, and revision version. Rich lessons are read-only except reordering.', $lesson, true, false],
            ['update_draft_module', 'Update owned draft module title and summary using its current version. No changes to child lessons.', $module + $rev + ['title' => $str(180), 'summary' => $str(700)], false, true],
            ['update_draft_lesson', 'Replace plain-text lesson fields and knowledge checks with current version. Preserve preview status; refuse rich HTML/media lessons. Provide all fields.', $lesson + $rev + ['lesson' => $fields], false, true],
            ['append_draft_lesson', 'Append one plain-text lesson and knowledge check to an existing owned draft module using current module version.', $module + $rev + ['lesson' => $fields], false, false],
            ['reorder_draft_modules', 'Reorder all modules in an owned draft using complete current and desired ID lists. No deletion.', $course + $order, false, true],
            ['reorder_draft_lessons', 'Reorder all lessons in one owned draft module using complete current and desired ID lists. No moving between modules.', $module + $order, false, true],
            ['duplicate_draft_module', 'Copy a plain-text-only draft module and its lessons with current module version. Append copy with preview disabled; no rich media.', $module + $rev, false, false],
            ['duplicate_draft_lesson', 'Copy one plain-text draft lesson in its current module using current lesson version. Append copy with preview disabled; no rich media.', $lesson + $rev, false, false],
        ];
        $schemes = [['type' => 'oauth2', 'scopes' => ['mcp:read', 'mcp:write', McpOAuthDraftScopePolicy::CURRICULUM_SCOPE, McpOAuthDraftScopePolicy::EDIT_SCOPE]]];
        return array_map(static fn (array $tool): array => [
            'name' => $tool[0], 'description' => $tool[1], 'inputSchema' => $obj($tool[2]),
            'securitySchemes' => $schemes, '_meta' => ['securitySchemes' => $schemes],
            'annotations' => ['readOnlyHint' => $tool[3], 'destructiveHint' => $tool[4], 'idempotentHint' => true, 'openWorldHint' => false],
        ], $tools);
    }

    public static function supports(string $name): bool
    {
        return in_array($name, array_column(self::catalog(), 'name'), true);
    }

    public static function execute(User $actor, string $name, array $args): array
    {
        abort_unless(self::supports($name), 403);
        $schema = collect(self::catalog())->firstWhere('name', $name)['inputSchema'];
        if (array_diff(array_keys($args), $schema['required']) || array_diff($schema['required'], array_keys($args))) {
            throw ValidationException::withMessages(['arguments' => 'Unknown or missing fields.']);
        }
        abort_if(strlen(json_encode($args, JSON_THROW_ON_ERROR)) > 750000, 413);
        $rules = ['course_id' => ['required', 'integer', 'min:1']];
        foreach (['module_id', 'lesson_id'] as $field) {
            if (isset($schema['properties'][$field])) {
                $rules[$field] = ['required', 'integer', 'min:1'];
            }
        }
        if (isset($schema['properties']['request_id'])) {
            $rules['request_id'] = ['required', 'uuid'];
        }
        if (isset($schema['properties']['expected_version'])) {
            $rules['expected_version'] = ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D'];
        }
        if ($name === 'update_draft_module') {
            $rules += ['title' => ['required', 'string', 'max:180'], 'summary' => ['required', 'string', 'max:700']];
        }
        if (isset($schema['properties']['order'])) {
            foreach (['order', 'expected_order'] as $field) {
                $rules[$field] = ['required', 'array', 'list', 'min:1', 'max:200'];
                $rules[$field.'.*'] = ['required', 'integer', 'min:1', 'distinct'];
            }
        }
        if (isset($schema['properties']['lesson'])) {
            $rules += [
                'lesson' => ['required', 'array:title,content,duration_minutes,quiz_questions'],
                'lesson.title' => ['required', 'string', 'max:180'],
                'lesson.content' => ['required', 'string', 'max:50000'],
                'lesson.duration_minutes' => ['required', 'integer', 'between:1,600'],
                'lesson.quiz_questions' => ['required', 'array', 'list', 'min:1', 'max:'.LessonQuiz::MAX_QUESTIONS],
                'lesson.quiz_questions.*' => ['required', 'array:prompt,choices,correct,feedback'],
                'lesson.quiz_questions.*.prompt' => ['required', 'string', 'max:500'],
                'lesson.quiz_questions.*.choices' => ['required', 'array:a,b,c,d'],
                'lesson.quiz_questions.*.choices.a' => ['required', 'string', 'max:300'],
                'lesson.quiz_questions.*.choices.b' => ['required', 'string', 'max:300'],
                'lesson.quiz_questions.*.choices.c' => ['required', 'string', 'max:300'],
                'lesson.quiz_questions.*.choices.d' => ['required', 'string', 'max:300'],
                'lesson.quiz_questions.*.correct' => ['required', 'in:a,b,c,d'],
                'lesson.quiz_questions.*.feedback' => ['required', 'string', 'max:700'],
            ];
        }
        $data = Validator::make($args, $rules)->validate();
        if (isset($data['lesson'])) {
            foreach ($data['lesson']['quiz_questions'] as $question) {
                $choices = array_map(static fn ($v) => mb_strtolower(trim($v)), $question['choices']);
                if (count(array_unique($choices)) !== 4 || in_array('', $choices, true)) {
                    throw ValidationException::withMessages(['choices' => 'Provide four distinct nonempty choices.']);
                }
            }
        }

        return DB::transaction(function () use ($actor, $name, $data): array {
            $current = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless(McpOAuthDraftScopePolicy::eligible($current), 403);
            $course = Course::query()->where('created_by', $current->id)->where('status', 'draft')
                ->whereNull('published_at')->whereNull('source_course_id')->whereNull('superseded_at')
                ->whereNull('deactivated_at')->lockForUpdate()->findOrFail($data['course_id']);
            $module = isset($data['module_id']) ? $course->modules()->lockForUpdate()->findOrFail($data['module_id']) : null;
            $lesson = isset($data['lesson_id']) ? $module->lessons()->lockForUpdate()->findOrFail($data['lesson_id']) : null;
            $common = ['resource' => 'courses', 'resource_id' => $course->id, 'publication_status' => 'draft'];

            if (str_starts_with($name, 'get_draft_')) {
                $receipt = (string) Str::uuid();
                self::audit($current, $course, $name, $receipt, 'READ');
                return $common + ['status' => 'read', 'receipt_id' => $receipt] + ($lesson
                    ? ['module_id' => $module->id, 'lesson' => self::lessonData($lesson)]
                    : ['module' => self::moduleData($module)]);
            }

            $receipt = strtolower($data['request_id']);
            $payload = $data;
            unset($payload['request_id']);
            $hash = self::digest([$name, $payload]);
            DB::table('mcp_oauth_draft_receipts')->insertOrIgnore([
                'id' => $receipt, 'user_id' => $current->id, 'request_hash' => $hash,
                'result' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $record = DB::table('mcp_oauth_draft_receipts')->where('id', $receipt)->lockForUpdate()->first();
            abort_unless($record && (int) $record->user_id === (int) $current->id
                && hash_equals($record->request_hash, $hash), 409);
            if ($record->result !== null) {
                return json_decode($record->result, true, 512, JSON_THROW_ON_ERROR) + ['replayed' => true];
            }

            if (isset($data['expected_version'])) {
                $version = $lesson ? self::lessonVersion($lesson) : self::moduleData($module)['version'];
                abort_unless(hash_equals($version, $data['expected_version']), 409, 'Refresh the curriculum version.');
            }

            $extra = match ($name) {
                'update_draft_module' => self::updateModule($module, $data),
                'update_draft_lesson' => self::updateLesson($course, $lesson, $current, $data['lesson']),
                'append_draft_lesson' => self::appendLesson($course, $module, $current, $data['lesson']),
                'reorder_draft_modules' => self::reorder($course->modules(), $data, $course, $current, false),
                'reorder_draft_lessons' => self::reorder($module->lessons(), $data, $course, $current, true) + ['module_id' => $module->id],
                'duplicate_draft_lesson' => self::duplicateLesson($course, $module, $lesson, $current),
                'duplicate_draft_module' => self::duplicateModule($course, $module, $current),
            };
            $result = $common + $extra + ['status' => 'updated', 'receipt_id' => $receipt];
            self::audit($current, $course, $name, $receipt, 'UPDATE');
            DB::table('mcp_oauth_draft_receipts')->where('id', $receipt)->update([
                'result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            return $result + ['replayed' => false];
        });
    }

    private static function canonical(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }
        return array_map(static fn ($v) => is_array($v) ? self::canonical($v) : $v, $value);
    }

    private static function digest(array $value): string
    {
        return hash('sha256', json_encode(self::canonical($value), JSON_THROW_ON_ERROR));
    }

    private static function rich(CourseLesson $lesson): bool
    {
        return filled($lesson->content_html) || $lesson->media()->exists();
    }

    private static function lessonVersion(CourseLesson $lesson): string
    {
        return self::digest([$lesson->id, $lesson->course_module_id, $lesson->title, $lesson->content,
            $lesson->content_html, $lesson->quiz_questions, $lesson->position, $lesson->duration_minutes,
            $lesson->is_preview, $lesson->media()->allRelatedIds()->sort()->values()->all(),
            $lesson->getRawOriginal('updated_at')]);
    }

    private static function lessonData(CourseLesson $lesson): array
    {
        return ['id' => $lesson->id, 'title' => $lesson->title, 'content' => $lesson->content,
            'duration_minutes' => (int) $lesson->duration_minutes, 'position' => (int) $lesson->position,
            'is_preview' => $lesson->is_preview, 'quiz_questions' => $lesson->quiz_questions ?? [],
            'rich_content' => self::rich($lesson), 'version' => self::lessonVersion($lesson)];
    }

    private static function moduleData(CourseModule $module): array
    {
        $lessons = [];
        foreach ($module->lessons()->lockForUpdate()->get() as $lesson) {
            $lessons[] = ['id' => $lesson->id, 'title' => $lesson->title, 'position' => (int) $lesson->position,
                'questions' => count($lesson->quiz_questions ?? []), 'rich_content' => self::rich($lesson),
                'version' => self::lessonVersion($lesson)];
        }
        $data = ['id' => $module->id, 'title' => $module->title, 'summary' => $module->summary,
            'position' => (int) $module->position, 'lessons' => $lessons];
        return $data + ['version' => self::digest([$data, $module->getRawOriginal('updated_at')])];
    }

    private static function updateModule(CourseModule $module, array $data): array
    {
        $module->update(['title' => $data['title'], 'summary' => $data['summary']]);
        return ['module_id' => $module->id, 'version' => self::moduleData($module->refresh())['version']];
    }

    private static function updateLesson(Course $course, CourseLesson $lesson, User $actor, array $data): array
    {
        abort_if(self::rich($lesson), 409, 'Rich lessons require the web builder.');
        $lesson->fill($data);
        if ($lesson->isDirty()) {
            $lesson->save();
            CurriculumLessonRevision::capture($course, $lesson, $actor, 'updated');
        }
        return ['lesson_id' => $lesson->id, 'module_id' => $lesson->course_module_id,
            'version' => self::lessonVersion($lesson->refresh())];
    }

    private static function appendPosition(HasMany $items, int $limit): int
    {
        abort_if($items->count() >= $limit, 409);
        $position = 1 + (int) $items->max('position');
        abort_if($position > 999, 409);
        return $position;
    }

    private static function appendLesson(Course $course, CourseModule $module, User $actor, array $data): array
    {
        $lesson = $module->lessons()->create($data + ['position' => self::appendPosition($module->lessons(), 200),
            'content_html' => null, 'is_preview' => false]);
        CurriculumLessonRevision::capture($course, $lesson, $actor, 'created');
        return ['module_id' => $module->id, 'lesson_id' => $lesson->id, 'version' => self::lessonVersion($lesson->refresh())];
    }

    private static function reorder(HasMany $items, array $data, Course $course, User $actor, bool $lessons): array
    {
        $current = $items->lockForUpdate()->get()->keyBy('id');
        $expected = array_map('intval', $data['expected_order']);
        $order = array_map('intval', $data['order']);
        abort_unless($current->keys()->map(static fn ($v) => (int) $v)->all() === $expected, 409);
        if (count($order) !== count($expected) || count(array_unique($order)) !== count($order)
            || array_diff($order, $expected) !== []) {
            throw ValidationException::withMessages(['order' => 'Provide a complete permutation of the current sibling IDs.']);
        }
        foreach ($order as $index => $id) {
            $item = $current->get($id);
            $item->position = $index + 1;
            if ($item->isDirty('position')) {
                $item->save();
                if ($lessons) {
                    CurriculumLessonRevision::capture($course, $item, $actor, 'reordered');
                }
            }
        }
        return ['order' => $order];
    }

    private static function copyLesson(Course $course, CourseModule $target, CourseLesson $source, User $actor, string $title, int $position): CourseLesson
    {
        abort_if(self::rich($source), 409, 'Rich lessons require the web builder.');
        $copy = $target->lessons()->create([
            'title' => $title, 'content' => $source->content, 'content_html' => null,
            'quiz_questions' => $source->quiz_questions, 'position' => $position,
            'duration_minutes' => $source->duration_minutes, 'is_preview' => false,
        ]);
        CurriculumLessonRevision::capture($course, $copy, $actor, 'copied');
        return $copy;
    }

    private static function duplicateLesson(Course $course, CourseModule $module, CourseLesson $lesson, User $actor): array
    {
        $copy = self::copyLesson($course, $module, $lesson, $actor, mb_substr($lesson->title, 0, 160).' (copy)',
            self::appendPosition($module->lessons(), 200));
        return ['module_id' => $module->id, 'source_lesson_id' => $lesson->id, 'lesson_id' => $copy->id,
            'version' => self::lessonVersion($copy->refresh())];
    }

    private static function duplicateModule(Course $course, CourseModule $module, User $actor): array
    {
        $lessons = $module->lessons()->lockForUpdate()->get();
        // Limit the size of one copy operation; no partial copies.
        abort_if($lessons->count() > 10, 409, 'Copy at most ten lessons in one module.');
        foreach ($lessons as $lesson) {
            abort_if(self::rich($lesson), 409, 'Rich lessons require the web builder.');
        }
        $copy = $course->modules()->create(['title' => mb_substr($module->title, 0, 160).' (copy)',
            'summary' => $module->summary, 'position' => self::appendPosition($course->modules(), 100)]);
        foreach ($lessons as $lesson) {
            self::copyLesson($course, $copy, $lesson, $actor, $lesson->title, (int) $lesson->position);
        }
        return ['source_module_id' => $module->id, 'module_id' => $copy->id, 'version' => self::moduleData($copy->refresh())['version']];
    }

    private static function audit(User $actor, Course $course, string $name, string $receipt, string $operation): void
    {
        McpActionAudit::create(['id' => $receipt, 'user_id' => $actor->id, 'token_id' => null,
            'tool' => $name, 'operation' => $operation, 'resource' => 'courses', 'resource_id' => (string) $course->id,
            'result' => 'success', 'http_status' => 200, 'summary' => 'Owned draft curriculum editor; payload not retained in audit.']);
    }
}
