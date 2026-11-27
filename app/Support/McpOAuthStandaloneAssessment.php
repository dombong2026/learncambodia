<?php

namespace App\Support;

use App\Models\AssessmentAccess;
use App\Models\CourseExam;
use App\Models\EditorMedia;
use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Models\McpActionAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Native standalone assessment drafts; only records created and owned through this adapter. */
final class McpOAuthStandaloneAssessment
{
    private const FORMATS = ['choice', 'reading', 'boolean', 'order', 'fill', 'short'];
    private const SETTINGS = ['exam_type_id', 'title', 'instructions', 'time_limit', 'passing_percent',
        'attempt_limit', 'shuffle', 'gamified', 'live_feedback', 'content_language', 'public_requirement'];

    public static function catalog(): array
    {
        if (!McpOAuthDraftScopePolicy::assessmentEnabled()) {
            return [];
        }
        $id = ['type' => 'integer', 'minimum' => 1];
        $str = static fn (int $max, int $min = 1): array => ['type' => 'string', 'minLength' => $min, 'maxLength' => $max];
        $obj = static fn (array $p): array => ['type' => 'object', 'properties' => $p, 'required' => array_keys($p), 'additionalProperties' => false];
        $int = static fn (int $min, int $max): array => ['type' => 'integer', 'minimum' => $min, 'maximum' => $max];
        $bool = ['type' => 'boolean'];
        $kind = ['type' => 'string', 'enum' => ['quiz', 'exam']];
        $write = ['request_id' => ['type' => 'string', 'format' => 'uuid']];
        $record = ['assessment_id' => $id, 'kind' => $kind];
        $version = $write + ['expected_version' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$']];
        $question = $obj(['format' => ['type' => 'string', 'enum' => self::FORMATS],
            'prompt' => $str(600), 'a' => $str(300, 0), 'b' => $str(300, 0),
            'c' => $str(300, 0), 'd' => $str(300, 0), 'correct' => $str(300),
            'points' => $int(1, 100), 'passage' => $str(12000, 0), 'feedback' => $str(800)]);
        $questions = ['type' => 'array', 'minItems' => 1, 'maxItems' => 200, 'items' => $question];
        $settings = $obj(['exam_type_id' => $id, 'title' => $str(160), 'instructions' => $str(15000, 0),
            'time_limit' => $int(1, 240), 'passing_percent' => $int(1, 100), 'attempt_limit' => $int(1, 10),
            'shuffle' => $bool, 'gamified' => $bool, 'live_feedback' => $bool,
            'content_language' => $str(100, 0), 'public_requirement' => $str(2000, 0)]);
        $tools = [
            ['list_draft_assessment_types', 'List native quiz or exam type IDs and names. No student information.', ['kind' => $kind], true, false],
            ['get_draft_assessment', 'Inspect an owned MCP-created standalone draft, settings, questions including answers, cover and current version.', $record, true, false],
            ['create_draft_assessment', 'Create one free unpublished native standalone quiz or exam, with 1–50 quiz or 1–200 exam questions. No publication or access grants.', ['kind' => $kind] + $write + ['settings' => $settings, 'questions' => $questions], false, false],
            ['update_draft_assessment_settings', 'Update all plain-text settings of an owned draft with its current version. Preserves questions and cover.', $record + $version + ['settings' => $settings], false, true],
            ['append_draft_assessment_questions', 'Append questions within native quiz/exam limits, using the current version. Existing questions are unchanged.', $record + $version + ['questions' => $questions], false, false],
            ['replace_draft_assessment_questions', 'Replace the complete plain-text question list of an owned unpublished draft with its current version. No rich or manual-grading replacements.', $record + $version + ['questions' => $questions], false, true],
            ['attach_draft_assessment_cover', 'Attach an existing owned library image to an empty draft cover slot. No upload or replacement.', $record + $version + ['media_id' => $id, 'alt' => $str(200)], false, false],
        ];
        $schemes = [['type' => 'oauth2', 'scopes' => ['mcp:read', 'mcp:write', McpOAuthDraftScopePolicy::ASSESSMENT_SCOPE]]];
        return array_map(static fn (array $t): array => [
            'name' => $t[0], 'description' => $t[1], 'inputSchema' => $obj($t[2]),
            'securitySchemes' => $schemes, '_meta' => ['securitySchemes' => $schemes],
            'annotations' => ['readOnlyHint' => $t[3], 'destructiveHint' => $t[4], 'idempotentHint' => true, 'openWorldHint' => false],
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
        $rules = ['kind' => ['required', 'in:quiz,exam']];
        foreach (['assessment_id', 'media_id'] as $key) {
            if (isset($schema['properties'][$key])) {
                $rules[$key] = ['required', 'integer', 'min:1'];
            }
        }
        if (isset($schema['properties']['request_id'])) {
            $rules['request_id'] = ['required', 'uuid'];
        }
        if (isset($schema['properties']['expected_version'])) {
            $rules['expected_version'] = ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D'];
        }
        if (isset($schema['properties']['alt'])) {
            $rules['alt'] = ['required', 'string', 'max:200'];
        }
        if (isset($schema['properties']['settings'])) {
            $rules += [
                'settings' => ['required', 'array:'.implode(',', self::SETTINGS)],
                'settings.exam_type_id' => ['required', 'integer', 'min:1'],
                'settings.title' => ['required', 'string', 'max:160'],
                'settings.instructions' => ['present', 'nullable', 'string', 'max:15000'],
                'settings.time_limit' => ['required', 'integer', $args['kind'] === 'quiz' ? 'between:1,30' : 'between:1,240'],
                'settings.passing_percent' => ['required', 'integer', 'between:1,100'],
                'settings.attempt_limit' => ['required', 'integer', 'between:1,10'],
                'settings.shuffle' => ['required', 'boolean'],
                'settings.gamified' => ['required', 'boolean'],
                'settings.live_feedback' => ['required', 'boolean'],
                'settings.content_language' => ['present', 'nullable', 'string', 'max:100'],
                'settings.public_requirement' => ['present', 'nullable', 'string', 'max:2000'],
            ];
        }
        if (isset($schema['properties']['questions'])) {
            $rules += [
                'questions' => ['required', 'array', 'list', 'min:1', $args['kind'] === 'quiz' ? 'max:50' : 'max:200'],
                'questions.*' => ['required', 'array:format,prompt,a,b,c,d,correct,points,passage,feedback'],
                'questions.*.format' => ['required', 'in:'.implode(',', self::FORMATS)],
                'questions.*.prompt' => ['required', 'string', 'max:600'],
                'questions.*.a' => ['present', 'nullable', 'string', 'max:300'],
                'questions.*.b' => ['present', 'nullable', 'string', 'max:300'],
                'questions.*.c' => ['present', 'nullable', 'string', 'max:300'],
                'questions.*.d' => ['present', 'nullable', 'string', 'max:300'],
                'questions.*.correct' => ['required', 'string', 'max:300'],
                'questions.*.points' => ['required', 'integer', 'between:1,100'],
                'questions.*.passage' => ['present', 'nullable', 'string', 'max:12000'],
                'questions.*.feedback' => ['required', 'string', 'max:800'],
            ];
        }
        $data = Validator::make($args, $rules)->validate();
        if (isset($data['questions'])) {
            foreach ($data['questions'] as $index => $q) {
                $choices = array_map(static fn ($v) => mb_strtolower(trim((string) $v)), [$q['a'], $q['b'], $q['c'], $q['d']]);
                $choices = array_values(array_filter($choices, static fn ($v) => $v !== ''));
                if (count(array_unique($choices)) !== count($choices)) {
                    throw ValidationException::withMessages(["questions.$index" => 'Answer choices must be distinct.']);
                }
            }
            $data['questions'] = AssessmentQuestions::validate($data['questions']);
        }

        return DB::transaction(function () use ($actor, $name, $data): array {
            $current = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless(McpOAuthDraftScopePolicy::eligible($current), 403);
            $kind = $data['kind'];
            if ($name === 'list_draft_assessment_types') {
                $receipt = (string) Str::uuid();
                $items = ExamType::where('kind', $kind)->orderBy('name')->orderBy('id')->limit(200)->get(['id', 'name'])->toArray();
                self::audit($current, $name, $receipt, null, 'READ');
                return ['status' => 'read', 'resource' => 'assessment_types', 'kind' => $kind, 'items' => $items, 'receipt_id' => $receipt];
            }
            $exam = isset($data['assessment_id']) ? self::owned($current, $kind, (int) $data['assessment_id']) : null;
            if ($name === 'get_draft_assessment') {
                $receipt = (string) Str::uuid();
                self::audit($current, $name, $receipt, $exam->id, 'READ');
                return self::summary($exam) + ['status' => 'read', 'receipt_id' => $receipt,
                    'settings' => self::settings($exam), 'questions' => $exam->questions,
                    'rich_content' => self::rich($exam)];
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
                $result = json_decode($record->result, true, 512, JSON_THROW_ON_ERROR);
                // A replay must not reveal an old result after publication or ownership loss.
                self::owned($current, $kind, (int) $result['resource_id']);
                return $result + ['replayed' => true];
            }
            if ($exam) {
                abort_unless(hash_equals(self::version($exam), $data['expected_version']), 409);
            }
            if (isset($data['settings'])) {
                ExamType::where('kind', $kind)->lockForUpdate()->findOrFail($data['settings']['exam_type_id']);
            }
            if ($name === 'create_draft_assessment') {
                $exam = CourseExam::create(self::nativeSettings($data['settings']) + [
                    'kind' => $kind, 'course_id' => null, 'pricing' => null, 'published' => false,
                    'questions' => $data['questions'],
                ]);
                DB::table('mcp_assessment_owners')->insert(['course_exam_id' => $exam->id, 'user_id' => $current->id,
                    'created_at' => now(), 'updated_at' => now()]);
            } elseif ($name === 'update_draft_assessment_settings') {
                abort_if(self::rich($exam), 409, 'Use the web builder for rich assessment content.');
                $exam->update(self::nativeSettings($data['settings']));
            } elseif (in_array($name, ['append_draft_assessment_questions', 'replace_draft_assessment_questions'], true)) {
                abort_if(self::rich($exam), 409, 'Use the web builder for rich assessment content.');
                $questions = $name === 'append_draft_assessment_questions'
                    ? [...$exam->questions, ...$data['questions']] : $data['questions'];
                abort_if(count($questions) > ($kind === 'quiz' ? 50 : 200), 422);
                $exam->update(['questions' => $questions]);
            } else {
                abort_unless($exam->featured_kind === null && $exam->featured_media_id === null && $exam->featured_value === null, 409);
                $media = EditorMedia::where('context', 'library')->where('uploaded_by', $current->id)
                    ->whereIn('mime', ['image/png', 'image/jpeg', 'image/webp'])->lockForUpdate()->findOrFail($data['media_id']);
                abort_unless(ManagedFiles::disk()->fileExists($media->path), 422);
                $exam->update(['featured_kind' => 'image', 'featured_media_id' => $media->id,
                    'featured_value' => null, 'featured_alt' => $data['alt']]);
            }
            $result = self::summary($exam->refresh()) + [
                'status' => $name === 'create_draft_assessment' ? 'created' : 'updated', 'receipt_id' => $receipt];
            self::audit($current, $name, $receipt, $exam->id, $name === 'create_draft_assessment' ? 'CREATE' : 'UPDATE');
            DB::table('mcp_oauth_draft_receipts')->where('id', $receipt)->update([
                'result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            return $result + ['replayed' => false];
        });
    }

    public static function owned(User $actor, string $kind, int $id): CourseExam
    {
        $exam = CourseExam::whereKey($id)->where('kind', $kind)->whereNull('course_id')
            ->where('published', false)->whereNull('deactivated_at')
            ->whereIn('id', DB::table('mcp_assessment_owners')->where('user_id', $actor->id)->select('course_exam_id'))
            ->lockForUpdate()->firstOrFail();
        abort_if(ExamAttempt::where('course_exam_id', $id)->exists()
            || AssessmentAccess::where('course_exam_id', $id)->exists(), 409);
        abort_unless($exam->pricing === null, 409);
        return $exam;
    }

    private static function nativeSettings(array $settings): array
    {
        $settings['instructions_html'] = EditorHtml::clean(nl2br(e($settings['instructions'] ?? '')), lesson: true);
        unset($settings['instructions']);
        return $settings;
    }

    private static function settings(CourseExam $exam): array
    {
        return array_intersect_key($exam->attributesToArray(), array_flip(self::SETTINGS))
            + ['instructions' => html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>(?:\r\n|\r|\n)?/i', "\n", $exam->instructions_html ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')];
    }

    private static function rich(CourseExam $exam): bool
    {
        // Refuse replacement of web-authored rich content and unsupported question types.
        $html = $exam->instructions_html ?? '';
        $plain = self::settings($exam)['instructions'];
        $roundtrip = self::nativeSettings(['instructions' => $plain])['instructions_html'] ?? '';
        return $html !== $roundtrip || $exam->media()->exists()
            || collect($exam->questions ?? [])->contains(static fn ($q) => !in_array($q['format'] ?? 'choice', self::FORMATS, true)
                || !empty($q['media_id']));
    }

    private static function digest(array $data): string
    {
        $canonical = static function (array $v) use (&$canonical): array {
            if (!array_is_list($v)) {
                ksort($v);
            }
            return array_map(static fn ($item) => is_array($item) ? $canonical($item) : $item, $v);
        };
        return hash('sha256', json_encode($canonical($data), JSON_THROW_ON_ERROR));
    }

    public static function version(CourseExam $exam): string
    {
        return self::digest([$exam->getAttributes(), $exam->media()->allRelatedIds()->sort()->values()->all()]);
    }

    public static function summary(CourseExam $exam): array
    {
        return ['resource' => 'assessments', 'resource_id' => $exam->id, 'kind' => $exam->kind,
            'publication_status' => 'draft', 'title' => $exam->title, 'version' => self::version($exam),
            'question_count' => count($exam->questions ?? []), 'total_points' => array_sum(array_column($exam->questions ?? [], 'points')),
            'featured_media_id' => $exam->featured_media_id];
    }

    private static function audit(User $actor, string $name, string $receipt, ?int $id, string $operation): void
    {
        McpActionAudit::create(['id' => $receipt, 'user_id' => $actor->id, 'token_id' => null,
            'tool' => $name, 'operation' => $operation, 'resource' => 'assessments', 'resource_id' => $id === null ? null : (string) $id,
            'result' => 'success', 'http_status' => 200, 'summary' => 'Owned standalone assessment draft; payload not retained in audit.']);
    }
}
