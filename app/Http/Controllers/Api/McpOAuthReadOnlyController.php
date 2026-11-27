<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\McpActionAudit;
use App\Models\Course;
use App\Models\User;
use App\Support\McpOAuthDraftScopePolicy;
use App\Support\McpOAuthReadCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Separate OAuth-only MCP resource. Never dispatch write, upload, delete,
 * publish, arbitrary management, or approval tools through this endpoint.
 * The original Sanctum POST /api/mcp remains completely unchanged.
 */
final class McpOAuthReadOnlyController extends Controller
{
    private const RESOURCES = ['courses', 'analytics'];

    public function handle(Request $request): JsonResponse
    {
        // Only an enabled, separately authorized WRITE token can reach the draft handler.
        // It independently verifies the signed token, exact scope and audience.
        if (\App\Support\McpOAuthDraftScopePolicy::issuanceEnabled()
            && $request->user() instanceof \App\Models\McpOAuthUser
            && $request->user()->tokenCan('mcp:write')) {
            return app(McpOAuthDraftController::class)->handle($request);
        }

        return $this->handlePublicRead($request);
    }

    /** Internal read dispatcher: caller must have completed scoped OAuth authorization. */
    public function handlePublicRead(Request $request): JsonResponse
    {
        abort_unless($request->isJson(), 415, 'JSON-RPC application/json required.');
        $rpc = $request->json()->all();
        if (! is_array($rpc) || array_is_list($rpc) || ($rpc['jsonrpc'] ?? '') !== '2.0') {
            return response()->json(['jsonrpc' => '2.0', 'id' => null,
                'error' => ['code' => -32600, 'message' => 'Invalid JSON-RPC request.']], 400);
        }

        $id = $rpc['id'] ?? null;
        $method = $rpc['method'] ?? '';
        if (! is_string($method)) {
            return $this->rpcError($id, -32600, 'Invalid method.');
        }
        if ($method === 'notifications/initialized') {
            return response()->json([], 202)->header('Cache-Control', 'no-store');
        }

        if ($method === 'initialize') {
            return $this->success($id, [
                'protocolVersion' => '2025-11-25',
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'learncambodia-oauth-read-only', 'version' => '0.1.0'],
                'instructions' => 'OAuth resource permits READ of public catalog metadata and aggregate analytics only. No content mutations.',
            ]);
        }
        if ($method === 'ping') {
            return $this->success($id, (object) []);
        }
        if ($method === 'tools/list') {
            $tools = self::publicCatalog();
            if ($this->mayRequestDraftScope($request)) {
                // Discover descriptions only. A READ token cannot execute these tools.
                $tools = [...$tools, ...McpOAuthDraftController::catalog()];
            }
            return $this->success($id, ['tools' => $tools]);
        }
        if ($method !== 'tools/call') {
            return $this->rpcError($id, -32601, 'Method unavailable on OAuth READ resource.');
        }

        $params = $rpc['params'] ?? null;
        $args = is_array($params) ? ($params['arguments'] ?? null) : null;
        $name = is_array($params) ? ($params['name'] ?? null) : null;
        if (is_string($name) && str_contains($name, '_draft_')
            && in_array($name, array_column(McpOAuthDraftController::catalog(), 'name'), true)
            && $this->mayRequestDraftScope($request)) {
            // This branch never dispatches a draft lookup or mutation.
            return self::scopeChallenge($id, \App\Support\McpOAuthNativeCourseBuilder::supports($name)
                || \App\Support\McpOAuthDraftCoverUpload::supports($name)
                || \App\Support\McpOAuthCurriculumEditor::supports($name),
                \App\Support\McpOAuthDraftCoverUpload::supports($name),
                \App\Support\McpOAuthCurriculumEditor::supports($name));
        }
        if (is_string($name) && McpOAuthReadCatalog::supports($name)) {
            return $this->dedicatedRead($request, $id, $name, $args);
        }
        $operation = is_array($args) ? ($args['operation'] ?? null) : null;
        $resource = is_array($args) ? ($args['resource'] ?? null) : null;
        $valid = is_array($params) && ($params['name'] ?? null) === 'crud_manage_platform'
            && is_array($args) && $operation === 'READ'
            && in_array($resource, self::RESOURCES, true)
            && (! isset($args['payload']) || is_array($args['payload']));

        // OAuth never inherits the existing MCP write-capable catalog.
        if (! $valid) {
            return $this->toolResult($id, [
                'status' => 'rejected', 'http_status' => 403,
                'message' => 'Only READ courses and analytics are supported.',
            ], true);
        }

