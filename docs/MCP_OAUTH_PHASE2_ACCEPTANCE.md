# LearnCambodia MCP — Phase 2 OAuth 2.1 integration gates

**Status:** Phase 2a staged only. No OAuth authorization server, token issuance, client registration, refresh token, or ChatGPT connection is active.

## Phase 1 acceptance already verified (2026-11-24)

- Browser-issued, scoped Sanctum bearer token works on `POST /api/mcp`.
- HTTP 401 for anonymous and invalid bearer requests.
- `initialize`, `tools/list` (three tools), READ courses (25), READ analytics (8), audit receipts, and no content mutations passed production acceptance.
- Keep the working `/api/mcp`, current Sanctum tokens, existing MCP browser consent and scope gates intact.

## Architecture decision

The application currently has `laravel/sanctum` and Fortify, **not** Laravel Passport. Laravel Sanctum is not an OAuth authorization server. Use a reviewed install of **Laravel Passport 13** and, if appropriate, Laravel MCP as the standards-based OAuth layer. Do not improvise a home-built token/code/refresh implementation.

Do not replace `User::HasApiTokens` blindly: it is currently Sanctum's trait and the direct MCP bridge depends on Sanctum `PersonalAccessToken`, token names, abilities and revocation. Stage and test a coexistence strategy for new Passport access tokens and existing Sanctum tokens. Accepting Passport access tokens in the existing controller requires a **separate, explicit** authorization/guard design; the current `requireSession()` demands a Sanctum `PersonalAccessToken` and will reject Passport tokens.

## Phase 2a: fail-closed metadata scaffold (this PR)

Two public GET routes are introduced, deliberately returning **404** until all these gates hold:

1. `MCP_GATEWAY_ENABLED=true` (existing).
2. `MCP_OAUTH_DISCOVERY_ENABLED=true` (new flag, **defaults false**).
3. `mcp.oauth_ready=true` (hard-coded **false** until full integration accepted).
4. `mcp.oauth_provider_accepted=true` (hard-coded **false** until full integration accepted).
5. Named `mcp.oauth.authorize` and `mcp.oauth.token` routes exist.

Metadata is not a substitute for implementing OAuth. Do not flip flags to force a green discovery endpoint.

Upon full provider acceptance, the intended public resources are:

- `GET https://learncambodia.com/.well-known/oauth-protected-resource`
- `GET https://learncambodia.com/.well-known/oauth-authorization-server`
- Canonical protected resource: `https://learncambodia.com/api/mcp`
- Canonical issuer: `https://learncambodia.com`
- Initial minimal scope: `mcp:read`, **not** WRITE/DELETE/PUBLISH/MANAGE.
- Authorization response: authorization code + PKCE S256 with RFC 8707 `resource` parameter. Token requests must send the same `resource`.

The metadata contains no passwords, bearer tokens, private keys, client secrets, personal data, or draft write scopes. Published URLs are pinned to the canonical domain rather than reflecting untrusted Host headers.

## Phase 2b: implement OAuth provider in isolated staging — mandatory before activation

