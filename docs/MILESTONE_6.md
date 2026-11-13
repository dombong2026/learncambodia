# Learn Cambodia — Milestone 6: actionable dashboards and low-bandwidth UX

**Repository:** `dombong2026/learncambodia`  
**Draft PR:** https://github.com/dombong2026/learncambodia/pull/6  
**Branch:** `feat/premium-role-dashboard-widgets`  
**Base:** `feat/instructor-assignments-grading-controls` (PR #5).

## Shared account UX

The new design applies to all four actual workspaces:

- **Administrator:** `/dashboard`
- **Student:** `/account`
- **Instructor:** `/instructor`
- **Developer:** `/developer`

Navigation is consolidated into three to six semantic sidebar groups. Visible
items must reference registered, functional routes. There are **no active**
sidebar entries for Google Ads, payment methods, social authentication,
placement exams, Telegram, unbuilt ticketing, etc. Older direct module URLs
remain on their existing compatibility screens but are not exposed in navigation.

The header contains account actions and direct English/Khmer language forms,
not breadcrumbs. A small clickable breadcrumb appears immediately below it.
A role-specific, actual-page CTA sits in the top-right page heading area.
Profile & Security is always a working link at the bottom of the sidebar.
At widths below 1024px, five working bottom-tab destinations complement the
slide-out menu. No buttons without a handler, form action, or destination are
present in the new shared shell.

The English/Khmer toggle persists via the platform's existing session locale
route. Dashboard overview text, shared chrome and many navigation labels are
translated. Other legacy page copy, human-authored learning material and
institutional policies **still need a translation review**; this milestone
does not claim complete bilingual coverage.

The Data Saver toggle stores a device-local preference (localStorage),
suppresses decorative animation and pauses/disables video autoplay and preload
while enabled. Turning it off restores each video element's original
preload/autoplay configuration. **It is not** offline sync, adaptive streaming,
a bandwidth guarantee, a server preference or a replacement for accessibility
settings. Manual playback remains possible.

## Actual dashboard data

Metrics are based on existing authorized records and are displayed as tall
cards in a responsive two-to-five column grid (maximum five summary cards per
role, at most three rows on narrow screens). Each is a real link and includes
an SVG icon at its upper-right.

The `DashboardNumber` formatter abbreviates 1,000 → 1K,
10,000 → 10K, 1,000,000 → 1M, etc. Screen readers and tooltips expose
the real unrounded integer.

- **Student:** own approved published course progress, Resume Learning links,
  self-marked lesson completions, recent practice results grouped by real
  course category, actual upcoming published homework deadlines, and a
  consecutive-day **study activity** indicator based on real events in the
  latest 35-day window. It is not a login streak and never automatically
  awards a cultural badge. Performance uses up to 50 recent practice attempts.
  An SVG radar is drawn only with at least three recorded categories; otherwise
  accessible progress bars are used.
- **Admin:** approved enrollments, discoverable published courses, verified
  instructor accounts, genuine pending review counts, graded homework, actual
  daily new enrollment requests in the latest seven days and actionable
  review queues. The chart is **not** attendance.
- **Instructor:** currently assigned published courses, distinct enrolled
  students in authorized courses, real grading queue, graded submissions
  and published assignment deadlines over seven days.
- **Developer:** active/expired own tokens, remaining slots and the count of
  actually registered GET API endpoints; no invented traffic analytics.

There is no source of attendance events, reliable learner province, fees,
payments, verified missed-class data or official grades in this milestone.
Accordingly **no attendance percentage, financial widget, geographic
heatmap, student risk score or live-class reminder is fabricated**.

## Implementation

- `App\\Support\\DashboardInsights`: role-specific authorized queries.
- `App\\Support\\DashboardNumber`: reusable compact formatting.
- `App\\Support\\WorkspaceMenu`: working navigation, breadcrumbs,
  mobile destinations, safe role-specific actions.
- `resources/views/components/dashboard-metric-card.blade.php`: reusable
  compact visual card component.
- `resources/views/workspace/overview.blade.php`: responsive role-specific
  data visualizations and true CTA links.
- `resources/views/layouts/workspace.blade.php`: shared shell and breadcrumbs.
- `lang/en` and `lang/km`: interface strings.
- `resources/js/workspace.ts`: toggle, responsive menu/search, toast and
  local Data Saver behavior.

## Release gates

- [ ] GitHub CI on exact final commit; PHP tests, SQLite migrations,
      routes, TypeScript build.
- [ ] Independent privacy authorization and MySQL query-performance review.
- [ ] Real iPhone, Android, tablet, desktop Safari/Chrome/Firefox tests;
      navigation, Khmer shaping, screen readers and touch targets.
- [ ] aaPanel PHP-FPM/MySQL migration and isolated installation rehearsal;
      backup/restore/rollback before merge.
- [ ] Bilingual content and academic-policy review.
- [ ] Stacked PR reconciliation #1 → #2 → #3 → #4 → #5 → #6.

**Production remains unchanged. All PRs are drafts. Do not merge/deploy
before independent acceptance.**

## Still disabled

External social login, Telegram notification linking, KHQR/payments,
document vault/uploads, offline/PWA synchronization, official test bookings,
live chat, advertising, certificates and qualifications remain disabled.
