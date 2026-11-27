<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\McpActionAudit;
use Illuminate\Support\Str;
use App\Support\McpOAuthDraftAuthorization;
use App\Support\McpOAuthDraftScopePolicy;
use App\Support\McpOAuthDraftWriter;
use App\Support\McpOAuthNativeCourseBuilder;
use App\Support\McpOAuthDraftCoverUpload;
use App\Support\McpOAuthCurriculumEditor;
use App\Support\McpOAuthStandaloneAssessment;
use App\Support\McpOAuthAssessmentCoverUpload;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Draft-only MCP transport. All executions require the independent issuance and authorization policy. */
final class McpOAuthDraftController
{
    private const RESOURCES = ['courses', 'articles', 'pages'];

    /** Static tool definitions contain no account or draft data. */
    public static function catalog(): array
    {
        $tools = [];
        $schemes = [['type' => 'oauth2', 'scopes' => ['mcp:read', 'mcp:write']]];
        foreach (self::RESOURCES as $resource) {
            foreach (['get', 'create', 'update'] as $action) {
                $properties = ['id' => ['type' => 'integer', 'minimum' => 1]];
                if ($action !== 'get') {
                    $properties = [
                        'request_id' => ['type' => 'string', 'format' => 'uuid', 'description' => 'Unique UUID; reuse only to retry the identical request.'],
                        'title' => ['type' => 'string', 'maxLength' => 180],
                        'summary' => ['type' => 'string', 'maxLength' => $resource === 'courses' ? 1000 : 400],
                    ];
                    if ($resource === 'courses') {
                        $properties += [
                            'description' => ['type' => 'string', 'maxLength' => 25000],
                            'category_id' => ['type' => 'integer', 'minimum' => 1],
                            'delivery_mode' => ['type' => 'string', 'enum' => ['online', 'onsite', 'hybrid']],
                        ];
                    } else {
                        $properties['body'] = ['type' => 'string', 'maxLength' => 25000];
                    }
                    if ($action === 'update') {
                        $properties += [
                            'id' => ['type' => 'integer', 'minimum' => 1],
                            'expected_version' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
                        ];
                    }
                }
                $tools[] = [
                    'name' => $action.'_draft_'.$resource,
                    'description' => ucfirst($action).' own unpublished plain-text '.$resource.' draft. No publishing or deletion. '.($action === 'update' ? 'Use a version from draft lookup and provide all editable fields.' : ''),
                    'securitySchemes' => $schemes,
                    '_meta' => ['securitySchemes' => $schemes],
                    'annotations' => [
                        'readOnlyHint' => $action === 'get',
                        'destructiveHint' => $action === 'update',
                        'idempotentHint' => true,
                        'openWorldHint' => false,
                    ],
                    'inputSchema' => [
                        'type' => 'object', 'properties' => $properties,
                        'required' => array_keys($properties), 'additionalProperties' => false,
                    ],
                ];
            }
        }
        return [...$tools, ...McpOAuthNativeCourseBuilder::catalog(), ...McpOAuthDraftCoverUpload::catalog(), ...McpOAuthCurriculumEditor::catalog(), ...McpOAuthStandaloneAssessment::catalog(), ...McpOAuthAssessmentCoverUpload::catalog()];
    }

