<?php

namespace App\Support;

use App\Models\Course;
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

/** Rehearsal-only upload of a sanitized image to an owned unpublished course. */
final class McpOAuthDraftCoverUpload
{
    public const NAME = 'upload_draft_course_cover';
    public const MAX_INPUT_BYTES = 524288;
    public const MAX_BASE64_CHARS = 699052;
    public const MAX_STORED_BYTES = 2097152;

    private ?FilesystemAdapter $disk = null;
    private ?string $path = null;

    public static function catalog(): array
    {
        if (!McpOAuthDraftScopePolicy::mediaEnabled()) {
            return [];
        }
        $schemes = [['type' => 'oauth2', 'scopes' => ['mcp:read', 'mcp:write', McpOAuthDraftScopePolicy::CURRICULUM_SCOPE, McpOAuthDraftScopePolicy::MEDIA_SCOPE]]];
        return [[
            'name' => self::NAME,
            'description' => 'Upload and attach one 1920 by 1080 PNG, JPEG or WebP image to your own unpublished course with no existing cover. Provide actual base64 image bytes (maximum 512 KiB decoded), not a URL or data URI. Reuse the same request UUID only for identical retries. No replacement, publication or arbitrary uploads.',
            'inputSchema' => ['type' => 'object', 'additionalProperties' => false,
                'properties' => [
                    'course_id' => ['type' => 'integer', 'minimum' => 1],
                    'request_id' => ['type' => 'string', 'format' => 'uuid'],
                    'image_base64' => ['type' => 'string', 'minLength' => 4, 'maxLength' => self::MAX_BASE64_CHARS],
                    'alt' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                ],
                'required' => ['course_id', 'request_id', 'image_base64', 'alt']],
            'securitySchemes' => $schemes, '_meta' => ['securitySchemes' => $schemes],
            'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
        ]];
    }

    public static function supports(string $name): bool
    {
        return $name === self::NAME && McpOAuthDraftScopePolicy::mediaEnabled();
    }

    public function execute(User $actor, array $args): array
    {
        abort_unless(McpOAuthDraftScopePolicy::mediaEnabled(), 403);
        abort_unless($this->path === null, 409);
        $fields = ['course_id', 'request_id', 'image_base64', 'alt'];
        if (array_diff(array_keys($args), $fields) || array_diff($fields, array_keys($args))) {
            throw ValidationException::withMessages(['arguments' => 'Unknown or missing fields.']);
        }
        $data = Validator::make($args, [
            'course_id' => ['required', 'integer', 'min:1'],
            'request_id' => ['required', 'uuid'],
            'image_base64' => ['required', 'string', 'max:'.self::MAX_BASE64_CHARS],
            'alt' => ['required', 'string', 'max:200'],
        ])->validate();

        // The signed-token authorization adapter holds its own outer
        // transaction. This savepoint rolls back all media/course/audit rows
        // even when the transport converts an exception into an RPC error.
        return DB::transaction(function () use ($actor, $data): array {
            $current = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            abort_unless(McpOAuthDraftScopePolicy::eligible($current), 403);
            $course = Course::query()->where('created_by', $current->id)->where('status', 'draft')
                ->whereNull('published_at')->whereNull('source_course_id')->whereNull('superseded_at')
                ->whereNull('deactivated_at')->lockForUpdate()->findOrFail($data['course_id']);

            $receipt = strtolower($data['request_id']);
            $hash = hash('sha256', json_encode([self::NAME, (int) $course->id, hash('sha256', $data['image_base64']), $data['alt']], JSON_THROW_ON_ERROR));
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
            abort_unless($course->featured_kind === null && $course->featured_media_id === null
                && $course->featured_value === null, 409);

            [$bytes, $mime, $extension] = self::sanitize($data['image_base64']);
            $disk = ManagedFiles::disk();
            $path = 'editor-media/mcp-cover-'.Str::uuid().'.'.$extension;
            // Track this randomly named object before the write. On an
            // ambiguous storage error, reconcile after the database rolls back.
            $this->disk = $disk;
            $this->path = $path;
            if (!ManagedFiles::put($disk, $path, $bytes, true)) {
                // Conditional create found an existing object: never delete it.
                $this->path = null;
                abort(409, 'Media object collision.');
            }
            abort_unless($disk->fileExists($path), 503);
            $media = EditorMedia::create([
                'context' => 'library', 'uploaded_by' => $current->id,
                'path' => $path, 'mime' => $mime, 'original_name' => 'course-cover.'.$extension,
                'size' => strlen($bytes),
            ]);
            $course->update(['featured_kind' => 'image', 'featured_media_id' => $media->id,
                'featured_value' => null, 'featured_alt' => $data['alt']]);
            $result = ['status' => 'updated', 'resource' => 'courses', 'resource_id' => $course->id,
                'publication_status' => 'draft', 'media_id' => $media->id, 'featured_media_id' => $media->id,
                'mime' => $mime, 'width' => 1920, 'height' => 1080, 'size_bytes' => strlen($bytes),
                'receipt_id' => $receipt];
            McpActionAudit::create([
                'id' => $receipt, 'user_id' => $current->id, 'token_id' => null, 'tool' => self::NAME,
                'operation' => 'UPDATE', 'resource' => 'courses', 'resource_id' => (string) $course->id,
                'result' => 'success', 'http_status' => 200, 'summary' => 'Validated draft cover uploaded and attached; image bytes not retained in audit.',
            ]);
            DB::table('mcp_oauth_draft_receipts')->where('id', $receipt)->update([
                'result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            return $result + ['replayed' => false];
        });
    }

    /** Call after the enclosing authorization transaction has completed. */
    public function cleanupUncommitted(): void
    {
        if ($this->path === null || $this->disk === null) {
            return;
        }
        try {
            // If commit status is uncertain, never delete a referenced file.
            if (!EditorMedia::query()->where('path', $this->path)->exists()
                && $this->disk->fileExists($this->path)
                && !$this->disk->delete($this->path)) {
                Log::warning('MCP cover cleanup requires operator review.');
            }
        } catch (Throwable) {
            // Do not expose the storage configuration, path or image data.
            Log::warning('MCP cover cleanup requires operator review.');
        } finally {
            $this->path = null;
            $this->disk = null;
        }
    }

    /** Shared bounded image decoder for native course and assessment covers. */
    public static function sanitize(string $encoded): array
    {
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_INPUT_BYTES
            || !hash_equals(base64_encode($bytes), $encoded)) {
            self::invalid();
        }
        $info = @getimagesizefromstring($bytes);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!$info || $info[0] !== 1920 || $info[1] !== 1080
            || !in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)
            || ($info['mime'] ?? null) !== $mime) {
            self::invalid();
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false || imagesx($image) !== 1920 || imagesy($image) !== 1080) {
            self::invalid();
        }
        // Re-encode pixels, stripping arbitrary appended payloads and metadata.
        // PNG retains transparency; JPEG/WebP input is normalized to JPEG.
        $png = $mime === 'image/png';
        ob_start();
        try {
            if ($png) {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $ok = imagepng($image, null, 6);
            } else {
                $ok = imagejpeg($image, null, 85);
            }
            $clean = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (!$ok || !is_string($clean) || $clean === '' || strlen($clean) > self::MAX_STORED_BYTES) {
            self::invalid();
        }
        return [$clean, $png ? 'image/png' : 'image/jpeg', $png ? 'png' : 'jpg'];
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages(['image_base64' => 'Provide a valid 1920 by 1080 PNG, JPEG or WebP image within the upload limits.']);
    }
}