        try {
            $data = $resource === 'courses'
                ? $this->publicCourses($args['payload'] ?? [])
                : McpOAuthReadCatalog::read('get_platform_analytics', $args['payload'] ?? []);
            $data['status'] = 'read';
            $data['resource'] = $resource;
            $data['http_status'] = 200;
            $data['receipt_id'] = $this->audit($request, (string) $resource, 'success', 200);
            return $this->toolResult($id, $data, false);
        } catch (Throwable $error) {
            $receipt = $this->audit($request, (string) $resource, 'failed', 403);
            return $this->toolResult($id, [
                'status' => 'rejected',
                'http_status' => 403,
                'receipt_id' => $receipt,
                'message' => 'Read operation rejected. Contact the website administrator with the receipt ID.',
            ], true);
        }
    }

    private function dedicatedRead(Request $request, mixed $id, string $name, mixed $args): JsonResponse
    {
        try {
            if (! is_array($args)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['arguments' => 'Expected object.']);
            }
            $data = McpOAuthReadCatalog::read($name, $args);
            $data['status'] = 'read';
            $data['resource'] = $name;
            $data['http_status'] = 200;
            $data['receipt_id'] = $this->audit($request, $name, 'success', 200);
            return $this->toolResult($id, $data, false);
        } catch (Throwable $error) {
            $receipt = $this->audit($request, $name, 'failed', 403);
            return $this->toolResult($id, [
                'status' => 'rejected', 'http_status' => 403, 'receipt_id' => $receipt,
                'message' => 'Read operation rejected. Contact the website administrator with the receipt ID.',
            ], true);
        }
    }

    private function publicCourses(array $payload): array
    {
        $filter = Validator::make($payload, ['id' => ['sometimes', 'integer', 'min:1']])->validate();
        $columns = ['id', 'title', 'slug', 'summary', 'status', 'category_id', 'content_language', 'updated_at'];
        // Use the public catalog's visibility scope, including category,
        // publication date, deactivation and supersession. Never delegate
        // OAuth course reads to the administrator's unrestricted CRUD engine.
        $query = Course::query()->discoverable();
        $fields = static fn (Course $course): array => collect($columns)
            ->mapWithKeys(fn ($field) => [$field => $course->{$field}])->all();

        if (isset($filter['id'])) {
            return ['items' => [$fields($query->findOrFail((int) $filter['id'], $columns))], 'total' => 1];
        }

        return ['items' => $query->orderByDesc('id')->limit(25)->get($columns)->map($fields)->all(), 'limit' => 25];
    }

    public static function publicCatalog(): array
    {
        $schemes = [['type' => 'oauth2', 'scopes' => ['mcp:read']]];
        return array_map(static fn (array $tool): array => $tool + [
            'securitySchemes' => $schemes,
            '_meta' => ['securitySchemes' => $schemes],
        ], [self::readTool(), ...McpOAuthReadCatalog::tools()]);
    }

    /** Only the selected, currently eligible administrator may discover step-up tools. */
    private function mayRequestDraftScope(Request $request): bool
    {
        $identity = $request->user();
        return McpOAuthDraftScopePolicy::issuanceEnabled()
            && $identity instanceof \App\Models\McpOAuthUser
            && $identity->tokenCan('mcp:read')
            && ! $identity->tokenCan('mcp:write')
            && McpOAuthDraftScopePolicy::eligible(User::query()->find($identity->getAuthIdentifier()));
    }

    public static function scopeChallenge(mixed $id, bool $curriculum = false, bool $media = false, bool $edit = false): JsonResponse
    {
        $challenge = 'Bearer resource_metadata="https://learncambodia.com/.well-known/oauth-protected-resource", '
            .'error="insufficient_scope", scope="mcp:read mcp:write'.(($curriculum || $media || $edit) ? ' mcp:curriculum' : '').($media ? ' mcp:media' : '').($edit ? ' mcp:curriculum-edit' : '').'", '
            .'error_description="Fresh administrator draft authorization is required"';
        // HTTP 403 is the MCP transport scope-upgrade signal; include the
        // tool-result challenge too for clients supporting the OpenAI extension.
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => [
            'content' => [['type' => 'text', 'text' => 'Fresh OAuth consent is required for draft access. No operation was performed.']],
            'structuredContent' => ['status' => 'rejected', 'http_status' => 403],
            'isError' => true,
            '_meta' => ['mcp/www_authenticate' => [$challenge]],
        ]], 403)->header('WWW-Authenticate', $challenge)->header('Cache-Control', 'no-store');
    }

    private static function readTool(): array
    {
        return [
            'name' => 'crud_manage_platform',
            'description' => 'Read LearnCambodia public courses or aggregated education metrics. No writes.',
            'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'operation' => ['type' => 'string', 'enum' => ['READ']],
                    'resource' => ['type' => 'string', 'enum' => self::RESOURCES],
                    'payload' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'required' => ['operation', 'resource'],
                'additionalProperties' => false,
            ],
        ];
    }

    private function audit(Request $request, string $resource, string $result, int $status): string
    {
        $receipt = (string) Str::uuid();
        McpActionAudit::create([
            'id' => $receipt,
            'user_id' => $request->user()->getAuthIdentifier(),
            'token_id' => null, // Passport IDs are UUID strings, Sanctum audit column is bigint.
            'tool' => 'oauth_crud_read',
            'operation' => 'READ',
            'resource' => $resource,
            'resource_id' => null,
            'result' => $result,
            'http_status' => $status,
            'summary' => 'Scoped read-only OAuth MCP resource request.',
        ]);
        return $receipt;
    }

    private function success(mixed $id, mixed $result): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result])
            ->header('Cache-Control', 'no-store');
    }

    private function toolResult(mixed $id, array $data, bool $error): JsonResponse
    {
        return $this->success($id, [
            'content' => [['type' => 'text', 'text' => json_encode($data, JSON_THROW_ON_ERROR)]],
            'structuredContent' => $data, 'isError' => $error,
        ]);
    }

    private function rpcError(mixed $id, int $code, string $message): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id,
            'error' => ['code' => $code, 'message' => $message]], 200)
            ->header('Cache-Control', 'no-store');
    }
}