    public function handle(Request $request): JsonResponse
    {
        abort_unless(McpOAuthDraftScopePolicy::issuanceEnabled(), 403);
        abort_unless($request->isJson(), 415);
        $rpc = $request->json()->all();
        if (array_is_list($rpc) || ($rpc['jsonrpc'] ?? null) !== '2.0'
            || ! is_string($rpc['method'] ?? null)
            || (isset($rpc['id']) && ! is_string($rpc['id']) && ! is_int($rpc['id']))) {
            return $this->error(null, -32600, 'Invalid JSON-RPC request.');
        }
        $id = $rpc['id'] ?? null;
        // Discovery and lookup require the same current token and audience
        // checks as mutations, including locked authorization records.
        $upload = new McpOAuthDraftCoverUpload;
        $assessmentUpload = new McpOAuthAssessmentCoverUpload;
        try {
            return McpOAuthDraftAuthorization::authorized($request, function (User $actor, array $scopes) use ($request, $rpc, $id, $upload, $assessmentUpload): JsonResponse {
            $method = $rpc['method'];
            if ($method === 'notifications/initialized') {
                return response()->json([], 202)->header('Cache-Control', 'no-store');
            }
            if ($id === null) {
                return $this->error(null, -32600, 'Request ID required.');
            }
            if ($method === 'initialize') {
                return $this->success($id, [
                    'protocolVersion' => '2025-11-25',
                    'capabilities' => ['tools' => ['listChanged' => false]],
                    'serverInfo' => ['name' => 'learncambodia-oauth-draft', 'version' => '0.1.0'],
                    'instructions' => 'Public catalog READ and own unpublished draft management; native curriculum requires separate consent. Publishing and deletion are not permitted.',
                ]);
            }
            if ($method === 'ping') {
                return $this->success($id, (object) []);
            }
            $catalog = self::catalog();
            if ($method === 'tools/list') {
                return $this->success($id, ['tools' => [...McpOAuthReadOnlyController::publicCatalog(), ...$catalog]]);
            }
            if ($method !== 'tools/call') {
                return $this->error($id, -32601, 'Method unavailable.');
            }
            $params = $rpc['params'] ?? null;
            $name = is_array($params) ? ($params['name'] ?? null) : null;
            $args = is_array($params) ? ($params['arguments'] ?? null) : null;
            // Public catalog calls use the existing READ-only implementation
            // only after full WRITE token authorization. Never dispatch the
            // legacy write engine, even when its tool name is supplied.
            if (is_string($name) && in_array($name, array_column(McpOAuthReadOnlyController::publicCatalog(), 'name'), true)) {
                return app(McpOAuthReadOnlyController::class)->handlePublicRead($request);
            }
            $tool = is_string($name) ? collect($catalog)->firstWhere('name', $name) : null;
            if ($tool === null || ! is_array($args) || array_is_list($args)
                || array_diff(array_keys($args), $tool['inputSchema']['required'])
                || array_diff($tool['inputSchema']['required'], array_keys($args))) {
                return $this->rejection($actor, $id, 422, 'Invalid tool or arguments.');
            }
            try {
                if (McpOAuthAssessmentCoverUpload::supports($name)) {
                    if (!McpOAuthDraftScopePolicy::hasAssessmentMediaScopes($scopes)) {
                        return McpOAuthReadOnlyController::scopeChallenge($id, true, true,
                            in_array(McpOAuthDraftScopePolicy::EDIT_SCOPE, $scopes, true), true, true);
                    }
                    $result = $assessmentUpload->execute($actor, $args);
                    return $this->toolResult($id, $result + ['http_status' => 200], false);
                }
                if (McpOAuthStandaloneAssessment::supports($name)) {
                    if (!McpOAuthDraftScopePolicy::hasAssessmentScopes($scopes)) {
                        return McpOAuthReadOnlyController::scopeChallenge($id,
                            in_array(McpOAuthDraftScopePolicy::CURRICULUM_SCOPE, $scopes, true),
                            in_array(McpOAuthDraftScopePolicy::MEDIA_SCOPE, $scopes, true),
                            in_array(McpOAuthDraftScopePolicy::EDIT_SCOPE, $scopes, true), true);
                    }
                    $result = McpOAuthStandaloneAssessment::execute($actor, $name, $args);
                    return $this->toolResult($id, $result + ['http_status' => 200], false);
                }
                if (McpOAuthCurriculumEditor::supports($name)) {
                    if (!McpOAuthDraftScopePolicy::hasEditScopes($scopes)) {
                        return McpOAuthReadOnlyController::scopeChallenge($id, true, in_array(McpOAuthDraftScopePolicy::MEDIA_SCOPE, $scopes, true), true, in_array(McpOAuthDraftScopePolicy::ASSESSMENT_SCOPE, $scopes, true), in_array(McpOAuthDraftScopePolicy::ASSESSMENT_MEDIA_SCOPE, $scopes, true));
                    }
                    $result = McpOAuthCurriculumEditor::execute($actor, $name, $args);
                    return $this->toolResult($id, $result + ['http_status' => 200], false);
                }
                if (McpOAuthDraftCoverUpload::supports($name)) {
                    if (!McpOAuthDraftScopePolicy::hasMediaScopes($scopes)) {
                        return McpOAuthReadOnlyController::scopeChallenge($id, true, true, in_array(McpOAuthDraftScopePolicy::EDIT_SCOPE, $scopes, true), in_array(McpOAuthDraftScopePolicy::ASSESSMENT_SCOPE, $scopes, true));
                    }
                    $result = $upload->execute($actor, $args);
                    return $this->toolResult($id, $result + ['http_status' => 200], false);
                }
                if (McpOAuthNativeCourseBuilder::supports($name)) {
                    if (!McpOAuthDraftScopePolicy::hasCurriculumScopes($scopes)) {
                        return McpOAuthReadOnlyController::scopeChallenge($id, true, false, false, in_array(McpOAuthDraftScopePolicy::ASSESSMENT_SCOPE, $scopes, true));
                    }
                    $result = McpOAuthNativeCourseBuilder::execute($actor, $name, $args);
                    return $this->toolResult($id, $result + ['http_status' => 200], false);
                }
                [$action, $resource] = explode('_draft_', $name, 2);
                // A nested transaction ensures errors roll back partial
                // writes even when converted to a normal JSON-RPC result.
                $result = \Illuminate\Support\Facades\DB::transaction(function () use ($actor, $action, $resource, $args): array {
                    if ($action === 'get') {
                        Validator::make($args, ['id' => ['required', 'integer', 'min:1']])->validate();
                        return McpOAuthDraftWriter::inspect($actor, $resource, (int) $args['id']);
                    }
                    $requestId = Validator::make($args, ['request_id' => ['required', 'uuid']])->validate()['request_id'];
                    unset($args['request_id']);
                    return McpOAuthDraftWriter::execute($actor, strtoupper($action), $resource, $args, $requestId);
                });
                return $this->toolResult($id, $result + ['http_status' => 200], false);
            } catch (ValidationException $error) {
                $status = 422;
            } catch (ModelNotFoundException $error) {
                $status = 404;
            } catch (HttpExceptionInterface $error) {
                $status = $error->getStatusCode();
            } catch (Throwable $error) {
                // Do not emit raw exceptions or content in tool responses.
                $status = 500;
            }
            return $this->rejection($actor, $id, $status, 'Draft operation rejected. Verify ownership, draft status, fields and version.');
            });
        } finally {
            // Storage is outside the database transaction. Reconcile only
            // objects created by this request after the outer transaction ends.
            $upload->cleanupUncommitted();
            $assessmentUpload->cleanupUncommitted();
        }
    }

    private function rejection(User $actor, mixed $id, int $status, string $message): JsonResponse
    {
        $receipt = (string) Str::uuid();
        McpActionAudit::create([
            'id' => $receipt, 'user_id' => $actor->id, 'token_id' => null,
            'tool' => 'oauth_draft_transport', 'operation' => 'DRAFT',
            'resource' => 'drafts', 'resource_id' => null,
            'result' => 'failed', 'http_status' => $status,
            'summary' => 'Draft tool request rejected; no payload retained.',
        ]);
        return $this->toolResult($id, [
            'status' => 'rejected', 'http_status' => $status,
            'receipt_id' => $receipt, 'message' => $message,
        ], true);
    }

    private function success(mixed $id, mixed $result): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result])
            ->header('Cache-Control', 'no-store');
    }

    private function error(mixed $id, int $code, string $message): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]])
            ->header('Cache-Control', 'no-store');
    }

    private function toolResult(mixed $id, array $data, bool $error): JsonResponse
    {
        return $this->success($id, [
            'content' => [['type' => 'text', 'text' => json_encode($data, JSON_THROW_ON_ERROR)]],
            'structuredContent' => $data, 'isError' => $error,
        ]);
    }
}
