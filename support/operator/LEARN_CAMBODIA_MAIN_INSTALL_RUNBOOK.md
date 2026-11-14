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
 

## Auth modal copy / centered header follow-up (2026-11-14)

The operator confirmed successful previous UI release installation at GitHub Main 9a86dec6694fafe01eaf2bd27295e71f7eb9db99 with root-private backup and no migrations, and supplied screenshots of the live Login/Register/Forgot Password pages.

PR #17 (`feature/20261114-auth-centered-header-copy`) was accepted and squash-merged as Main 76a01b8a3dc68088d64af0a592d248f595d07d6b. Exact tested PR SHA c2deb3a96a64c0fc0765cc8442ddf923be5c9e81, identical source tree c0f8b7ad7e0fb8da6a5b07e0d28d70b83629dc0c. Three green workflows: Foundation 43471604227, QA Vite 43471604140 (artifact 14061905674, expires November 20, 2026), complete release acceptance 43471603931 (attempt 2; browser, MySQL and dependency-security all success). First browser attempt failed from WebKit opening a page before executing tests; rerun passed all suites.

The new source differs from previously installed Main in exactly six repository files: the shared auth Blade template, English/Khmer/Chinese auth translations, and two feature/browser test files. Runtime change count: four. No schema/auth logic/mail/route/config/other-site changes.

Guarded operator upgrade file: `support/operator/learncambodia-auth-header-copy-upgrade-20261114.sh` on ops branch, pinned source commit 550ee41117765291b75e4a146071488c29c3e4d4 and Git blob 473d42031a12a1c70ea4bb65819f4b364eb755f6. Run `check` before `upgrade`, and confirm by typing `UPDATE LEARN CAMBODIA AUTH HEADER` into the private terminal. This checks exact installed source blobs, preserved Nginx Basic Auth/TLS, sealed installer, Green CI, Vite source/provenance, and backup of source/assets/read-only database before writing; only four runtime files and pinned Vite assets are installed with rollback attempt on failure. Stop if any gate blocks. Do not rerun original installer or PR #16 installer.

After upgrade, manually inspect Login, Register, Forgot Password, Reset Password (valid token only), authenticated Confirm Email, mobile/desktop layout in EN/KM/ZH, top-left icon-only Home, centered heading/subtitle, removal of obsolete footer, keyboard access, login/signup permissions, and unchanged Basic Auth and outgoing mail restrictions.


## Approved responsive brand release after successful ZIP staging (14 November 2026)

Operator uploaded `LearnCambodia_Branding_Ready.zip` to `/www/backup`, verified archive SHA256 `c5c485cb6c886672be4575ab779b20923b60cc0b69dc80dc05d0ee36af06bfbb` and 23 approved files, and pushed exact files to review branch `feature/20261114-approved-responsive-brand` after signing into a separate root-only `dombong2026` GitHub profile. Original server credentials for other projects were not changed.

PR #18 fully accepted on tested SHA `7107992739465b7f208ca8280a4e490c878426de`; Foundation run 43541117655 SUCCESS, Vite build run 43541117733 SUCCESS (artifact 14091332310, expires 2026-11-21), release acceptance run 43541117797 SUCCESS (isolated MySQL, browser emulation, dependency security). Squash merged to Main SHA `4c566c8f8a6e9e77190c9a916820764546e6e304`, with exactly matching tested tree `b500b3d6791e4bdab2cfae8de9ec539496063bde`.

**Live is not automatically changed by this merge.** Guarded single-site operator script `support/operator/learncambodia-approved-brand-live-upgrade-20261114.sh`, pinned commit `4f7d3c2a18343f57f22aca53643f6e4b811806e5` and Git blob `60b0bce29722e2a5db3af3ed35f5fd44a0a1504c`, has `check` and `upgrade` modes. It verifies installed previous source hash, finalized installer lock, existing Nginx Basic Auth, same-tree green GitHub CI, local approved ZIP and exact GitHub asset bytes; then downloads CI-pinned Vite assets, takes a root-private app source/frontend/DB backup before changes, and requires explicit local confirmation. It replaces only seven reviewed Blade components/layouts, 23 `public/brand` files, favicon and Apple icon root aliases, plus CI built Vite assets. It recompiles Laravel views in the final application directory and attempts site-only rollback on any failure. No DB migrations, app secrets, Nginx vhost or other domains are changed.

After successful guarded upgrade, validate all light/dark headers, sidebar/auth/footer logos, favicon tab in fresh browser context, Safari Apple touch icon, Android 192/512, maskable PWA installation and Windows tile; keep site Basic Auth enabled pending independent production acceptance. If Main HEAD or CI artifact changed/expired, script must BLOCK pending a newly reviewed release.


## Post-merge brand rollout preflight correction (14 November 2026)

First production brand release attempt was blocked safely during `check` with `BRAND_ASSET_STAGING=BLOCKED: Main advanced; repin before asset publish`. This was **not** an asset or production failure: the older `learncambodia-brand-stage-v1.sh` is intentionally pinned to pre-merge Main and must not be reused for a *post-merge live deployment*. No site files or MySQL data were changed.

