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
5. The aaPanel database name can be **sql_learncambodia_com** (the operator-reported name) or learncambodia_... . The dedicated database user can be sql_learncambodia_com or lcam_... . Reuse only a **new, completely empty** database dedicated to this site; the installer now checks zero tables and inspects `SHOW GRANTS FOR CURRENT_USER()` to reject global privileges, grant options, or access to other schemas. Never use a MySQL root account or share the database password in GitHub or chat. An aaPanel-generated password containing **exactly 13 digits** is accepted *only* for database and username both named **sql_learncambodia_com**, and only if the authenticated MySQL account is restricted to **localhost** or **127.0.0.1** rather than a wildcard host. The normal stronger 24–128-character password policy is still accepted for other dedicated accounts. Because a 13-digit numeric password has substantially less entropy, prefer rotating to a longer password before any public launch, updating the application configuration securely in the same maintenance operation.
6. Use the latest ops/guarded-aapanel-install-20261113 operator script only after checking its Git commit and Git blob. Download with gh api, verify git hash-object and bash -n, then run check before install. The installer checks Git/CI/tree/lockfiles, domain delegation, live origin TLS plus 401, auth_basic, PHP/FPM, Composer checksum, and approved placeholder contents.
7. The installer prompts privately for isolated DB credentials, checks the database is empty, builds the application in a private staging area, downloads a provenanced CI Vite artifact, installs PHP dependencies using the isolated PHAR, and places the app only into the approved placeholder. It preserves original .user.ini and copies its validated open_basedir restriction to public/.user.ini, where PHP-FPM can read it.
8. Open https://learncambodia.com/install only through Basic Auth. Complete the authorized web installer with locally stored token. The installer migrates and seeds the fresh DB and creates the first administrator, then writes an installer lock. After verifying lock, run the same script in finalize mode to disable installer and remove token. Do not publicly launch yet.

## Stop conditions

Any missing auth gate, insecure TLS, unexpected directory contents, altered Git tree, absent/expired CI artifact, unreviewed .user.ini, missing PHP extension, Composer digest mismatch, nonempty database or unexpected installation lock must STOP installation. Never bypass these guards or overwrite other websites.

## Independent release requirements

Real aaPanel/PHP-FPM and backup/restore tests, independent privacy and security review, student/age safeguarding, legal documents and content rights approval, physical iPhone/Android/Safari acceptance, monitoring, backup and rollback remain required for PUBLIC release. Green CI and a private installer do not replace these gates.

## Browser-first wizard handoff after verified staging

If the guarded installer has already returned NEW_SITE=STAGED_WITH_PROTECTED_INSTALLER, do not rerun install or replace the .env. Nginx HTTP 401 and the browser's username/password popup for /install are expected safety controls, not a Laravel error. Run the pinned script support/operator/learncambodia-browser-wizard-access.sh from the separate operations branch after downloading by exact commit and verifying its Git blob (the script must pass bash -n). It checks Laravel source, compiled assets, environment, installer token, absence of the installation lock, Nginx vhost and origin TLS; prompts privately for a new 24+-character website-access password; preserves and atomically replaces only /www/server/panel/vhost/nginx/.learncambodia-setup.htpasswd; and checks that authenticated /install returns HTTP 200. If browser verification fails it attempts to restore the previous website-access credentials. It performs no migrations or code redeployment.

In the browser at https://learncambodia.com/install, use username learncambodia_setup and the newly chosen website password. Then retrieve the separate setup token locally from /root/learncambodia-setup-token (never paste it into a chat). The Laravel installer prompts for the initial administrator credentials, runs migrations and seeds, writes the installer lock and redirects to /dashboard. Only after successful creation should the operator use the already integrity-verified guarded installer in finalize mode. Keep the website's HTTP Basic Auth enabled until release acceptance.


## Staged installer HTTP 500 — absolute config-cache path recovery

The original operator installer accidentally ran artisan config:cache while the app was inside /www/wwwroot/.learncambodia-install.XXXXXXXX/app. The staging directory was subsequently removed. Cached absolute paths (including sessions, view compilation, file caches and installer lock/mutex) can therefore point to a nonexistent location, causing HTTP 500 after successful Nginx Basic Authentication.

The operations script support/operator/learncambodia-restore-staged-config-cache.sh verifies that exact stale staging path exists in bootstrap/cache/config.php, backs up that file into a private root-only directory, clears and rebuilds the config cache as the www account **after the app is deployed at its final path**, and checks the installer routes. It then invokes the separately verified browser-access helper to reset the website-only Basic Auth password and ensure authenticated GET /install returns HTTP 200. **It does not migrate, seed or replace MySQL or .env.** If it cannot confirm the stale path, it stops; investigate Nginx/PHP-FPM/Laravel logs rather than clearing config speculatively. Keep APP_DEBUG=false and Basic Auth enabled.

