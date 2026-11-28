<?php

namespace App\Support;

use App\Models\EditorMedia;
use App\Models\McpActionAudit;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Upload a sanitized image to an owned MCP-created standalone draft. */
final class McpOAuthAssessmentCoverUpload
{
    public const NAME = 'upload_draft_assessment_cover';

    private ?FilesystemAdapter $disk = null;
    private ?string $path = null;

    public static function catalog(): array
    {
        if (!McpOAuthDraftScopePolicy::assessmentMediaEnabled()) {
            return [];
        }
        $schemes = [['type' => 'oauth2', 'scopes' => ['mcp:read', 'mcp:write',
            McpOAuthDraftScopePolicy::CURRICULUM_SCOPE, McpOAuthDraftScopePolicy::MEDIA_SCOPE,
            McpOAuthDraftScopePolicy::ASSESSMENT_SCOPE, McpOAuthDraftScopePolicy::ASSESSMENT_MEDIA_SCOPE]]];
        $properties = [
            'assessment_id' => ['type' => 'integer', 'minimum' => 1],
            'kind' => ['type' => 'string', 'enum' => ['quiz', 'exam']],
            'expected_version' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
            'request_id' => ['type' => 'string', 'format' => 'uuid'],
            'image_base64' => ['type' => 'string', 'minLength' => 4, 'maxLength' => McpOAuthDraftCoverUpload::MAX_BASE64_CHARS],
            'alt' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
        ];
        return [[
            'name' => self::NAME,
            'description' => 'Upload and attach one 1920 by 1080 PNG, JPEG or WebP image to your own MCP-created unpublished standalone quiz or exam with no existing cover. Provide the current version and actual canonical base64 image bytes (maximum 512 KiB decoded), not a URL or data URI. Reuse the request UUID only for identical retries. No replacement, publication or arbitrary uploads.',
            'inputSchema' => ['type' => 'object', 'additionalProperties' => false,
                'properties' => $properties, 'required' => array_keys($properties)],
            'securitySchemes' => $schemes, '_meta' => ['securitySchemes' => $schemes],
            'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
        ]];
    }

    public static function supports(string $name): bool
    {
        return $name === self::NAME && McpOAuthDraftScopePolicy::assessmentMediaEnabled();
    }

    public function execute(User $actor, array $args): array
    {
        abort_unless(McpOAuthDraftScopePolicy::assessmentMediaEnabled(), 403);
        abort_unless($this->path === null, 409);
        $fields = ['assessment_id', 'kind', 'expected_version', 'request_id', 'image_base64', 'alt'];
        if (array_diff(array_keys($args), $fields) || array_diff($fields, array_keys($args))) {
            throw ValidationException::withMessages(['arguments' => 'Unknown or missing fields.']);
        }
        $data = Validator::make($args, [
            'assessment_id' => ['required', 'integer', 'min:1'],
            'kind' => ['required', 'in:quiz,exam'],
            'expected_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D'],
            'request_id' => ['required', 'uuid'],
            'image_base64' => ['required', 'string', 'max:'.McpOAuthDraftCoverUpload::MAX_BASE64_CHARS],
            'alt' => ['required', 'string', 'max:200'],
        ])->validate();

        return DB::transaction(function () use ($actor, $data): array {
            $current = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            abort_unless(McpOAuthDraftScopePolicy::eligible($current), 403);
            $exam = McpOAuthStandaloneAssessment::owned($current, $data['kind'], (int) $data['assessment_id']);
            $receipt = strtolower($data['request_id']);
            $hash = hash('sha256', json_encode([self::NAME, (int) $exam->id, $data['kind'],
                $data['expected_version'], hash('sha256', $data['image_base64']), $data['alt']], JSON_THROW_ON_ERROR));
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
            abort_unless($exam->featured_kind === null && $exam->featured_media_id === null
                && $exam->featured_value === null, 409);

            [$bytes, $mime, $extension] = McpOAuthDraftCoverUpload::sanitize($data['image_base64']);
            $disk = ManagedFiles::disk();
            $path = 'editor-media/mcp-assessment-cover-'.Str::uuid().'.'.$extension;
            $this->disk = $disk;
            $this->path = $path;
            if (!ManagedFiles::put($disk, $path, $bytes, true)) {
                $this->path = null;
                abort(409, 'Media object collision.');
            }
            abort_unless($disk->fileExists($path), 503);
            $media = EditorMedia::create([
                'context' => 'library', 'uploaded_by' => $current->id,
                'path' => $path, 'mime' => $mime, 'original_name' => 'assessment-cover.'.$extension,
                'size' => strlen($bytes),
            ]);
            $exam->update(['featured_kind' => 'image', 'featured_media_id' => $media->id,
                'featured_value' => null, 'featured_alt' => $data['alt']]);
            $result = McpOAuthStandaloneAssessment::summary($exam->refresh()) + [
                'status' => 'updated', 'media_id' => $media->id, 'mime' => $mime,
                'width' => 1920, 'height' => 1080, 'size_bytes' => strlen($bytes), 'receipt_id' => $receipt,
            ];
            McpActionAudit::create([
                'id' => $receipt, 'user_id' => $current->id, 'token_id' => null, 'tool' => self::NAME,
                'operation' => 'UPDATE', 'resource' => 'assessments', 'resource_id' => (string) $exam->id,
                'result' => 'success', 'http_status' => 200, 'summary' => 'Validated assessment cover uploaded and attached; bytes not retained in audit.',
            ]);
            DB::table('mcp_oauth_draft_receipts')->where('id', $receipt)->update([
                'result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            return $result + ['replayed' => false];
        });
    }

    /** Reconcile only this request's object after the authorization transaction ends. */
    public function cleanupUncommitted(): void
    {
        if ($this->path === null || $this->disk === null) {
            return;
        }
        try {
            if (!EditorMedia::query()->where('path', $this->path)->exists()
                && $this->disk->fileExists($this->path)
                && !$this->disk->delete($this->path)) {
                Log::warning('MCP assessment cover cleanup requires operator review.');
            }
        } catch (Throwable) {
            Log::warning('MCP assessment cover cleanup requires operator review.');
        } finally {
            $this->path = null;
            $this->disk = null;
        }
    }
}
