# Learn Cambodia — Homepage Course Player aaPanel release notes (2026-11-23)

## Production preflight facts (verified by operator terminal output)

- Application directory: `/www/wwwroot/learncambodia.com`
- Laravel deployed as a **copy / release directory**, not a Git checkout (no `.git`). Running `git pull` or `git status` in this directory will fail.
- Production `resources/views/home.blade.php`: **4,160 bytes**, modified **2026-11-21 14:24 UTC**; contains a dynamic `$hero['hero_title']` and `$hero['hero_intro']` around lines 19–21.
- GitHub `main` homepage uses a **different Blade template** and references `Appearance::value('home', ...)`. The PR branch inherits that older template.
- Production `app/Support/Appearance.php`: **16,366 bytes**, modified **2026-11-19**, with only `hero_title` and `hero_intro` as home fields.
- Production `app/Http/Controllers/CatalogController.php`: **4,872 bytes**, modified **2026-11-19**.
- Server has PHP/Composer but no `node` or `npm`. Existing `public/build/manifest.json` is dated Nov 17.
- Disk free: **49 GB**.

## Release gate

**Do not merge/deploy this PR as it stands.** The homepage view cannot be replaced with the GitHub version without risking loss of the live Nov 21 design. A migration/rebase against the exact deployed source is required before writing production files.

### Required snapshot (read-only)

From aaPanel Terminal, inspect **only application source**, not `.env`, credentials, certificates or runtime logs:

```bash
(
 set -Eeuo pipefail
 cd /www/wwwroot/learncambodia.com
 printf '\n=== LIVE HOME VIEW ===\n'
 nl -ba resources/views/home.blade.php | sed -n '1,180p'
 printf '\n=== HOME ROUTE / CONTROLLER SIGNATURES ===\n'
 grep -RnE "return view\(['\"]home['\"]|Route::get\(['\"]/['\"]|hero_title" routes app/Http/Controllers | head -80 || true
 printf '\n=== LIVE CATALOG CONTROLLER ===\n'
 nl -ba app/Http/Controllers/CatalogController.php | sed -n '1,130p'
 printf '\n=== LIVE ADMIN APPEARANCE FORM ===\n'
 nl -ba resources/views/workspace/admin/appearance-form.blade.php | sed -n '1,115p'
)
```

Review output for any hardcoded secrets before sharing.

### Reconciliation / QA

1. Recreate the live homepage markup and related home controller data in a reviewed GitHub source baseline (do not copy `.env`, uploads, database or generated credentials).
2. Port the alternate Course Player as a **conditional slot**; maintain the live default hero verbatim. Retain `hero_title`, `hero_intro`, and independent appearance review.
3. Ensure any Vue/TS/JS entry is included in the Vite build; CI must generate the manifest and assets. Do **not** require Node installation on the production host.
4. Run PHP/feature/integration and security tests against the reconciled code; verify course playback, no private lesson content leakage, and navigation/fallback.
5. Deploy on an isolated staging copy first; compare assets and render screenshots desktop, tablet and phone for EN/KM/ZH, light/dark, with and without featured media.
6. With backups and rollback available, release a pinned artifact to the copy-deployed aaPanel site. Preserve all `storage`, `.env`, server configuration and current uploads. Apply only necessary migrations, if any.
7. Activate `Course Player` through the existing independent appearance approval flow after production smoke tests. Keep `Hero Default` as immediate rollback selection.

## Current status

**GitHub PR #118: draft. Production: unchanged. Physical device QA: not executed.** All dates, statuses and paths above reflect operator-supplied output, not a live server session initiated by this tool.
