# Learn Cambodia — Role-based workspaces

## Current routes

| Role | Home | Account purpose |
|---|---|---|
| admin | /dashboard | School operational overview and curriculum management |
| student | /account | Personal learning catalogue and proficiency reference |
| developer | /developer | Read-only catalogue APIs and scoped tokens |

All three use `resources/views/layouts/workspace.blade.php`.
Menu definitions are isolated in `config/workspaces.php`. Links without
an approved backend implementation show an explicit **Not yet activated**
page; they do not create data or call third-party services.

## Functional module boundaries

**Admin:** category list, create and edit; published/unpublished state;
read-only user, course and CEFR lists, profile and password updates.

**Student:** published learning categories, CEFR descriptors, account profile
and password settings. Formal placement tests, enrollment and ticketing are
not enabled.

**Developer:** token creation, listing, revocation; read-only
`GET /api/v1/categories`, `GET /api/v1/cefr-levels`, and
`GET /api/v1/courses`; API reference and policy.
No account, payment, write, student assessment or private data APIs exist.

**Planned only:** advertising providers, appearance CRUD, content publishing,
real-time support, policy workflows, user creation, payment configuration,
storage providers, social sign-in, exams and formal test issuance. A menu link
alone does not establish a working module.

## Identity and least privilege

- Self-registration creates only `student` accounts. Never expose role
  selection through registration, profile or public API payloads.
- Workspace permissions are checked via `EnsureRole` on **every request**.
  `admin`, `student` and `developer` cannot access one another's pages.
- Operators may grant a privileged role only from the trusted server console.
  The command requires an explicit confirmation, operator identifier and
  documented reason, writes an append-only change record, and revokes tokens.
- Privileged roles are **not** seeded or created by installation. Operators
  must verify the identity and approval of recipients before changes.

Example **for an approved, existing, disposable QA account only**:

```bash
php artisan accounts:assign-role qa-admin@example.test admin \
  --operator="Approved QA Operator" \
  --reason="Approved isolated QA role-assignment acceptance" \
  --confirm
```

Do not use the example email in a real environment.

## Developer token security

Laravel Sanctum personal tokens are persisted as hashes. Each token:
- Has `catalog:read` scope only; no wildcard abilities.
- Expires within 1–30 days of issue.
- Requires a password check before creation.
- Is limited to 10 simultaneously active tokens per developer.
- Can be individually revoked by its owner.
- Is invalid if its owner is no longer `developer`; role changes
  immediately revoke all previously issued tokens.
- Is revealed once in the browser with no-store response headers.
- Uses per-request rate limiting and bounded pagination.

Current implementation is deliberately **not OAuth**; adding external
OAuth clients, application credentials, webhooks or third-party callbacks
needs a separate security review.

## Production-release gates

1. Independent technical review of role assignment, incident recovery,
   audit retention, and password/session management.
2. Security acceptance: role isolation, token expiry, cross-account token
   deletion, CSRF, rate limiting, replay and cross-origin behavior.
3. Browser acceptance: desktop/mobile, keyboard, accessibility,
   search/filter behavior, collapsible groups and dropdowns.
4. Legal approval of Privacy and Terms, and protection of student records.
5. aaPanel infrastructure verification, backup/restore, reproducible builds,
   production monitoring and rollback.
6. Merge foundation PR #1 first; PR #2 is stacked on its feature branch.

**Neither PR is merged or deployed to production.**
