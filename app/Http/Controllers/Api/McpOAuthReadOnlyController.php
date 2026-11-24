<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\McpActionAudit;
use App\Support\McpCrudEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
                'instructions' => 'OAuth resource permits READ of published courses and aggregate analytics only.',
            ]);
        }
        if ($method === 'ping') {
            return $this->success($id, (object) []);
        }
        if ($method === 'tools/list') {
            return $this->success($id, ['tools' => [self::readTool()]]);
        }
        if ($method !== 'tools/call') {
            return $this->rpcError($id, -32601, 'Method unavailable on OAuth READ resource.');
        }

        $params = $rpc['params'] ?? null;
        $args = is_array($params) ? ($params['arguments'] ?? null) : null;
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
            $data = McpCrudEngine::execute($request, $args);
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

    private static function readTool(): array
    {
        return [
            'name' => 'crud_manage_platform',
            'description' => 'Read LearnCambodia public courses or aggregated education metrics. No writes.',
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
