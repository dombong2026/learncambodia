# Learn Cambodia — Milestone 8: Public frontend, auth and one-click setup

**Repo:** `dombong2026/learncambodia`  
**Draft PR:** https://github.com/dombong2026/learncambodia/pull/8  
**Stack base:** draft PR #7, `feat/reference-navy-gold-dashboard-trilingual`.

## Frontend

- Real public courses directory: `/courses`, keyword/category/CEFR filters,
  pagination (10/page), published/current course details at `/courses/{slug}`.
  Unpublished/superseded courses return 404. Course outline exposes
  module names and counts, never private lesson bodies.
- Published editorial articles: `/articles` and `/articles/{slug}`, with
  10/page, searching, proper type-scoped slug lookup and escaped plain text.
  Drafts and rejected posts return 404.
- Homepage displays up to six real discoverable courses and three approved
  articles; no fictitious course entries, testimonials, achievements or
  external qualifications are seeded.
- Existing categories, CEFR guide, information pages and public header remain,
  now with actual course and article links, course search, trilingual language
  selector and working course action from category detail.
- The internal editorial workflow for **posts only** now supports author draft,
  submitted review, independent second-administrator approval/rejection,
  audit history and immutable published text. Legal policies and custom pages
  remain in the draft-only workflow pending separate legal approvals.

## Authentication

- Compact centered, responsive royal-navy-and-gold login and registration
  pages, English/Khmer/Simplified Chinese screen labels, no copied logo and no
  public frontend overhaul of the user's established header.
- Email, password, remember-me, password visibility toggle, name, confirmation,
  required draft Terms/Privacy acknowledgement, real CSRF-safe forms.
- Server-side registration remains **student-only**; no public admin/instructor
  provisioning or external/social login activated. Current login rate limits,
  hashed passwords and session regeneration are preserved.
- Password-reset and outbound email remain **disabled** until configured and
  independently tested. No dead "forgot password" button.

## Secure one-click installer

**Operator preconditions:**

1. Provision a *new empty* dedicated database and least-privileged DB account.
   Never point it at production or an existing school database.
2. Install Composer dependencies and build Vite frontend assets with pinned
   locks. Set web root to Laravel `public/` (never the repo root).
3. Create a protected `.env` based on `.env.example` before starting PHP-FPM:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`
   - `APP_KEY` generated with `php artisan key:generate`
   - Configured database driver/host/name/user/password in `.env`
   - `SESSION_DRIVER=file`, `CACHE_STORE=file` during fresh setup
   - `INSTALLER_ENABLED=true`
   - `INSTALLER_TOKEN` generated privately (at least 32 unpredictable characters):
     `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`
4. Ensure `storage/` and `bootstrap/cache/` are writable by the isolated
   app user, and that `.env` is not publicly served. Only expose HTTPS.
5. Browse to **`https://your-domain/install`**. Paste the operator token in
   the password field (never in URL or screenshots), then enter the first
   admin credentials and confirm this is a new authorized installation.
6. Click **Install Learn Cambodia** once. The server runs pending migrations,
   idempotent category/CEFR reference seeders, creates a role-locked admin,
   writes a private single-use file lock and signs the admin in.
7. Change `INSTALLER_ENABLED=false` and clear `INSTALLER_TOKEN` in `.env`.
   Rebuild config cache/reload PHP-FPM under reviewed deployment steps.

**Guards:**

- Installer is disabled by default, secret must be long, unlock is limited
  and uses a session rather than a URL token, all mutations are CSRF-protected.
- It refuses access if a lock exists or users/course records already exist,
  never prompts for DB credentials in HTTP, never invokes `migrate:fresh`
  and never resets an existing application.
- Production requires HTTPS APP_URL, an HTTPS request, APP_DEBUG=false,
  encryption key, writable storage and compiled build assets.
- Installation serializes concurrent requests under a filesystem mutex.
- Migrations cannot be universally rolled back transactionally on MySQL.
  If a first attempt stops during migration, **inspect and reconcile the
  isolated database before retrying** rather than clearing locks or reusing
  an existing database.
- Credentials and secret token are not written to logs or returned in
  response content.

## Independent acceptance still needed

- [ ] Exact final-head GitHub Actions tests and Vite build.
- [ ] First-install replay against isolated physical MySQL/aaPanel PHP-FPM,
      TLS, private env/lock paths, backup/restore and rollback.
- [ ] Real Safari/Android/iOS/tablet responsiveness, screen-reader,
      Chinese/Khmer typography and full translation acceptance.
- [ ] Security review of installer races, file permissions, CSRF/session
      safety, admin provisioning, editorial ownership and private data.
- [ ] Reconcile stacked draft PRs #1–#8 before any merge/release.

**Production and GitHub main remain unchanged; do not deploy yet.**
