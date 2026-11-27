<?php

namespace App\Http\Controllers\Api;

use App\Support\McpOAuthAuthorizationFailure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Exceptions\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Passport\Client;
use Laravel\Passport\Http\Controllers\AuthorizationController;

/**
 * Always show administrator consent. Passport normally reuses an earlier
 * permission grant silently, but this MCP client must not auto-approve it.
 */
final class McpOAuthAuthorizationController extends AuthorizationController
{
    public function authorize(
        ServerRequestInterface $psrRequest,
        Request $request,
        ResponseInterface $psrResponse,
        AuthorizationViewResponse $viewResponse
    ): Response|AuthorizationViewResponse {
        try {
            return parent::authorize($psrRequest, $request, $psrResponse, $viewResponse);
        } catch (OAuthServerException $error) {
            // Preserve protocol redirects (including prompt=none errors).
            // Do not expose raw exception messages or query values.
            if ($error->getResponse()->getStatusCode() === 400) {
                return McpOAuthAuthorizationFailure::respond('passport_validation', 400);
            }
            throw $error;
        }
    }

    protected function hasGrantedScopes(Authenticatable $user, Client $client, array $scopes): bool
    {
        return false;
    }
}
