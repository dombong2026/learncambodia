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

/** Atomically upload bounded PCM WAV and append one native listening question. */
final class McpOAuthAssessmentAudioUpload
{
    public const NAME = 'upload_draft_assessment_audio';

    public const MAX_INPUT_BYTES = 524288;
    public const MAX_BASE64_CHARS = 699052;

    private ?FilesystemAdapter $disk = null;
    private ?string $path = null;

    public static function catalog(): array
    {
        if (!McpOAuthDraftScopePolicy::assessmentAudioEnabled()) {
            return [];
        }
        $schemes = [['type' => 'oauth2', 'scopes' => ['mcp:read', 'mcp:write',
            McpOAuthDraftScopePolicy::ASSESSMENT_SCOPE, McpOAuthDraftScopePolicy::ASSESSMENT_ADVANCED_SCOPE,
            McpOAuthDraftScopePolicy::ASSESSMENT_AUDIO_SCOPE]]];
        $properties = [
            'assessment_id' => ['type' => 'integer', 'minimum' => 1],
            'kind' => ['type' => 'string', 'enum' => ['quiz', 'exam']],
            'expected_version' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
            'request_id' => ['type' => 'string', 'format' => 'uuid'],
            'audio_base64' => ['type' => 'string', 'minLength' => 4, 'maxLength' => self::MAX_BASE64_CHARS],
            'prompt' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 600],
            'a' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 300],
            'b' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 300],
            'c' => ['type' => 'string', 'maxLength' => 300],
            'd' => ['type' => 'string', 'maxLength' => 300],
            'correct' => ['type' => 'string', 'enum' => ['a', 'b', 'c', 'd']],
            'points' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            'feedback' => ['type' => 'string', 'maxLength' => 800],
        ];
        return [[
            'name' => self::NAME,
            'description' => 'Upload canonical PCM WAV bytes (16-bit mono or stereo, 8–48 kHz, 512 KiB maximum, 0.1–30 seconds, standard 44-byte header with no extra chunks) and append one listening question to an owned unpublished standalone quiz or exam in one transaction. Supply distinct choices A and B, optional C and D, correct letter, current version and UUID. Private storage only; no URL, arbitrary file upload, replacement or publication.',
            'inputSchema' => ['type' => 'object', 'additionalProperties' => false,
                'properties' => $properties, 'required' => array_keys($properties)],
            'securitySchemes' => $schemes, '_meta' => ['securitySchemes' => $schemes],
            'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
        ]];
    }

    public static function supports(string $name): bool
    {
        return $name === self::NAME && McpOAuthDraftScopePolicy::assessmentAudioEnabled();
    }

    public function execute(User $actor, array $args): array
    {
        abort_unless(McpOAuthDraftScopePolicy::assessmentAudioEnabled(), 403);
        abort_unless($this->path === null, 409);
        $fields = ['assessment_id', 'kind', 'expected_version', 'request_id', 'audio_base64', 'prompt', 'a', 'b', 'c', 'd', 'correct', 'points', 'feedback'];
        if (array_diff(array_keys($args), $fields) || array_diff($fields, array_keys($args))) {
            throw ValidationException::withMessages(['arguments' => 'Unknown or missing fields.']);
        }
        $data = Validator::make($args, [
            'assessment_id' => ['required', 'integer', 'min:1'],
            'kind' => ['required', 'in:quiz,exam'],
            'expected_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D'],
            'request_id' => ['required', 'uuid'],
            'audio_base64' => ['required', 'string', 'max:'.self::MAX_BASE64_CHARS],
            'prompt' => ['required', 'string', 'max:600'],
            'a' => ['required', 'string', 'max:300'], 'b' => ['required', 'string', 'max:300'],
            'c' => ['present', 'nullable', 'string', 'max:300'], 'd' => ['present', 'nullable', 'string', 'max:300'],
            'correct' => ['required', 'in:a,b,c,d'], 'points' => ['required', 'integer', 'between:1,100'],
            'feedback' => ['present', 'nullable', 'string', 'max:800'],
        ])->validate();

        $question = array_intersect_key($data, array_flip(['prompt', 'a', 'b', 'c', 'd', 'correct', 'points', 'feedback']));
        $question['points'] = (int) $question['points'];
        $choices = array_values(array_filter(array_map(static fn ($v) => mb_strtolower(trim((string) $v)),
            [$question['a'], $question['b'], $question['c'], $question['d']]), static fn ($v) => $v !== ''));
        if (count($choices) !== count(array_unique($choices)) || blank($question[$question['correct']])) {
            throw ValidationException::withMessages(['correct' => 'Distinct choices and a populated correct option are required.']);
        }

        return DB::transaction(function () use ($actor, $data, $question): array {
            $current = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            abort_unless(McpOAuthDraftScopePolicy::eligible($current), 403);
            $exam = McpOAuthStandaloneAssessment::owned($current, $data['kind'], (int) $data['assessment_id']);
            $receipt = strtolower($data['request_id']);
            $hash = hash('sha256', json_encode([self::NAME, (int) $exam->id, $data['kind'],
                $data['expected_version'], hash('sha256', $data['audio_base64']), $question], JSON_THROW_ON_ERROR));
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
            $questions = $exam->questions ?? [];
            abort_if(count($questions) >= ($exam->kind === 'quiz' ? 50 : 200), 422);
            [$bytes, $durationMs, $rate, $channels] = self::sanitize($data['audio_base64']);
            $mime = 'audio/wav';
            $disk = ManagedFiles::disk();
            $path = 'editor-media/mcp-assessment-audio-'.Str::uuid().'.wav';
            $this->disk = $disk;
            $this->path = $path;
            if (!ManagedFiles::put($disk, $path, $bytes, true)) {
                $this->path = null;
                abort(409, 'Media object collision.');
            }
            abort_unless($disk->fileExists($path), 503);
            $media = EditorMedia::create([
                'context' => 'exam', 'uploaded_by' => $current->id,
                'path' => $path, 'mime' => $mime, 'original_name' => 'assessment-listening.wav',
                'size' => strlen($bytes),
            ]);
            $question = AssessmentQuestions::validate([$question + ['format' => 'listening', 'media_id' => $media->id]])[0];
            $exam->media()->syncWithoutDetaching([$media->id]);
            $exam->update(['questions' => [...$questions, $question]]);
            $result = McpOAuthStandaloneAssessment::summary($exam->refresh()) + [
                'status' => 'updated', 'media_id' => $media->id, 'mime' => $mime,
                'duration_ms' => $durationMs, 'sample_rate' => $rate, 'channels' => $channels,
                'size_bytes' => strlen($bytes), 'question_index' => count($questions), 'receipt_id' => $receipt,
            ];
            McpActionAudit::create([
                'id' => $receipt, 'user_id' => $current->id, 'token_id' => null, 'tool' => self::NAME,
                'operation' => 'UPDATE', 'resource' => 'assessments', 'resource_id' => (string) $exam->id,
                'result' => 'success', 'http_status' => 200, 'summary' => 'Validated WAV audio uploaded and listening question appended; bytes not retained in audit.',
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
                Log::warning('MCP assessment audio cleanup requires operator review.');
            }
        } catch (Throwable) {
            Log::warning('MCP assessment audio cleanup requires operator review.');
        } finally {
            $this->path = null;
            $this->disk = null;
        }
    }

    /** Only canonical RIFF PCM is accepted; no arbitrary chunks, codecs or external decoders. */
    public static function sanitize(string $encoded): array
    {
        if (strlen($encoded) > self::MAX_BASE64_CHARS) {
            self::invalid();
        }
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || strlen($bytes) < 44 || strlen($bytes) > self::MAX_INPUT_BYTES
            || !hash_equals(base64_encode($bytes), $encoded)
            || substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 8) !== 'WAVEfmt '
            || substr($bytes, 36, 4) !== 'data') {
            self::invalid();
        }
        $riff = unpack('Vsize', substr($bytes, 4, 4))['size'];
        $fmt = unpack('Vlength/vcodec/vchannels/Vrate/VbyteRate/vblock/vbits', substr($bytes, 16, 20));
        $length = unpack('Vsize', substr($bytes, 40, 4))['size'];
        $rate = $fmt['rate'];
        $channels = $fmt['channels'];
        if ($riff !== strlen($bytes) - 8 || $fmt['length'] !== 16 || $fmt['codec'] !== 1
            || !in_array($channels, [1, 2], true) || $rate < 8000 || $rate > 48000
            || $fmt['bits'] !== 16 || $fmt['block'] !== 2 * $channels
            || $fmt['byteRate'] !== $rate * $fmt['block']
            || $length !== strlen($bytes) - 44 || $length % $fmt['block'] !== 0
            || $length * 10 < $fmt['byteRate'] || $length > 30 * $fmt['byteRate']) {
            self::invalid();
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime, ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'], true)) {
            self::invalid();
        }
        // Rebuild the complete canonical header and retain only the validated PCM frames.
        $clean = 'RIFF'.pack('V', 36 + $length).'WAVEfmt '
            .pack('VvvVVvv', 16, 1, $channels, $rate, $fmt['byteRate'], $fmt['block'], 16)
            .'data'.pack('V', $length).substr($bytes, 44);
        return [$clean, intdiv($length * 1000, $fmt['byteRate']), $rate, $channels];
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages(['audio_base64' => 'Provide canonical 16-bit PCM WAV, 8–48 kHz, mono/stereo, 0.1–30 seconds, at most 512 KiB, without extra chunks.']);
    }
}
