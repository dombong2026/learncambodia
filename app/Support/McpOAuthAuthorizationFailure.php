<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Fixed, non-sensitive browser errors for the MCP authorization endpoint. */
final class McpOAuthAuthorizationFailure
{
    public static function respond(string $reason, int $status): Response
    {
        $messages = [
            'client_resource' => 'The registered application or resource does not match this request.',
            'public_client' => 'This connection requires a public OAuth client with PKCE.',
            'client_registration' => 'The registered application is unavailable or not eligible.',
            'callback_registration' => 'The registered callback configuration is not valid.',
            'response_type' => 'An authorization code request is required.',
            'callback_request' => 'The callback does not match the registered application.',
            'pkce' => 'The secure authorization challenge is missing or invalid.',
            'state' => 'The authorization session is missing or invalid.',
            'scope_policy' => 'The requested permissions are unavailable or not an allowed combination.',
            'administrator_session' => 'Sign in to LearnCambodia using the administrator selected for MCP access, with a verified email and confirmed two-factor authentication. Then start a fresh authorization from ChatGPT.',
            'passport_validation' => 'The OAuth engine rejected this request. Start a fresh authorization only after the administrator has checked the reference below.',
        ];

        if (!array_key_exists($reason, $messages)) {
            $reason = 'passport_validation';
        }

        // Never log the URL, query string, state, client ID, account ID,
        // challenge, authorization code, token or exception content.
        Log::warning('LearnCambodia MCP authorization rejected', [
            'reason' => $reason,
            'status' => $status,
        ]);

        return response()->view('workspace.admin.mcp-oauth-error', [
            'reason' => $reason,
            'message' => $messages[$reason],
        ], $status, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            'X-LearnCambodia-OAuth-Error' => $reason,
        ]);
    }
}