The corrected rollout helper `support/operator/learncambodia-approved-brand-live-upgrade-20261114.sh` on ops branch at commit `3a041a0ea9ab7b362856eaf6ac40dbf6f48d7e23` and Git blob `8889946889491caeb376f51297e34abda146733f` replaces this obsolete staging precheck with local offline verification of the exact already-approved ZIP SHA256, the **exact 23 file set**, individual SHA256 hashes and sizes, image type/format/dimensions, favicon header, dark/light official logo digests, and PWA manifest. It still verifies Main SHA, CI success, approved GitHub binary content against the user ZIP, Vite provenance, and takes private backups before a guarded site-only upgrade.

Use the new pinned script (do not reuse existing `/root/learncambodia-approved-brand-live-upgrade-v1.sh` with old blob), run `check` before `upgrade`, and do not bypass any new failing gate.


## Profile dropdown light/dark switch (14 November 2026)

PR #19 is merged into GitHub Main `f673385404692403df1c90322ba5c5d2ed226f79` with source tree `0b7a16c80b23b6b6602feab3ea632f6342b90dd7`, an exact match for reviewed PR SHA `a813266e5b821a8cb01487f424f05188810a78c8`. The new UI adds a switch in the shared public guest/member and workspace profile dropdown menus; theme preference `learncambodia.theme.v1` is local only, loaded early on all three public/workspace/auth layouts, applied with a class-based Tailwind v4 dark variant. Labels in EN/KM/ZH. There are no migrations, account settings changes or other-site effects.

CI gates passed: Foundation 43543781130, QA Vite 43543781102 (artifact ID 14093186176, expires 2026-11-21), release acceptance 43543781076 with independent MySQL, security, and Chromium/WebKit browser passes.

Site-specific upgrade helper: `support/operator/learncambodia-profile-theme-guarded-upgrade-20261114.sh`, pinned operator commit `565e26fbd2c85be7d527e71bab9bec6ef823acb0`, Git blob `ca5ea5bf47935e6f77882151bb50d991f175e1e3`. It verifies site lock, Nginx Basic Auth and TLS, exact previous live source `4c566c8f8a6e9e77190c9a916820764546e6e304` against the 13-file approved diff (11 runtime + 2 tests), source/tree/CI pinned asset integrity, isolated GitHub `dombong2026` credentials, and dependency locks. `check` performs the preflight without site edits. `upgrade` requires explicit `UPDATE LEARN CAMBODIA THEME` confirmation, creates root-private source/Vite/DB backup before site mutations, uses maintenance mode, copies only 11 runtime files and CI-pinned Vite build, rebuilds Laravel caches, verifies origin HTTPS 401, retains Nginx Basic Auth, and attempts site-only rollback on failure. Reinstall/migrations are not involved.

After successful upgrade, manually verify guest drop-down on public home, profile switch on Admin/Student/Instructor/Developer accounts, immediate light/dark changes, correct approved adaptive logos, preserved mode on route changes and auth pages, and mobile/desktop browser appearance. Keep protected Basic Auth until independent release approval.

 
## Premium header, flag menu, link-arrow and card SVG upgrade — PR #20

PR #20 is fully merged into Main at `71ed767f140f44bd54be8fb042a2fa51354c7af5`, same tree `2178958a2e426a302ffde570af638882afaac3ec` as tested PR SHA `20d44702cf162d99d10e536a33be28624064d1ad`. Green: Foundation 43558736402, Vite 43558736349 (artifact 14099146661, expires 2026-11-21), isolated MySQL/Chromium/WebKit/security 43558736350.

The update introduces shared flag-only EN/KM/ZH dropdown, animated header language/profile controls with high-contrast profile button and reduced-motion safety, blended upper-right SVG icon art in common card templates and public pages, removes decorative link arrow-up-right glyphs in reviewed views, and makes course, person and article titles clickable. Diff exactly 19 paths including 2 tests and 17 runtime CSS/Blade paths; no migrations.

Operator helper `support/operator/learncambodia-premium-header-cards-upgrade-20261114.sh` is pinned at commit `434cc94a5a9fde52695ba8e9eeffb14c50a3213c`, Git blob `b3abfc78bf2b9f1ab75eb2fc1c3c3a9c501b3652`. Only run `check` first; `upgrade` requires private terminal phrase `UPDATE LEARN CAMBODIA HEADER CARDS`. It verifies original installed source at PR #19 `f673385404692403df1c90322ba5c5d2ed226f79`, protected Nginx origin, exact reviewed Main and CI asset provenance, Composer/npm locks, 19-path diff/17 runtime-file allowlist, then backs up source, assets, and MySQL privately before deployment. No production DB migrations, credentials, Nginx edits, or other-site changes are intended. Retain Basic Auth until independent release approval. Post-deploy physically test both themes, desktop/mobile dropdown motion, category navigation, title links, art sizes and focus states.
