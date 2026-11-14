# LearnCambodia · Frontend Experience 3 (review candidate)

## Starting point
Built from verified Frontend Experience 2.0 release on Main `aed4b8a911166fe7364fc0d3ed3357636c411133`. The November 14 aaPanel operator log confirms the earlier upgrade, private backups and unchanged Basic Authentication. This PR is **not** automatically installed.

## Implemented scope
- Shared scoped workspace presentation for admin, student, instructor and developer views. Standardizes panel surfaces, table headers and hover states, form focus states, dark-mode borders, heading rhythm and mobile overflow.
- Sidebar navigation gains a mobile search field; desktop/mobile filtering is synchronized while the mobile input retains focus.
- Generic informational pages use the same navy/amber hierarchy and consistent quick links as the modern public pages; legal draft labels remain explicit.
- Reusable workspace empty state with accessible text and relevant destinations; applied to admin course manager, editorial drafts, saved courses and student course catalog.
- Student course and developer panels use shared panel primitive; statistics reserve space for corner SVGs. English/Khmer/Chinese labels for newly introduced interface text.

## Out of scope / invariants
- No schema changes, migrations, seeders, role or authorization modifications, route changes, approval bypasses, publication changes, payment or email activation.
- Only approved published course data continues to display in student catalog. Legal documents remain marked as drafts. No production changes.
- Avoid surprise mobile focus jumps, introduce no extra blank/demonstration content, and respect reduced-motion preferences.
- Existing brand logo, header behavior, theme persistence, public frontend and Basic Authentication remain intact.

## Acceptance gate before Main or aaPanel rollout
1. PHP tests (including `WorkspaceVisualSystemTest` and all existing authorization/content tests).
2. TypeScript typecheck and Vite production build.
3. MySQL integration CI, static/security checks, Chromium/WebKit desktop and mobile visual tests.
4. Manual comparison on phone, tablet, desktop: role menus, long translated labels, no table overflow of page body, keyboard tab order and focus visibility.
5. Verify real approved/draft content boundaries, empty states, legal draft text and light/dark/system preferences.
6. Independent aaPanel staging smoke test with backups and rollback rehearsed, then explicit sign-off before any live production update.

Deployment intentionally not included in this feature branch.
