<?php

namespace App\Support;

use App\Models\McpActionApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class McpActionConsent
{
    public static function hash(string $operation, string $resource, string $id, array $payload = []): string
    {
        // Key ordering is fixed at the outer envelope; payload must use the
        // exact same data on request and execution.
        return hash('sha256', json_encode([
            'operation'=>$operation, 'resource'=>$resource,
            'resource_id'=>$id, 'payload'=>$payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public static function request(Request $request, string $operation, string $resource, string $id, array $payload = []): McpActionApproval
    {
        abort_unless(in_array($operation, ['DELETE', 'BULK_UPDATE'], true), 422);
        abort_unless(in_array($resource, ['courses','posts','pages','policies','help_articles'], true), 422);
        abort_unless(ctype_digit($id) && (int)$id>0, 422);
        $actor=$request->user();
        abort_unless($actor?->isAdministrator(), 403);
        abort_unless($actor->hasEnabledTwoFactorAuthentication(),403,
            'A confirmed second factor is required for destructive MCP requests.');
        $token=$actor->currentAccessToken();
        abort_unless($token instanceof \Laravel\Sanctum\PersonalAccessToken, 401);

        return McpActionApproval::create([
            'id'=>(string) Str::uuid(),
            'user_id'=>$actor->id,
            'token_id'=>$token->id,
            'operation'=>$operation,
            'resource'=>$resource,
            'resource_id'=>$id,
            'payload_hash'=>self::hash($operation,$resource,$id,$payload),
            'status'=>'pending',
            'expires_at'=>now()->addMinutes(10),
        ]);
    }

    /** Must be invoked within the same DB transaction as the destructive mutation. */
    public static function consume(Request $request, string $approvalId, string $operation, string $resource, string $id, array $payload = []): void
    {
        $token=$request->user()?->currentAccessToken();
        abort_unless($token instanceof \Laravel\Sanctum\PersonalAccessToken, 401);
        $approval=McpActionApproval::query()->lockForUpdate()->findOrFail($approvalId);
        abort_unless($approval->status==='approved'
            && $approval->approved_at !== null
            && $approval->consumed_at === null
            && $approval->expires_at->isFuture()
            && (int)$approval->user_id === (int)$request->user()->id
            && (int)$approval->token_id === (int)$token->id
            && $approval->operation === $operation
            && $approval->resource === $resource
            && hash_equals($approval->resource_id,$id)
            && hash_equals($approval->payload_hash,self::hash($operation,$resource,$id,$payload)), 403,
            'Missing, expired or mismatched browser confirmation.');

        $approval->update(['status'=>'consumed','consumed_at'=>now()]);
    }
}
