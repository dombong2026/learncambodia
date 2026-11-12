# Learn Cambodia

**Version:** v0.0.1 (development)  
**Website:** https://learncambodia.com  
**Author:** Ton Samith · https://tonsamith.com  
**Repository:** https://github.com/dombong2026/learncambodia  
**License:** MIT (source code); see [Content Rights](CONTENT_RIGHTS.md)

Learn Cambodia is an international language-school platform foundation with
English learning pathways, English proficiency-exam preparation information,
teacher-development categories, and CEFR A1–C2 level descriptions.

## Stack and boundaries

- Laravel 13 / PHP 8.3+ manages web requests, sessions, security and data.
- Tailwind CSS 4 and Blade deliver accessible, responsive public and auth UI.
- TypeScript handles the category tray and dropdowns; Vite builds frontend assets.
- Python is an **optional, independent** future service for validated assessments,
  analytics or AI workloads. No Python process is required to operate this build.
- SQLite for local development; MySQL for a tested production deployment.

## Local development (new, disposable database only)

Prerequisites: PHP >=8.3 with Laravel-required extensions, Composer,
Node.js supported by Vite 8 (Node 22.12+ recommended), npm and SQLite.

```bash
git clone https://github.com/dombong2026/learncambodia.git
cd learncambodia
# Switch to the feature branch while PR is under review:
git switch feat/v0.0.1-platform-foundation
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

For tests: `php artisan test` (SQLite in-memory database) and
`npm run typecheck`. Do not run destructive migration commands on production.

## Implemented in v0.0.1 branch

- Public two-tier header: logo, responsive search, flag language switch,
  user menu, smooth scrolling category tray.
- Four-column footer with functioning information/category links.
- Modal-style centered login and registration pages **without** the site header
  and footer; student dashboard.
- 23 editorially described pathway categories and six CEFR reference levels.
- Course schema with **draft-by-default** publishing. No fabricated course
  offerings, instructors, accreditations or examination centres.
- Basic English/Khmer UI translations for the global controls. Full localized
  editorial content and a validated placement test are later phases.
- Middleware-level session locale setting; CSRF-protected POST actions,
  password hashing, generic authentication errors and login throttling.
- PHPUnit feature tests and GitHub Actions quality workflow.

## Release blockers

The registration form references draft Privacy and Terms documents that require
formal legal review. Authentication and educational data have not been accepted
against real devices or real aaPanel hosting. Domain/DNS, TLS, SMTP, backups,
database credentials and operational monitoring still require independent
verification. **Do not deploy to production from this draft PR.**

See [Architecture](docs/ARCHITECTURE.md) and
[Deployment Checklist](docs/DEPLOYMENT.md).

## Role-based account foundation (draft PR #2)

The three role-separated account areas are `/dashboard` (admin),
`/account` (student), and `/developer` (developer). Their navigation
uses one reusable workspace layout with distinct grouped menu definitions.

Read [Role-Based Account Architecture](docs/ACCOUNT_WORKSPACES.md) for
provisioning controls, API-token scope, and implementation status.