The original guarded installer was also fixed in the ops branch: it no longer caches Laravel configuration in staging. Its finalize mode still writes the final cached configuration after successful browser installation. Do not rerun the full install on an already staged application.

## Nginx Basic Auth password-file permissions (HTTP 500 after 401)

The real server diagnostic confirmed Nginx EACCES errors opening /www/server/panel/vhost/nginx/.learncambodia-setup.htpasswd: it was root:www mode 600, and www cannot traverse the shared aaPanel Nginx vhost directory. It is unsafe to chmod or chgrp these shared panel directories. Keep Laravel data unchanged; do not reinstall.

The pinned operations helper support/operator/learncambodia-nginx-auth-path-recovery.sh creates a root:www 750 private path /www/wwwroot/learncambodia.com/.private-nginx-auth (outside public), writes root:www 640 site-only password file, updates ONLY learncambodia.com.conf's auth_basic_user_file, tests nginx syntax and reloads, then validates local HTTPS /install returns 401 without credentials and 200 with website-only credentials. It prompts privately for a new 24-128 character website password and verifies the actual Nginx worker www can read the new file. It keeps a root-only backup and attempts Nginx vhost restoration on failure. No MySQL, .env, Laravel code, or other vhosts are modified. Once passed, open /install, enter website username learncambodia_setup and new password, then use the separate root-only setup token and complete first administrator creation. Run the original integrity-pinned installer finalize mode ONLY after installer lock and dashboard are confirmed.

## Verified missing Laravel front-controller routing plus Nginx HTTP Basic Auth EACCES

Read-only live server diagnostics confirmed config(installer.enabled)=true, valid installer token, no lock, successful dedicated database connection, users and courses tables not yet created, application /install route registered, correct public document root, BUT no location / with try_files and no rewrite rule. An attempted isolated Basic Auth relocation returned authenticated HTTP 404 and rolled back because no front controller existed. Nginx previously logged permission denied opening the original root:www mode 600 .htpasswd inside inaccessible shared panel directories.

Use the pinned operations helper support/operator/learncambodia-nginx-auth-and-laravel-routing-recovery.sh (download by exact commit, verify Git blob, bash -n). It verifies one dedicated server block, expected document root, missing front controller, and blocked old auth file; backs up the vhost and password file; places site-only root:www 640 auth outside public under root:www 750 /www/wwwroot/learncambodia.com/.private-nginx-auth; and changes only that site's auth_basic_user_file directive and adds a Laravel location / with try_files $uri $uri/ /index.php?$query_string. It validates Nginx syntax and reloads, then confirms unauthenticated origin HTTPS 401, authenticated origin HTTPS 200, and the actual Laravel installer HTML marker. On failure it attempts to restore original vhost and password protection. Neither MySQL nor Laravel files/credentials are modified. Do not run older auth-only or cache-recovery helpers after the combined helper passes; complete protected browser wizard and finalize when admin dashboard is verified.

## Post-install premium auth and locale UI upgrade, 2026-11-13

PR #16 is merged into Main at commit 9a86dec6694fafe01eaf2bd27295e71f7eb9db99, whose source tree exactly matches the CI-tested PR SHA 13bde35519a721062ecdeabedeab1a58037dc0f5 (tree 17f03bf9689f110efe9a981f3f9db1bbc02571b3). Acceptance workflows: Foundation 43464088859 PASS, pinned Vite 43464089125 PASS (artifact 14056544517, seven-day expiry 2026-11-20), release acceptance 43464093399 PASS (isolated MySQL, Chromium/WebKit and security jobs all passed).

Production remains a *finalized, existing* installation; the old `install` mode MUST NOT be rerun. Operator helper `support/operator/learncambodia-guarded-ui-auth-upgrade-20261113.sh` implements `check` (read-only baseline and CI gates) and `upgrade` (explicit local confirmation). Source changes are verified against the previous exact Git tree, restricted to 20 non-test PHP/Blade/TS/CSS paths, and all compiled frontend assets must come from the pinned green CI artifact with matching provenance. Before upgrading it creates a root-private source/build copy AND read-only database dump. It enters Laravel maintenance, replaces only the reviewed code paths and Vite assets, checks routes/compiled views and origin Basic Auth, exits maintenance, then retains the private backup. It attempts to restore source and original build on failure. It does NOT modify Nginx, MySQL schema, `.env`, installer lock, or other websites. Stop on any failed gate and do not bypass.

Post-upgrade, physically test public and all four account headers (desktop/tablet/phone), actual locale switches `?lang=en|km|zh` without redirecting home, floating labels, password toggles, blank/filled fields, login/logout/register/forgot/reset/confirm email routes, same-origin CSRF and protected admin access. Production `MAIL_MAILER=log` means recovery/confirmation email notifications are intentionally NOT delivered; enable SMTP only after privacy/delivery acceptance. Keep Nginx Basic Auth enabled until independent final approval.