1. Review `composer.lock`, PHP 8.5 compatibility, Laravel Passport 13 and Laravel MCP package versions in a dedicated, **isolated** QA checkout. Do not use `artisan install:api --passport` against production without reviewing its migrations, user model/guard modifications, keys, defaults, and effects on Sanctum.
2. Add Passport OAuth schema through safe forward-only migrations. Add private signing keys with restricted permissions and protected backup/recovery; never commit private keys or put them in ChatGPT.
3. Add the new `mcp.oauth.authorize` and `mcp.oauth.token` routes under a fully tested OAuth authorization server, and enforce an HTTPS issuer and exact canonical resource. Reject mismatched `resource`/audience at both token issuance and resource access.
4. Implement a **browser-only** administrator consent screen with CSRF protection, login/2FA, fresh password confirmation, explicit requested scope and client identity, and deny/cancel support. Only the account holder can grant access; all unauthorized roles fail closed.
5. Use authorization-code + mandatory S256 PKCE, one-use short-lived codes, state handled by the client, exact allowlisted redirect URI, secure refresh-token rotation/revocation if supported, access-token expiry and binding. Never accept implicit grant, password grant, wildcard redirects, or bearer tokens in query strings.
6. Choose and verify client identity: prefer client ID metadata documents (CIMD) when supported and safely validated; otherwise approve an explicit pre-registered ChatGPT client. Do **not** enable unauthenticated DCR by default. If DCR is required, implement validation, rate limits, anti-SSRF protections and quotas before advertising `registration_endpoint`.
7. Validate **ChatGPT's actual redirect URL from its connector setup page**, not an invented callback ID. Advertise `authorization_response_iss_parameter_supported=true` only if the server returns `iss` on every success and error redirect.
8. Make the new OAuth resource guard independent and explicitly verify issuer, token signature/introspection, resource audience, expiry, scopes, account role and revocation. Never automatically treat a generic Passport or ordinary Sanctum developer token as MCP access.
9. Implement `WWW-Authenticate: Bearer resource_metadata="https://learncambodia.com/.well-known/oauth-protected-resource", scope="mcp:read"` on unauthenticated OAuth MCP requests **only after metadata is genuinely functional**. Handle insufficient scopes without escalating permissions.
10. Keep `MCP_REMOTE_WRITES_ENABLED=false` and explicitly disallow `mcp:write`, `mcp:delete`, `mcp:publish`, `mcp:manage`, uploads and deployments in OAuth Phase 2 acceptance.

## Phase 2c: isolated automated and live acceptance

- RFC 9728 protected-resource metadata available from canonical HTTPS URL; issuer matches authorization server metadata exactly.
- Invalid/unregistered client, arbitrary URL-form client ID, unapproved redirect URI and bad `resource` denied.
- Authorization requests without S256 PKCE, reuse of codes, mismatched `code_verifier`, replayed/expired tokens and wrong audience denied.
- Browser consent cannot be skipped, escalated or CSRF-forged; account roles enforced; email/2FA/password checks covered.
- Refresh rotation and logout/revocation tested (if refresh enabled); revoke all tokens for a compromised account.
- Tokens scoped `mcp:read` can list tools and read courses and analytics but cannot read sensitive users/settings or perform mutations.
- Original manual-token Phase 1 acceptance **must remain green** before and after this additive release.
- `php artisan route:list`, PHPUnit and browser UI testing in isolated QA, followed by guarded staged deploy with backups and rollback.
- When the **entire OAuth provider** passes, change `oauth_ready` and `oauth_provider_accepted` only via a separately reviewed release and run fresh live OAuth conformance tests. No auto-release from this scaffold.
- Connect through ChatGPT's own plugin/MCP management UI and scan tools using the verified callback client and scopes.

## Operator checks for this PR (no secrets)

```bash
cd /www/wwwroot/learncambodia.com
/usr/bin/php artisan route:list --path=.well-known
```

**Do not deploy Phase 2a straight to production** until the CI suite, isolated source drift check, and release gate are reviewed. Phase 2a is source preparation, **not** an OAuth-enabled connection.

## Sources / standards

- OpenAI MCP authentication: https://developers.openai.com/plugins/build/auth
- MCP authorization spec: https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization
- Laravel 13 MCP OAuth docs: https://laravel.com/docs/13.x/mcp#oauth-21
- Laravel 13 Passport: https://laravel.com/docs/13.x/passport
- RFC 9728 Protected Resource Metadata: https://www.rfc-editor.org/rfc/rfc9728
- RFC 8707 Resource Indicators: https://www.rfc-editor.org/rfc/rfc8707
- RFC 9207 Issuer Identification: https://www.rfc-editor.org/rfc/rfc9207
