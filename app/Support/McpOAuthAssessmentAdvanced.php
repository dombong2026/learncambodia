<?php

namespace App\Support;

use App\Models\EditorMedia;
use App\Models\McpActionAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Append-only native advanced questions, with owned private exam audio. */
final class McpOAuthAssessmentAdvanced
{
    private const FORMATS = ['listening', 'writing', 'speaking', 'likert'];
    private const AUDIO = ['audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/webm'];
    public const LIST = 'list_draft_assessment_audio';
    public const APPEND = 'append_draft_advanced_assessment_questions';

    public static function catalog(): array
    {
        if (!McpOAuthDraftScopePolicy::assessmentAdvancedEnabled()) {
            return [];
        }
        $obj = static fn (array $p): array => ['type' => 'object', 'properties' => $p, 'required' => array_keys($p), 'additionalProperties' => false];
        $int = static fn (int $min, int $max): array => ['type' => 'integer', 'minimum' => $min, 'maximum' => $max];
        $str = static fn (int $max, int $min = 0): array => ['type' => 'string', 'minLength' => $min, 'maxLength' => $max];
        $schemes = [['type' => 'oauth2', 'scopes' => ['mcp:read', 'mcp:write', McpOAuthDraftScopePolicy::ASSESSMENT_SCOPE,
            McpOAuthDraftScopePolicy::ASSESSMENT_ADVANCED_SCOPE]]];
        $question = $obj([
            'format' => ['type' => 'string', 'enum' => self::FORMATS],
            'prompt' => $str(600, 1), 'a' => $str(300), 'b' => $str(300), 'c' => $str(300), 'd' => $str(300),
            'correct' => $str(300), 'points' => $int(1, 100), 'feedback' => $str(800),
            'media_id' => ['type' => 'integer', 'minimum' => 0],
        ]);
        $tools = [
            [self::LIST, 'List your own private exam-context audio metadata (IDs, names, MIME, bytes). No paths, URLs, audio contents or other users’ media.', ['limit' => $int(1, 100)], true],
            [self::APPEND, 'Append native listening, writing, speaking or unscored Likert questions to an owned MCP-created standalone draft. Listening needs an existing owned exam audio ID and distinct options with a correct letter. For writing, speaking or Likert use empty options and correct, media_id 0; Likert uses points 1 as a native placeholder but is unscored. No replacement or publication.', [
                'kind' => ['type' => 'string', 'enum' => ['quiz', 'exam']],
                'assessment_id' => ['type' => 'integer', 'minimum' => 1],
                'request_id' => ['type' => 'string', 'format' => 'uuid'],
                'expected_version' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
                'questions' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 200, 'items' => $question],
            ], false],
        ];
        return array_map(static fn (array $t): array => [
            'name' => $t[0], 'description' => $t[1], 'inputSchema' => $obj($t[2]),
            'securitySchemes' => $schemes, '_meta' => ['securitySchemes' => $schemes],
            'annotations' => ['readOnlyHint' => $t[3], 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
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
        $rules = $name === self::LIST ? ['limit' => ['required', 'integer', 'between:1,100']] : [
            'kind' => ['required', 'in:quiz,exam'], 'assessment_id' => ['required', 'integer', 'min:1'],
            'request_id' => ['required', 'uuid'], 'expected_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D'],
            'questions' => ['required', 'array', 'list', 'min:1', 'max:200'],
            'questions.*' => ['required', 'array:format,prompt,a,b,c,d,correct,points,feedback,media_id'],
            'questions.*.format' => ['required', 'in:'.implode(',', self::FORMATS)],
            'questions.*.prompt' => ['required', 'string', 'max:600'],
            'questions.*.a' => ['present', 'nullable', 'string', 'max:300'],
            'questions.*.b' => ['present', 'nullable', 'string', 'max:300'],
            'questions.*.c' => ['present', 'nullable', 'string', 'max:300'],
            'questions.*.d' => ['present', 'nullable', 'string', 'max:300'],
            'questions.*.correct' => ['present', 'nullable', 'string', 'max:300'],
            'questions.*.points' => ['required', 'integer', 'between:1,100'],
            'questions.*.feedback' => ['present', 'nullable', 'string', 'max:800'],
            'questions.*.media_id' => ['required', 'integer', 'min:0'],
        ];
        $data = Validator::make($args, $rules)->validate();
        if ($name === self::APPEND) {
            foreach ($data['questions'] as $i => &$q) {
                $values = array_values(array_filter(array_map(static fn ($v) => mb_strtolower(trim((string) $v)),
                    [$q['a'], $q['b'], $q['c'], $q['d']]), static fn ($v) => $v !== ''));
                if ($q['format'] === 'listening') {
                    if ((int) $q['media_id'] < 1 || count($values) !== count(array_unique($values))) {
                        throw ValidationException::withMessages(["questions.$i" => 'Listening requires owned exam audio and distinct options.']);
                    }
                    $q['media_id'] = (int) $q['media_id'];
                } elseif ($values !== [] || filled($q['correct']) || (int) $q['media_id'] !== 0
                    || ($q['format'] === 'likert' && (int) $q['points'] !== 1)) {
                    throw ValidationException::withMessages(["questions.$i" => 'Manual and Likert questions have no choices, answer key or audio attachment; Likert points must be 1.']);
                } else {
                    unset($q['media_id']);
                }
                $q['points'] = (int) $q['points'];
            }
            unset($q);
            $data['questions'] = AssessmentQuestions::validate($data['questions']);
        }

        return DB::transaction(function () use ($actor, $name, $data): array {
            $current = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless(McpOAuthDraftScopePolicy::eligible($current), 403);
            if ($name === self::LIST) {
                $items = EditorMedia::where('context', 'exam')->where('uploaded_by', $current->id)
                    ->whereIn('mime', self::AUDIO)->latest('id')->limit($data['limit'])
                    ->get(['id', 'original_name', 'mime', 'size'])->toArray();
                $receipt = (string) Str::uuid();
                self::audit($current, $name, $receipt, null, 'READ');
                return ['status' => 'read', 'resource' => 'assessment_audio', 'items' => $items, 'receipt_id' => $receipt];
            }
            $exam = McpOAuthStandaloneAssessment::owned($current, $data['kind'], (int) $data['assessment_id']);
            $receipt = strtolower($data['request_id']);
            $payload = $data;
            unset($payload['request_id']);
            $canonical = static function (array $v) use (&$canonical): array {
                if (!array_is_list($v)) {
                    ksort($v);
                }
                return array_map(static fn ($item) => is_array($item) ? $canonical($item) : $item, $v);
            };
            $hash = hash('sha256', json_encode($canonical([$name, $payload]), JSON_THROW_ON_ERROR));
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
            abort_unless(hash_equals(McpOAuthStandaloneAssessment::version($exam), $data['expected_version']), 409);
            $questions = [...($exam->questions ?? []), ...$data['questions']];
            abort_if(count($questions) > ($exam->kind === 'quiz' ? 50 : 200), 422);
            abort_if(count(array_filter($questions, static fn ($q) => ($q['format'] ?? '') === 'speaking')) > 10, 422);
            abort_if($exam->guest_enabled && !GuestAssessment::compatible($exam->course_id, $exam->pricing, $questions), 422,
                'Disable guest participation before adding speaking recordings.');
            // Only new audio is authorized here. Existing instructions, question data,
            // and native media relations are never replaced or detached.
            $ids = array_values(array_unique(array_column(array_filter($data['questions'],
                static fn ($q) => $q['format'] === 'listening'), 'media_id')));
            if ($ids !== []) {
                $media = EditorMedia::whereIn('id', $ids)->where('context', 'exam')
                    ->where('uploaded_by', $current->id)->whereIn('mime', self::AUDIO)
                    ->orderBy('id')->lockForUpdate()->get();
                abort_unless($media->count() === count($ids), 404);
                foreach ($media as $item) {
                    abort_unless(ManagedFiles::disk()->fileExists($item->path), 422);
                }
                $exam->media()->syncWithoutDetaching($ids);
            }
            $exam->update(['questions' => $questions]);
            $result = McpOAuthStandaloneAssessment::summary($exam->refresh()) + [
                'status' => 'updated', 'receipt_id' => $receipt,
                'manual_question_count' => count(array_filter($questions, AssessmentQuestions::manual(...))),
                'unscored_question_count' => count(array_filter($questions, static fn ($q) => !AssessmentQuestions::scored($q))),
            ];
            self::audit($current, $name, $receipt, $exam->id, 'UPDATE');
            DB::table('mcp_oauth_draft_receipts')->where('id', $receipt)->update([
                'result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            return $result + ['replayed' => false];
        });
    }

    private static function audit(User $actor, string $name, string $receipt, ?int $id, string $operation): void
    {
        McpActionAudit::create(['id' => $receipt, 'user_id' => $actor->id, 'token_id' => null, 'tool' => $name,
            'operation' => $operation, 'resource' => 'assessments', 'resource_id' => $id === null ? null : (string) $id,
            'result' => 'success', 'http_status' => 200, 'summary' => 'Advanced assessment draft operation; payload not retained in audit.']);
    }
}
