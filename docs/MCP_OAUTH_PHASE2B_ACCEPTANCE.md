# LearnCambodia MCP — Phase 2B Passport OAuth: staging implementation and release gates

**2026-11-24 status: DRAFT / NOT DEPLOYED / NOT APPROVED.**

Phase 1 production READ-only Sanctum bearer MCP and Phase 2A inactive OAuth metadata are accepted and deployed. This branch adds an isolated Passport 13 OAuth implementation. It is **NOT** ready for aaPanel deployment or ChatGPT account connection unless *all* gates below pass. Never run Composer update or Passport key generation on production from this draft.

## Core isolation / intended flow

- Main \App\Models\User and \POST /api/mcp remain Sanctum. Do not replace \Laravel\Sanctum\HasApiTokens.
- \App\Models\McpOAuthUser uses Passport's \HasApiTokens on the **same** \users table, separate \mcp_oauth guard/provider and endpoint \POST /api/mcp/oauth. This prevents PHP trait collision and wrong-token acceptance.
- Passport creates and verifies authorization codes, PKCE S256, access JWTs, and refresh tokens. Original login, passkeys and 2FA continue through the existing website web guard.
- A logged-in verified Website Admin must re-confirm the account password and approve explicit \mcp:read consent in the website browser. Repeated authorization still requires browser consent. Students and unverified accounts cannot authorize.
- OAuth preflight enforces exact public client ID, redirect, resource indicator and S256; only a reviewed public third-party client is pre-registered. No dynamic client registration endpoint.
- New API offers *only* \initialize, \ping, \tools/list (one READ-only tool), \tools/call for \READ courses and \READ aggregate analytics. All management/write/upload/publish/delete requests are rejected.
- Server-issued access-token IDs are mapped to exactly one protected resource in \mcp_oauth_token_audiences; the resource middleware also checks signature through Passport's token guard, active unrevoked token, client, user, scope, expiry, and audience record.
- Browser revocation disables Passport access and associated refresh credentials and deletes the audience record; existing Sanctum tokens remain untouched.

## Release flags / fail closed

Existing \config/mcp.php has \oauth_ready=false and \oauth_provider_accepted=false as hard-coded gates. Phase 2B additionally has \MCP_OAUTH_PROVIDER_STAGING_ENABLED=false by default and requires a non-empty exact \MCP_OAUTH_CLIENT_ID / \MCP_OAUTH_REDIRECT_URI. *None* are enabled on production.

No Passport auto routes: \Passport::ignoreRoutes() suppresses the package defaults, preventing generic OAuth and client management routes.

Metadata must not return OAuth-ready responses until the full provider passes acceptance and a separate, signed release changes BOTH hard-off gates. \MCP_REMOTE_WRITES_ENABLED is not changed by this work.

## Security and standards gaps to close before acceptance

1. **Lock dependencies.** Commit the deterministic \composer.lock after resolving Passport 13 on a clean PHP 8.5 runner. Confirm platform/library minimums and run composer validate / audit. Do not run live \composer update.
2. **Backups/migrations.** Inspect existing \oauth_* tables for conflicts. Passport vendor schema is copied under \database/migrations, and \mcp_oauth_token_audiences migration is additive. Dry-run on isolated MySQL seeded with representative app metadata before live DB migration. Validate rollback, lock / FK strategy and auth keys.
3. **Signing keys.** Generate keys only in isolated QA or a reviewed production release; secure root or site owner permissions (0600). Never print or commit private keys. Back up and document rotation/recovery, including consequences for existing tokens.
4. **Exact callback.** Obtain the real ChatGPT redirect URI from *its UI* for the actual connector registration and pin it. Do not rely on the test fixture callback as production identity.
5. **Issuer/resource binding.** Evaluate standards conformance for JWT \aud\ and \iss\ claims, resource indicators in both authorization and token requests, resource-bound refresh lifecycle and nonstandard token reuse. An app-level DB resource binding is not a substitute for universally conformant JWT audience semantics. A security review must explicitly accept or modify this architecture before enabling metadata.
6. **Client identity.** Validate optional client metadata document (CIMD) or pre-registered public client; do not advertise DCR or wildcard client ids. Validate exact redirect and prevent remote URLs / SSRF.
7. **Consent protocol.** Verify Passport approve/deny session handling, CSRF, strict \state, login/2FA flows, password confirmation and no consent bypass or silent approval. Ensure Auth Code single use and short TTL.
8. **Token lifecycle.** Test wrong verifier, wrong resource, wrong client, expired/replayed codes, expired/revoked access tokens, refresh rotation + replay detection, logout/revoke-all across trusted clients and race conditions.
9. **MCP protocol.** Check Authorization \WWW-Authenticate\ \resource_metadata\ challenge and RFC 9728 discovery only when ready, plus exact CORS Origin/Host and content-type policy. Do not loosen the legacy \POST /api/mcp\ requirements.
10. **Production route divergence.** The live \routes/web.php\ already contains many newer routes, seen in Phase2A diagnostics. Future release MUST apply a reviewed additive overlay to the live exact hash with source backup and full route-registry comparison. No force-overwrite, no \git reset --hard\.
11. **Final live acceptance.** Re-run Phase 1 anonymous-denial, initialize, course READ, analytics READ and zero mutation acceptance; then Phase2B staging web login→consent→code→PKCE→READ, rotate and revoke; compare before/after DB data and audit receipts. Only then promote to production.

## Developer CI

\`\`\`sh
composer update laravel/passport --with-all-dependencies --no-interaction --no-scripts
php artisan package:discover
php artisan passport:keys --force --no-interaction # isolated CI ONLY
php artisan test --filter=McpOAuthPassportTest
php artisan test --filter=McpOAuthDiscoveryTest
php artisan test --filter=McpBrowserBridgeTest
\`\`\`

The GitHub Actions workflow runs this in temporary GitHub-runner containers, not the user's aaPanel.

## References

- Laravel 13 Passport: https://laravel.com/docs/13.x/passport
- Laravel 13 MCP: https://laravel.com/docs/13.x/mcp
- MCP authorization spec: https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization
- RFC 8707 resource indicators: https://www.rfc-editor.org/rfc/rfc8707
- RFC 9728 resource metadata: https://www.rfc-editor.org/rfc/rfc9728
- OpenAI ChatGPT plugins OAuth: https://developers.openai.com/plugins/build/auth
