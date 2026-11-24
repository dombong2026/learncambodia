<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Passport owns the OAuth authorization-code, PKCE, and refresh cryptography.
 * This adapter records a fixed resource audience for every issued access token,
 * including refresh responses, before returning the token to the client.
 */
final class McpOAuthAccessTokenController extends AccessTokenController
{
    public function issueToken(ServerRequestInterface $psrRequest, ResponseInterface $psrResponse): Response
    {
        $response = parent::issueToken($psrRequest, $psrResponse);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');

        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $json = json_decode((string) $response->getContent(), true);
        $accessToken = $json['access_token'] ?? null;
        if (! is_string($accessToken)) {
            abort(500, 'OAuth issuance response did not contain an access token.');
        }

        // jti is extracted solely from a token returned by Passport's local
        // signing server, not from an untrusted caller-provided JWT.
        $parts = explode('.', $accessToken);
        if (count($parts) !== 3) {
            abort(500, 'Unexpected Passport token format.');
        }
        $decoded = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = is_string($decoded) ? json_decode($decoded, true) : null;
        $id = is_array($claims) ? ($claims['jti'] ?? null) : null;
        if (! is_string($id) || $id === '' || strlen($id) > 128) {
            abort(500, 'Unable to bind OAuth token audience.');
        }

        $client = (string) config('mcp.oauth_client_id');
        $resource = (string) config('mcp.oauth_resource');
        $userId = DB::table('oauth_access_tokens')->where('id', $id)
            ->where('client_id', $client)->where('revoked', false)->value('user_id');

        if (! is_numeric($userId)) {
            abort(500, 'OAuth access token does not belong to a valid user.');
        }

        DB::table('mcp_oauth_token_audiences')->insert([
            'access_token_id' => $id,
            'user_id' => (int) $userId,
            'client_id' => $client,
            'resource' => $resource,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $response;
    }
}
