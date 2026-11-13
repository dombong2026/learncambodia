# Learn Cambodia release acceptance - 13 November 2026

GitHub: dombong2026/learncambodia
Main baseline: 6d076e5d9c498b3003ca2b1b3d5aaf6672d954b5
Status: draft QA PR #12. No production installation, DNS, Nginx, PHP-FPM or payment changes.

## Distinct evidence gates

1. Isolated GitHub Actions MySQL 8 service: run the Laravel feature suite against MySQL (not SQLite), migrate and seed a disposable DB.
2. Disposable SQL backup/restore: dump a populated MySQL QA database; restore into a separate disposable database; compare all relational rows, column definitions, seeded references, migration history and a synthetic sentinel. Never restore over existing production.
3. Actual aaPanel: a human operator with authorized shell access performs a genuine isolated vhost/PHP-FPM/MySQL installation rehearsal, carefully recording evidence.
4. Desktop/mobile/tablet browsers: Playwright Chromium layout, links, locale, auth, overflow and inaccessible files are automatically checked against a local fixture.
5. WebKit: Playwright WebKit is an actual browser engine but is not an iPhone, iPad or physical Safari session.
6. Physical Safari/iOS, Android and real desktop: independent operator records exact browser/OS, date, screenshots, keyboard/touch interactions and layout.
7. Independent security: separate reviewer signs a report covering authorization, installer locking, CSRF, XSS, SQL safety, private records, headers, cookies, data protection and dependencies.

## aaPanel host procedure (requires operator, is not executed from ChatGPT)

A. Verify existing server snapshot, DNS, TLS, Nginx and PHP-FPM 8.4/8.5, disk, vhost roots and MySQL privileges using read-only commands. Do not alter other sites.
B. Create brand-new isolated directory under /www/wwwroot/learncambodia-qa-YYYYMMDD, dedicated QA hostname with valid HTTPS, dedicated MySQL database and least-privileged QA DB account. Never point the production domain or database at this QA copy.
C. Run bash support/qa/aapanel-readonly-preflight.sh /www/wwwroot/learncambodia-qa-YYYYMMDD. This script never modifies anything and deliberately does not print keys/passwords.
D. Only after verified isolation, deploy the reviewed pinned source into the isolated QA directory with Composer lock dependencies and Vite build. Nginx document root must be Laravel public directory. .env must be private, APP_DEBUG=false, MAIL_MAILER=log, installer disabled by default.
E. For a confirmed fresh and empty QA database only, issue a new server-generated private installer token, temporarily enable the guarded one-click installer, create first administrator, verify installer lockout, then disable it and remove the token. Never put installer credentials into chat or GitHub.
F. Check actual migration and seed history, guest/student/instructor/admin isolation, category searching, protected lesson reading, CSRF, workflow reviews and HTML locale layout. Do not copy real student or financial data.
G. Backup only isolated QA data, restore into a second separate QA database, compare every row and schema, and document recovery evidence. Do not use migrate:fresh, DROP DATABASE, truncation or any writes on existing customer/production databases.

## Physical device acceptance

Record 360px/390px mobile, 768px tablet, 1024px laptop, 1440px and 1920px desktop. Check English, Khmer, Chinese; Khmer text clipping; global header and footer; section width; no split-page layout or horizontal scrolling; Home, Course Catalog, Single Course, Teachers, Student privacy, Blog, Single Blog, Categories, Single Category, About, Contact, Library, Reading, Exam Catalog, Course Watch, Recommendations, Trophy, Upload Studio and account workspaces. Test actual search, filter, pagination, buttons, forms, breadcrumbs, touch and keyboard, password visibility and two-admin review.

## Independent security checklist

Reviewer other than author must inspect: account escalation, object ownership, administrator vs instructor vs student isolation, login and installer throttling, installer off-by-default, HTTPS/session cookies, CSRF, rendered XSS, SQL parameter binding, file traversal, secrets and composer/npm advisories. Verify consent and data retention for minors and inquiries. Keep private files outside public web root. Require an explicit external reviewer verdict; automation alone is not independent security approval.

## Intentionally disabled until separate acceptance

KHQR/billing and payments; advertisements; live chat and Telegram integration; external OAuth; unverified media uploads; public student directory; automatic official certification or accreditation claims.

## Release decision

GitHub CI PASS does not mean real aaPanel, physical Safari, physical Android or independent security PASS. Each gate requires evidence, exact commit, environment, responsible reviewer and signature. Keep production unchanged until every mandatory gate is complete.


## Dependency-lock release blocker (open)

The source tree currently does **not** track composer.lock or package-lock.json. CI resolves dependencies dynamically, so a passing test run is not sufficient evidence that aaPanel can install the identical packages later. The release acceptance workflow uploads the generated lockfiles as an ephemeral review artifact. An authorized maintainer must inspect dependency licenses/advisories, commit approved lockfiles to the source branch, switch deployment to composer install and npm ci, and independently rerun MySQL and browser acceptance on the pinned package graph. Keep production installation blocked until the lockfiles are reviewed and committed.

## Automated results (baseline 61ee46a, 13 Nov 2026)

- MySQL 8.0: 153 tests / 1,706 assertions, complete backup/restore with 23 categories and matching logical SQL rows, schema and migration history.
- Browser engines: 100/100 Playwright checks on Linux Chromium desktop/mobile/tablet and WebKit desktop/mobile; the corrected responsive home grid no longer overflows the mobile viewport.
- Dependency/source review workflow: green, but only on dynamically resolved dependencies due to missing source lockfiles.
- aaPanel real host installation, physical Safari/iOS/Android, reviewed restore of real staging data, and independent penetration/security signoff remain **NOT DONE**.

## Before creating the isolated QA directory

Run `support/qa/aapanel-host-readiness.sh` as root to inspect PHP 8.4/8.5 extensions, Nginx syntax, Node/npm/Composer, aaPanel vhost names and available disk *before the app exists*. This command performs no modifications, never connects to a database and does not print credentials. A passing inventory alone does not authorize installation or prove QA DNS/TLS readiness.
