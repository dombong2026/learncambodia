# MCP OAuth draft WRITE production activation

This release defines a closed-by-default production permission boundary. Installing it does not grant WRITE permissions.

## Activation contract

The existing production OAuth gates and exact registered public client/resource/callback must remain accepted. In addition, all of the following must be explicitly configured by an operator after CI and production preflight:

- MCP_OAUTH_DRAFT_WRITES_ACCEPTED=true
- MCP_OAUTH_DRAFT_WRITES_ENABLED=true
- MCP_OAUTH_DRAFT_SCOPE_ISSUANCE_ENABLED=true
- MCP_OAUTH_DRAFT_PRODUCTION_ACCEPTED=true
- MCP_OAUTH_DRAFT_PRODUCTION_ADMIN_ID=<explicitly selected administrator's numeric ID>

Do not select the administrator automatically. Verify current administrator role, verified email and confirmed two-factor authentication. No legacy Sanctum write setting can enable this OAuth feature.

When disabled, metadata and authorization expose only mcp:read. Existing READ tokens continue to authorize ten READ tools. When every draft gate is open, a READ token belonging to the selected, currently eligible administrator can additionally discover nine static draft tool definitions, not draft data. All draft calls using that READ token return an HTTP 403 insufficient_scope challenge with the canonical resource metadata URL and both required scopes, plus the OpenAI tool error metadata. Clients may request fresh explicit consent; scopes never change automatically. Ineligible administrators and closed gates retain ten tool definitions. Client capabilities and workspace restrictions still apply; metadata does not guarantee ChatGPT exposes WRITE actions. The initial unauthenticated challenge remains READ-only. Enabling the draft gates permits a new authorization-code grant for the selected administrator with exactly mcp:read and mcp:write, explicit browser consent and PKCE S256. A READ-only grant cannot authorize draft tools. Both token issuance and draft requests enforce the current selected administrator and role/2FA status. Authorized WRITE discovery exposes nineteen tools, including six draft mutations and three owned-draft lookups. There is no publishing, deletion, upload or user-management tool.

## Closing and reopening

Closing any draft gate denies WRITE calls and WRITE token issuance, while retaining READ access. This is a kill switch, not a blanket credential revocation: already issued WRITE credentials may remain recorded in the database. Before reopening or changing the selected administrator, revoke previous WRITE access and refresh tokens and invalidate pending WRITE authorization codes for the registered client using a separately reviewed operator procedure; verify no previous WRITE grant remains usable. Do not revoke existing READ credentials. Keep issuance closed until that verification passes. Require fresh browser consent on reauthorization. Never copy access tokens, refresh tokens, secrets or signing keys into logs or chat.

## Receipts and retention

The receipt table contains the operator ID, request hash and minimal operation result; not the request body. Retain receipts while the same request IDs can be retried. No automated expiry or deletion is authorized in this release, because deleting a receipt can make an old CREATE request executable again. The restrictive user foreign key prevents deleting users referenced by receipts. Account deletion and a final retention/anonymization policy require explicit approval and a separate migration/design, not an automatic cleanup or cascading delete. Backup evidence must be protected with restricted access.

## Production acceptance sequence

1. Accept all CI workflows on an exact pinned release.
2. Verify the live file baseline and existing migration history; back up changed files and configuration.
3. Deploy with all draft gates closed; confirm READ-only metadata, existing authenticated READ and site health.
4. Confirm the selected administrator and review receipt retention/account deletion implications.
5. Reconcile and revoke any previous WRITE grants before opening gates. Verify both READ and WRITE scope boundaries.
6. Reauthorize the registered client through the explicit consent flow. Test one approved unpublished draft, retry protection, lookup and versioned update; verify no publication and audit records.
7. Close the draft gates on any failed acceptance. Retain evidence and rollback code/configuration if required.

The production activation itself is not performed by this document or by the CI tests. The tests use isolated databases and ephemeral signing keys.
