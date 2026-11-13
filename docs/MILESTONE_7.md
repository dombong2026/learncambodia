# Milestone 7 — screenshot-referenced navy/gold account dashboards

**Repository:** `dombong2026/learncambodia`  
**Branch:** `feat/reference-navy-gold-dashboard-trilingual`  
**Draft PR:** https://github.com/dombong2026/learncambodia/pull/7  
**Base:** draft PR #6 (`feat/premium-role-dashboard-widgets`).

## Design implementation

User-supplied reference images for Teacher, Admin and Student dashboards are
used for **visual direction only**:

- Deep navy left sidebar, muted blue navigation, warm gold active indication,
  consistent premium rounded white panels, and gold progress-ring graphics.
- Navigation preserves **real, functioning sections and items** configured by
  the existing workspace menu. An icon mapped to each route/key is drawn
  from a custom inline SVG path; no third-party icon font and no template images.
- Settings is pinned to the bottom (gear icon), above the sidebar boundary, and
  opens the existing authorized Profile & Security settings view.
- No temple logo, copied example favicon, fake avatar, demo badge or image
  downloaded from the references. The sidebar uses only a plain text brand
  name until a separately approved logo is created.
- **Header structure unchanged:** existing website/Home, search, device-local
  Data Saver, profile menu and working role actions remain; a third Chinese
  language control is added in the original language control group. Breadcrumbs
  remain small and *below* the header.
- Top section: localized welcome message, a green real-record status shortcut,
  featured navy card with up to two gold progress donuts, real-timeline card,
  and priority action panel. Previously implemented responsive KPI cards
  and detailed dashboard sections remain below the new top layout.
- Device sizes: new cards stack on mobile; shared bottom navigation, accordion
  menu, keyboard focus and light/dark modes are preserved.

## Supported locales

- English `en`, Khmer `km`, Simplified Chinese `zh`
- Locale route and session middleware validate these codes; the account
  language switch changes the session locale in all four role workspaces.
- Dashboard messages, core workspace chrome, sidebar groups/items and account
  header labels are translated into the three languages.
- Existing long-form *inner* views still contain English text that requires
  localization review. This milestone does not claim all curriculum,
  policies or older account content have been fully translated.

## Trusted operational records

The reference example's numbers are **not seeded or copied**.

- Student rings: approved published course lessons, including student-owned,
  self-reported completions; timeline: their own approved homework.
- Teacher rings: real graded-submission percentage and the share of
  authorized courses with published homework. Timeline: own permitted
  course's pending grading.
- Admin rings: approved enrollments / all enrollment requests, and current
  published courses / all course records. Pending approvals remain links to
  real review queues.
- Developer rings: fraction of ten permitted active tokens, and whether any
  registered read-only API endpoint exists; no request-traffic graph.
- If there is no denominator, the ring shows **—** (unavailable). All link
  tiles resolve to existing role-appropriate routes.

## Explicitly not enabled

Attendance tracking and student risk scoring, provincial geolocation heatmaps,
KHQR/ABA/ACLEDA payments or fees, Telegram notification linking, classroom
schedule or live Zoom/Telegram calls, student identity-document vaults, offline
downloads/PWA sync, sample cultural badges, certificate issuance, ads, and
external authentication. No official academic certification is claimed.

## Independent acceptance remaining

- [ ] Exact-head GitHub Actions tests, TypeScript/Vite build, SQLite migrations,
      route compilation and locale checks.
- [ ] Real MySQL/aaPanel install, backup/restore, rollback rehearsals.
- [ ] Mobile/tablet/Safari/Chrome, touch, dark mode, Khmer and Chinese text
      shaping, translation QA, zoom and screen-reader checks.
- [ ] Independent privacy, authorization, performance and security review.
- [ ] Review and reconcile draft PR stack #1 → #2 → #3 → #4 → #5 → #6 → #7.

**Production and main must remain unchanged until these gates are approved.**
