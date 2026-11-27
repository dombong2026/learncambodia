<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CurriculumLessonRevision;
use App\Models\EditorMedia;
use App\Models\McpActionAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Append-only native curriculum rehearsal. No publication, deletion, replacement,
 * public file upload, or execution through the legacy bridge.
 */
final class McpOAuthNativeCourseBuilder
{
    public static function enabled(): bool
    {
        return McpOAuthDraftScopePolicy::curriculumEnabled();
    }

    public static function catalog(): array
    {
        if (!self::enabled()) {
            return [];
        }
        $id = ['type' => 'integer', 'minimum' => 1];
        $str = static fn (int $max): array => ['type' => 'string', 'minLength' => 1, 'maxLength' => $max];
        $obj = static fn (array $p): array => ['type' => 'object', 'properties' => $p, 'required' => array_keys($p), 'additionalProperties' => false];
        $arr = static fn (array $item, int $max): array => ['type' => 'array', 'minItems' => 1, 'maxItems' => $max, 'items' => $item];
        $request = ['course_id' => $id, 'request_id' => ['type' => 'string', 'format' => 'uuid']];
        $choices = $obj(array_fill_keys(LessonQuiz::CHOICES, $str(300)));
        $question = $obj(['prompt' => $str(500), 'choices' => $choices,
            'correct' => ['type' => 'string', 'enum' => LessonQuiz::CHOICES], 'feedback' => $str(700)]);
        $lesson = $obj(['title' => $str(180), 'content' => $str(50000),
            'duration_minutes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 600],
            'quiz_questions' => $arr($question, LessonQuiz::MAX_QUESTIONS)]);
        $tools = [
            ['get_draft_curriculum', 'Inspect own unpublished course: ordered module and lesson IDs, counts and cover status. No answer keys.', ['course_id' => $id], true],
            ['append_draft_module', 'Append one native module with up to ten native lessons and their embedded knowledge checks. Use current module count and a unique request ID. No replacement or publication.',
                $request + ['expected_module_count' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 99],
                    'module' => $obj(['title' => $str(180), 'summary' => $str(700), 'lessons' => $arr($lesson, 10)])], false],
            ['attach_draft_course_cover', 'Attach an existing image from your own Media Library to a draft with no cover. No upload or replacement.',
                $request + ['media_id' => $id, 'alt' => $str(200)], false],
        ];
        $schemes = [['type' => 'oauth2', 'scopes' => ['mcp:read', 'mcp:write', McpOAuthDraftScopePolicy::CURRICULUM_SCOPE]]];
        return array_map(static fn (array $tool): array => [
            'name' => $tool[0], 'description' => $tool[1], 'inputSchema' => $obj($tool[2]),
            'securitySchemes' => $schemes, '_meta' => ['securitySchemes' => $schemes],
            'annotations' => ['readOnlyHint' => $tool[3], 'destructiveHint' => false,
                'idempotentHint' => true, 'openWorldHint' => false],
        ], $tools);
    }

    public static function supports(string $name): bool
    {
        return in_array($name, array_column(self::catalog(), 'name'), true);
    }

    public static function execute(User $actor, string $name, array $args): array
    {
        abort_unless(self::enabled() && self::supports($name), 403);
        $schema = collect(self::catalog())->firstWhere('name', $name)['inputSchema'];
        if (array_diff(array_keys($args), $schema['required']) || array_diff($schema['required'], array_keys($args))) {
            throw ValidationException::withMessages(['arguments' => 'Unknown or missing fields.']);
        }
        abort_if(strlen(json_encode($args, JSON_THROW_ON_ERROR)) > 750000, 413);
        $rules = ['course_id' => ['required', 'integer', 'min:1']];
        if ($name !== 'get_draft_curriculum') {
            $rules['request_id'] = ['required', 'uuid'];
        }
        if ($name === 'append_draft_module') {
            $rules += [
                'expected_module_count' => ['required', 'integer', 'between:0,99'],
                'module' => ['required', 'array:title,summary,lessons'],
                'module.title' => ['required', 'string', 'max:180'],
                'module.summary' => ['required', 'string', 'max:700'],
                'module.lessons' => ['required', 'array', 'list', 'min:1', 'max:10'],
                'module.lessons.*' => ['required', 'array:title,content,duration_minutes,quiz_questions'],
                'module.lessons.*.title' => ['required', 'string', 'max:180'],
                'module.lessons.*.content' => ['required', 'string', 'max:50000'],
                'module.lessons.*.duration_minutes' => ['required', 'integer', 'between:1,600'],
                'module.lessons.*.quiz_questions' => ['required', 'array', 'list', 'min:1', 'max:'.LessonQuiz::MAX_QUESTIONS],
                'module.lessons.*.quiz_questions.*' => ['required', 'array:prompt,choices,correct,feedback'],
                'module.lessons.*.quiz_questions.*.prompt' => ['required', 'string', 'max:500'],
                'module.lessons.*.quiz_questions.*.choices' => ['required', 'array:a,b,c,d'],
                'module.lessons.*.quiz_questions.*.choices.a' => ['required', 'string', 'max:300'],
                'module.lessons.*.quiz_questions.*.choices.b' => ['required', 'string', 'max:300'],
                'module.lessons.*.quiz_questions.*.choices.c' => ['required', 'string', 'max:300'],
                'module.lessons.*.quiz_questions.*.choices.d' => ['required', 'string', 'max:300'],
                'module.lessons.*.quiz_questions.*.correct' => ['required', 'in:a,b,c,d'],
                'module.lessons.*.quiz_questions.*.feedback' => ['required', 'string', 'max:700'],
            ];
        } elseif ($name === 'attach_draft_course_cover') {
            $rules += ['media_id' => ['required', 'integer', 'min:1'], 'alt' => ['required', 'string', 'max:200']];
        }
        $data = Validator::make($args, $rules)->validate();
        return DB::transaction(function () use ($actor, $name, $data): array {
            $current = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            abort_unless(McpOAuthDraftScopePolicy::eligible($current), 403);
            $course = Course::query()->where('created_by', $current->id)->where('status', 'draft')
                ->whereNull('published_at')->whereNull('source_course_id')->whereNull('superseded_at')
                ->whereNull('deactivated_at')->lockForUpdate()->findOrFail($data['course_id']);
            if ($name === 'get_draft_curriculum') {
                $result = self::summary($course);
                $receipt = (string) Str::uuid();
                self::audit($current, $course, $name, $receipt, 'READ');
                return $result + ['status' => 'read', 'receipt_id' => $receipt];
            }
            $receipt = strtolower($data['request_id']);
            $payload = $data;
            unset($payload['request_id']);
            $hash = hash('sha256', json_encode([$name, self::canonical($payload)], JSON_THROW_ON_ERROR));
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
            if ($name === 'append_draft_module') {
                $moduleCount = $course->modules()->count();
                abort_unless($moduleCount === (int) $data['expected_module_count'] && $moduleCount < 100, 409);
                $position = 1 + (int) $course->modules()->max('position');
                abort_if($position > 999, 409);
                $module = $course->modules()->create([
                    'title' => $data['module']['title'], 'summary' => $data['module']['summary'], 'position' => $position,
                ]);
                foreach ($data['module']['lessons'] as $i => $lesson) {
                    foreach ($lesson['quiz_questions'] as $q) {
                        $normalized = array_map(static fn ($v) => mb_strtolower(trim($v)), $q['choices']);
                        if (count(array_unique($normalized)) !== 4 || in_array('', $normalized, true)) {
                            throw ValidationException::withMessages(['choices' => 'Provide four distinct nonempty choices.']);
                        }
                    }
                    $created = $module->lessons()->create([
                        'title' => $lesson['title'], 'content' => $lesson['content'],
                        'content_html' => null, 'quiz_questions' => $lesson['quiz_questions'],
                        'position' => $i + 1, 'duration_minutes' => $lesson['duration_minutes'], 'is_preview' => false,
                    ]);
                    CurriculumLessonRevision::capture($course, $created, $current, 'created');
                }
                $extra = ['module_id' => $module->id];
            } else {
                // Never replace media; caller must use the existing admin workflow for replacement.
                abort_unless($course->featured_kind === null && $course->featured_media_id === null
                    && $course->featured_value === null, 409);
                $media = EditorMedia::query()->where('context', 'library')->where('uploaded_by', $current->id)
                    ->whereIn('mime', ['image/png', 'image/jpeg', 'image/webp'])->lockForUpdate()->findOrFail($data['media_id']);
                abort_unless(ManagedFiles::disk()->fileExists($media->path), 422);
                $course->update(['featured_kind' => 'image', 'featured_media_id' => $media->id,
                    'featured_value' => null, 'featured_alt' => $data['alt']]);
                $extra = ['media_id' => $media->id];
            }
            $result = self::summary($course->refresh()) + $extra + ['status' => 'updated', 'receipt_id' => $receipt];
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

    private static function summary(Course $course): array
    {
        $modules = [];
        $lessons = $checks = $questions = 0;
        foreach ($course->modules()->with('lessons:id,course_module_id,title,position,quiz_questions')->get() as $module) {
            $items = [];
            foreach ($module->lessons as $lesson) {
                $count = count($lesson->quiz_questions ?? []);
                $lessons++;
                $checks += $count > 0 ? 1 : 0;
                $questions += $count;
                $items[] = ['id' => $lesson->id, 'title' => $lesson->title, 'position' => $lesson->position, 'questions' => $count];
            }
            $modules[] = ['id' => $module->id, 'title' => $module->title, 'position' => $module->position, 'lessons' => $items];
        }
        return ['resource' => 'courses', 'resource_id' => $course->id, 'publication_status' => 'draft',
            'module_count' => count($modules), 'lesson_count' => $lessons, 'knowledge_check_count' => $checks,
            'question_count' => $questions, 'featured_media_id' => $course->featured_media_id, 'modules' => $modules];
    }

    private static function audit(User $actor, Course $course, string $name, string $receipt, string $operation): void
    {
        McpActionAudit::create([
            'id' => $receipt, 'user_id' => $actor->id, 'token_id' => null, 'tool' => $name,
            'operation' => $operation, 'resource' => 'courses', 'resource_id' => (string) $course->id,
            'result' => 'success', 'http_status' => 200, 'summary' => 'Native draft curriculum; payload not retained in audit.',
        ]);
    }
}
