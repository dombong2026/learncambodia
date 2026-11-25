<?php

namespace App\Console\Commands;

use App\Models\McpOAuthUser;
use App\Http\Middleware\EnsureMcpOAuthStagingEnabled;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * Explicit operator-only pre-registration; no open DCR endpoint.
 * Copy the exact redirect from ChatGPT's connector settings. No placeholder.
 */
final class RegisterMcpOAuthClient extends Command
{
    protected $signature = 'mcp:oauth-register-chatgpt
        {owner_id : Verified LearnCambodia administrator numeric ID}
        {redirect_uri : Exact ChatGPT OAuth redirect URI}
        {--production : Explicitly request closed production registration}
        {--confirm : Create only after human review}';

    protected $description = 'Pre-register a public PKCE ChatGPT OAuth client while production is closed';

    public function handle(ClientRepository $repository): int
    {
        $production = app()->environment('production');
        if ($production) {
            if (! $this->option('production') || ! EnsureMcpOAuthStagingEnabled::productionRegistrationAllowed()) {
                $this->error('PRODUCTION_REGISTRATION_REQUIRES_EXPLICIT_CLOSED_GATE');
                return self::FAILURE;
            }
        } elseif ($this->option('production') || ! app()->environment(['local', 'testing', 'staging'])
            || ! config('mcp.oauth_provider_staging_enabled', false)) {
            $this->error('STAGING_ONLY_OAUTH_CLIENT_REGISTRATION');
            return self::FAILURE;
        }

        $redirect = (string) $this->argument('redirect_uri');
        $validRedirect = $redirect === 'https://chatgpt.com/connector_platform_oauth_redirect'
            || (bool) preg_match('~\Ahttps://chatgpt\.com/connector/oauth/[A-Za-z0-9_-]{8,128}\z~D', $redirect);
        if (! $validRedirect || ($production && ! EnsureMcpOAuthStagingEnabled::productionRedirectIsValid($redirect))) {
            $this->error('CHATGPT_EXACT_CALLBACK_REQUIRED');
            return self::FAILURE;
        }

        $id = (string) $this->argument('owner_id');
        $owner = ctype_digit($id) ? McpOAuthUser::query()->find((int) $id) : null;
        if (! $owner || ! $owner->isAdministrator() || ! $owner->hasVerifiedEmail()) {
            $this->error('VERIFIED_ADMIN_OWNER_REQUIRED');
            return self::FAILURE;
        }

        $name = 'LearnCambodia ChatGPT MCP (READ)';
        $existing = Passport::client()->newQuery()->where('name', $name)->first();
        if ($existing) {
            $this->error('CLIENT_ALREADY_EXISTS_REVIEW_AND_REVOKE_OLD_CLIENT');
            return self::FAILURE;
        }
        if (! $this->option('confirm')) {
            $this->line('DRY_RUN=PASS');
            $this->line('OAuth client type: public authorization_code + refresh_token, PKCE S256');
            $this->line('Allowed redirect: '.$redirect);
            $this->line('Allowed resource: https://learncambodia.com/api/mcp/oauth');
            return self::SUCCESS;
        }

        $lock = Cache::lock('mcp-oauth-register-chatgpt', 60);
        if (! $lock->get()) {
            $this->error('CLIENT_REGISTRATION_BUSY');
            return self::FAILURE;
        }
        try {
            if (Passport::client()->newQuery()->where('name', $name)->exists()) {
                $this->error('CLIENT_ALREADY_EXISTS_REVIEW_AND_REVOKE_OLD_CLIENT');
                return self::FAILURE;
            }
            $client = $repository->createAuthorizationCodeGrantClient(
            name: $name,
            redirectUris: [$redirect],
            confidential: false,
                user: $owner,
            );
        } finally {
            $lock->release();
        }
        $this->line('MCP_OAUTH_PUBLIC_CLIENT_REGISTERED=PASS');
        $this->line('MCP_OAUTH_CLIENT_ID='.$client->getKey());
        $this->line('MCP_OAUTH_CLIENT_SECRET=NONE');
        $this->warn('Configure the client ID and exact redirect in the intended environment; close the registration gate before any activation.');
        return self::SUCCESS;
    }
}
