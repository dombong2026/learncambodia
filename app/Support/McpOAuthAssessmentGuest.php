<?php

namespace App\Support;

use App\Models\McpActionAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class McpOAuthAssessmentGuest
{
    public const NAME = 'update_draft_assessment_guest_access';

    public static function catalog(): array
    {
        if (!McpOAuthDraftScopePolicy::assessmentGuestEnabled()) {
            return [];
        }
        $fields = [
            'kind' => ['type' => 'string', 'enum' => ['quiz', 'exam']],
            'assessment_id' => ['type' => 'integer', 'minimum' => 1],
            'request_id' => ['type' => 'string', 'format' => 'uuid'],
            'expected_version' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
            'guest_enabled' => ['type' => 'boolean'],
        ];
        $schemes = [['type' => 'oauth2', 'scopes' => ['mcp:read', 'mcp:write',
            McpOAuthDraftScopePolicy::ASSESSMENT_SCOPE, McpOAuthDraftScopePolicy::ASSESSMENT_GUEST_SCOPE]]];
        return [[
            'name' => self::NAME,
            'description' => 'Enable or disable future guest participation for your own free unpublished standalone quiz or exam, with current version and UUID. No speaking recordings in guest-enabled assessments. No publication, guest attempts or learner data.',
            'inputSchema' => ['type' => 'object', 'properties' => $fields, 'required' => array_keys($fields), 'additionalProperties' => false],
            'securitySchemes' => $schemes, '_meta' => ['securitySchemes' => $schemes],
            'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
        ]];
    }

    public static function supports(string $name): bool
    {
        return $name === self::NAME && McpOAuthDraftScopePolicy::assessmentGuestEnabled();
    }

    public static function execute(User $actor, array $args): array
    {
        abort_unless(McpOAuthDraftScopePolicy::assessmentGuestEnabled(), 403);
        $keys = ['kind', 'assessment_id', 'request_id', 'expected_version', 'guest_enabled'];
        if (array_diff(array_keys($args), $keys) || array_diff($keys, array_keys($args))
            || !is_bool($args['guest_enabled'] ?? null)) {
            throw ValidationException::withMessages(['arguments' => 'Unknown or invalid fields.']);
        }
        $data = Validator::make($args, [
            'kind' => ['required', 'in:quiz,exam'], 'assessment_id' => ['required', 'integer', 'min:1'],
            'request_id' => ['required', 'uuid'], 'expected_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D'],
            'guest_enabled' => ['required', 'boolean'],
        ])->validate();
        return DB::transaction(function () use ($actor, $data): array {
            $current = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless(McpOAuthDraftScopePolicy::eligible($current), 403);
            $exam = McpOAuthStandaloneAssessment::owned($current, $data['kind'], (int) $data['assessment_id']);
            $receipt = strtolower($data['request_id']);
            $hash = hash('sha256', json_encode([self::NAME, $exam->id, $exam->kind,
                $data['expected_version'], $data['guest_enabled']], JSON_THROW_ON_ERROR));
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
            if ($data['guest_enabled'] && ($exam->viewer_mode !== 'default'
                || !GuestAssessment::compatible($exam->course_id, $exam->pricing, $exam->questions ?? []))) {
                throw ValidationException::withMessages(['guest_enabled' => 'Guest access requires a free standalone assessment without speaking recordings.']);
            }
            $exam->update(['guest_enabled' => $data['guest_enabled']]);
            $result = McpOAuthStandaloneAssessment::summary($exam->refresh()) + ['status' => 'updated', 'receipt_id' => $receipt];
            McpActionAudit::create([
                'id' => $receipt, 'user_id' => $current->id, 'token_id' => null, 'tool' => self::NAME,
                'operation' => 'UPDATE', 'resource' => 'assessments', 'resource_id' => (string) $exam->id,
                'result' => 'success', 'http_status' => 200, 'summary' => 'Owned unpublished assessment guest participation setting updated.',
            ]);
            DB::table('mcp_oauth_draft_receipts')->where('id', $receipt)->update([
                'result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            return $result + ['replayed' => false];
        });
    }
}
