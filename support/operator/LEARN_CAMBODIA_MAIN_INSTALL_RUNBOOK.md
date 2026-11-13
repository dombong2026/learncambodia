# Learn Cambodia — guarded aaPanel installation runbook

Updated 13 November 2026. This document supersedes the earlier pre-registration version.

## Verified GitHub state
- Release main: db2a5cdd6fa3a3b6b2066fdb8c95084e4dbb387e.
- Exact source tree: 588b4af180b446bec48189863bb2c84e322028ad, identical to fully CI-tested feature commit c2eec3680d2b69e5e5ee2bea30ccc66ac404e96e.
- PRs 12, 14 and 15 are merged. All three feature CI workflows and the latest main Foundation Quality job passed.

## Verified hosting state
- Domain learncambodia.com is NOW registered and delegated to Cloudflare nameservers june.ns.cloudflare.com and watson.ns.cloudflare.com.
- Active Cloudflare zone proxies apex A record to origin 72.61.113.168, with www CNAME to apex.
- Direct origin HTTPS at 127.0.0.1 with the correct SNI verifies TLS and returns HTTP 403, but that status does NOT prove access control; the main Nginx vhost has no site-wide HTTP Basic Auth directive.
- Nginx syntax PASS; PHP 8.5.11 present.
- Existing site directory /www/wwwroot/learncambodia.com is www:www, mode 755, with ONLY an aaPanel .user.ini file. No Laravel application, no .env.
- Nginx root is the parent site directory, not the required /www/wwwroot/learncambodia.com/public.
- Global Composer is 2.2.6 and must remain unchanged. MySQL install account and empty database have NOT been verified.

## Before running an installer

1. Preserve the aaPanel website and its root .user.ini. Do not delete or replace them. Create a clean empty public/ folder inside the site directory. Confirm the .user.ini is a regular aaPanel PHP restriction file; any custom or unexpected directives must be manually reviewed.
2. In the settings for ONLY learncambodia.com, change the Nginx document root to /www/wwwroot/learncambodia.com/public. Verify Nginx syntax.
3. Enable real site-wide Nginx HTTP Basic Auth (website password access) and verify the DIRECT origin returns HTTP 401 to an unauthenticated HTTPS request. A Cloudflare 403 or missing-index 403 is insufficient.
4. Use an isolated Composer 2.11.2 PHAR at /root/learncambodia-tools/composer-2.11.2.phar with published SHA256 dd158c0afd41d15fac89e1b4e47b5d42911fe316c3687cd5edac98319b25d028 from https://getcomposer.org/download/. Do NOT modify global Composer.
5. The aaPanel database name can be **sql_learncambodia_com** (the operator-reported name) or learncambodia_... . The dedicated database user can be sql_learncambodia_com or lcam_... . Reuse only a **new, completely empty** database dedicated to this site; the installer now checks zero tables and inspects `SHOW GRANTS FOR CURRENT_USER()` to reject global privileges, grant options, or access to other schemas. Never use a MySQL root account or share the database password in GitHub or chat.
6. Use the latest ops/guarded-aapanel-install-20261113 operator script only after checking its Git commit and Git blob. Download with gh api, verify git hash-object and bash -n, then run check before install. The installer checks Git/CI/tree/lockfiles, domain delegation, live origin TLS plus 401, auth_basic, PHP/FPM, Composer checksum, and approved placeholder contents.
7. The installer prompts privately for isolated DB credentials, checks the database is empty, builds the application in a private staging area, downloads a provenanced CI Vite artifact, installs PHP dependencies using the isolated PHAR, and places the app only into the approved placeholder. It preserves original .user.ini and copies its validated open_basedir restriction to public/.user.ini, where PHP-FPM can read it.
8. Open https://learncambodia.com/install only through Basic Auth. Complete the authorized web installer with locally stored token. The installer migrates and seeds the fresh DB and creates the first administrator, then writes an installer lock. After verifying lock, run the same script in finalize mode to disable installer and remove token. Do not publicly launch yet.

## Stop conditions

Any missing auth gate, insecure TLS, unexpected directory contents, altered Git tree, absent/expired CI artifact, unreviewed .user.ini, missing PHP extension, Composer digest mismatch, nonempty database or unexpected installation lock must STOP installation. Never bypass these guards or overwrite other websites.

## Independent release requirements

Real aaPanel/PHP-FPM and backup/restore tests, independent privacy and security review, student/age safeguarding, legal documents and content rights approval, physical iPhone/Android/Safari acceptance, monitoring, backup and rollback remain required for PUBLIC release. Green CI and a private installer do not replace these gates.
