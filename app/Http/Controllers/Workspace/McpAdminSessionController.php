<?php

namespace App\Http\Controllers\Workspace;

use App\Models\McpActionAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class McpAdminSessionController
{
    private const LABEL_PREFIX = 'LearnCambodia MCP: ';
    public const SCOPES = [
        'mcp:read', 'mcp:write', 'mcp:upload',
        'mcp:publish', 'mcp:delete', 'mcp:manage',
    ];

    /**
     * Website-admin browser session is the ONLY authority allowed to issue
     * these short-lived, narrow-scoped bridge tokens. This is NOT an OAuth
     * authorization-code endpoint or an external ChatGPT OAuth callback.
     */
    public function issue(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdministrator(), 403);
        $data = $request->validate([
            'client_name' => ['required', 'string', 'min:3', 'max:60'],
            'scopes' => ['required', 'array', 'min:1', 'max:6'],
            'scopes.*' => ['required', 'string', Rule::in(self::SCOPES), 'distinct'],
            'confirmation' => ['required', Rule::in(['CONNECT'])],
            'expires_in_minutes' => ['required', 'integer', Rule::in([15, 30, 60])],
        ]);
        // Do not enable a broad write-capable bearer token for an admin
        // account that has never enrolled an authentication second factor.
        if (array_diff($data['scopes'], ['mcp:read']) !== []
            && !$user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages([
                'scopes' => 'Enable and confirm two-factor authentication before granting write, upload or management permissions.',
            ]);
        }

        $active = $user->tokens()
            ->where('name', 'like', self::LABEL_PREFIX.'%')
            ->where('expires_at', '>', now())->count();
        if ($active >= 5) {
            throw ValidationException::withMessages([
                'client_name' => 'At most five active MCP browser sessions are permitted. Revoke an existing session.',
            ]);
        }

        $name = self::LABEL_PREFIX.Str::limit(strip_tags($data['client_name']), 60, '');
        $expiry = now()->addMinutes((int) $data['expires_in_minutes']);
        // mcp:use marks these tokens as MCP-specific. Existing developer
        // catalog:read tokens cannot call MCP CRUD endpoints.
        [$issued,$receipt]=DB::transaction(function() use($user,$name,$data,$expiry){
            $newToken=$user->createToken($name,['mcp:use',...$data['scopes']],$expiry);
            $auditId=(string)Str::uuid();
            McpActionAudit::create([
                'id'=>$auditId,'user_id'=>$user->id,'token_id'=>$newToken->accessToken->id,
                'tool'=>'browser_admin_authorization','operation'=>'CREATE','resource'=>'mcp_session',
                'resource_id'=>(string)$newToken->accessToken->id,'result'=>'success','http_status'=>201,
                'summary'=>'Website-admin browser issued a scoped, short-lived MCP bearer session.',
            ]);
            return [$newToken,$auditId];
        });

        return response()->json([
            'status' => 'issued',
            'session_id' => $issued->accessToken->id,
            'receipt_id' => $receipt,
            'access_token' => $issued->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiry->toIso8601String(),
            'scopes' => $data['scopes'],
            'message' => 'Token displayed once. Store it securely. This is a short-lived direct bearer credential, not browser OAuth.',
        ], 201)->header('Cache-Control', 'no-store');
    }

    public function revoke(Request $request, int $token): JsonResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $credential = $request->user()->tokens()
            ->where('name', 'like', self::LABEL_PREFIX.'%')
            ->findOrFail($token);
        $receipt=DB::transaction(function()use($credential,$request,$token){
            $credential->delete();
            $auditId=(string)Str::uuid();
            McpActionAudit::create([
                'id'=>$auditId,'user_id'=>$request->user()->id,'token_id'=>$token,
                'tool'=>'browser_admin_revoke','operation'=>'DELETE','resource'=>'mcp_session',
                'resource_id'=>(string)$token,'result'=>'success','http_status'=>200,
                'summary'=>'Website administrator revoked an MCP session.',
            ]);
            return $auditId;
        });

        return response()->json([
            'status' => 'revoked',
            'session_id' => $token,
            'receipt_id' => $receipt,
        ])->header('Cache-Control', 'no-store');
    }
}
