<?php

namespace App\Support;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\McpActionAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Internal mutation service only. No HTTP route or MCP tool invokes this class.
 * A future Passport adapter MUST verify the separate write scope, token,
 * audience, client and consent before passing an authenticated actor here.
 * Existing OAuth READ middleware must remain unchanged.
 */
final class McpOAuthDraftWriter
{
    public static function execute(User $actor, string $operation, string $resource, array $payload, string $requestId): array
    {
        abort_unless(config('mcp.oauth_draft_writes_enabled', false) === true, 403);
        abort_unless(in_array($operation, ['CREATE', 'UPDATE'], true)
            && in_array($resource, ['courses', 'articles', 'pages'], true), 422);
        Validator::make(['request_id' => $requestId], ['request_id' => ['required', 'uuid']])->validate();
        $requestId = strtolower($requestId);

        $course = $resource === 'courses';
        $rules = [
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['required', 'string', 'max:'.($course ? 1000 : 400)],
        ];
        if ($course) {
            $rules += [
                'category_id' => ['required', 'integer', 'min:1'],
                'delivery_mode' => ['required', 'in:online,onsite,hybrid'],
                'description' => ['required', 'string', 'max:25000'],
            ];
        } else {
            $rules['body'] = ['required', 'string', 'max:25000'];
        }
        if ($operation === 'UPDATE') {
            $rules['id'] = ['required', 'integer', 'min:1'];
            $rules['expected_version'] = ['required', 'regex:/\A[a-f0-9]{64}\z/D'];
        }
        if (array_diff(array_keys($payload), array_keys($rules))) {
            throw ValidationException::withMessages(['payload' => 'Unknown or forbidden draft field.']);
        }
        $data = Validator::make($payload, $rules)->validate();
        ksort($data);
        $hash = hash('sha256', json_encode([$operation, $resource, $data], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $operation, $resource, $course, $data, $requestId, $hash): array {
            // Always re-read current authorization. A stale actor instance is insufficient.
            $current = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            abort_unless($current->isAdministrator() && $current->hasVerifiedEmail()
                && $current->two_factor_confirmed_at !== null
                && $current->hasEnabledTwoFactorAuthentication(), 403);

            DB::table('mcp_oauth_draft_receipts')->insertOrIgnore([
                'id' => $requestId, 'user_id' => $current->id, 'request_hash' => $hash,
                'result' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $receipt = DB::table('mcp_oauth_draft_receipts')->where('id', $requestId)->lockForUpdate()->first();
            abort_unless($receipt !== null && (int) $receipt->user_id === (int) $current->id
                && hash_equals($receipt->request_hash, $hash), 409, 'Request ID conflict.');
            if ($receipt->result !== null) {
                return json_decode($receipt->result, true, 512, JSON_THROW_ON_ERROR) + ['replayed' => true];
            }

            $fields = $data;
            unset($fields['id'], $fields['expected_version']);
            if ($course) {
                abort_unless(Category::query()->published()->whereKey($fields['category_id'])->exists(), 422);
            }
            $query = $course ? Course::query()
                : ContentEntry::query()->where('type', $resource === 'articles' ? 'post' : 'page');

            if ($operation === 'CREATE') {
                $fields['slug'] = (Str::limit(Str::slug($fields['title']) ?: 'draft', 110, '')).'-'.$requestId;
                $fields['status'] = 'draft';
                $fields['created_by'] = $current->id;
                if (! $course) {
                    $fields['type'] = $resource === 'articles' ? 'post' : 'page';
                    $fields['updated_by'] = $current->id;
                }
                $model = $query->create($fields);
            } else {
                $model = $query->lockForUpdate()->findOrFail($data['id']);
                abort_unless((int) $model->created_by === (int) $current->id
                    && $model->status === 'draft' && $model->published_at === null, 403);
                // This initial writer is intentionally plain text only. Do not
                // overwrite rich editor content or a revision of a live course.
                if ($course) {
                    abort_unless($model->source_course_id === null && $model->superseded_at === null
                        && $model->deactivated_at === null && blank($model->description_html), 409);
                } else {
                    abort_unless(blank($model->body_html), 409);
                    $fields['updated_by'] = $current->id;
                }
                abort_unless(hash_equals(self::version($model), $data['expected_version']), 409, 'Draft changed; reload before editing.');
                $model->fill($fields)->save();
            }
            $model->refresh();

            $result = [
                'status' => $operation === 'CREATE' ? 'created' : 'updated',
                'resource' => $resource, 'resource_id' => $model->id,
                'draft_version' => self::version($model), 'receipt_id' => $requestId,
                'publication_status' => 'draft',
            ];
            McpActionAudit::create([
                'id' => $requestId, 'user_id' => $current->id, 'token_id' => null,
                'tool' => 'oauth_draft_writer', 'operation' => $operation,
                'resource' => $resource, 'resource_id' => (string) $model->id,
                'result' => 'success', 'http_status' => 200,
                'summary' => 'Draft-only mutation; no publication or deletion.',
            ]);
            DB::table('mcp_oauth_draft_receipts')->where('id', $requestId)->update([
                'result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            return $result + ['replayed' => false];
        }, 3);
    }

    private static function version(Model $model): string
    {
        $attributes = $model->getRawOriginal();
        ksort($attributes);
        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }
}
