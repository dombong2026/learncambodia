# aaPanel deployment and acceptance gates

This guide describes work to perform **after** the draft PR is approved;
the current branch is not deployed.

1. Capture a server snapshot and verify the existing Nginx vhost, PHP-FPM
   socket, DNS and TLS for learncambodia.com without modifying unrelated sites.
2. Prepare a dedicated Unix user, restricted directories, isolated MySQL
   database and least-privileged database account. Keep `.env` off GitHub.
3. Use PHP 8.3+ and supported extensions. Set `APP_ENV=production`,
   `APP_DEBUG=false`, `APP_URL=https://learncambodia.com`; choose a supported
   session and cache backend. Disable outbound email until approved.
4. Install Composer dependencies with a reviewed lock file and build pinned
   npm assets with a lock file. Set Nginx root to Laravel's `public` folder.
5. Test migrations first against a disposable database, then take a backup
   immediately before an approved production migration. Never use
   `migrate:fresh` on production.
6. Verify owner permissions, CSRF, login throttling, unauthorized dashboard
   access, category search, locale switching, sessions, mobile layout,
   keyboard accessibility, dark/light appearance, and SEO.
7. Independently approve public Terms, Privacy, placement testing,
   official-examination claims and every educational course before release.
8. Configure backup restore drills, monitoring, PHP-FPM restart checks,
   scheduled tasks and a documented rollback path.
