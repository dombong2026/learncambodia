# Learn Cambodia — Milestone 3 acceptance and operating guide

**Repository:** dombong2026/learncambodia
**Branch:** `feat/appearance-curriculum-enrollment-approvals`
**PR:** https://github.com/dombong2026/learncambodia/pull/3
**Base:** `feat/account-workspaces-rbac` (PR #2; stacked on PR #1)
**Status:** Draft for review; no merge or production deployment.

## Implemented

### Appearance

- Ten allowlisted sections: Header, Menu, Footer, Typography, Home, About,
  Contact, Course, Catalog and Authentication.
- Text fields and limited font/size choices only; no HTML, arbitrary CSS,
  script, custom asset URLs, embeds or third-party integrations.
- A saved revision starts as `draft` and has no public effect.
- The editor explicitly submits it to `pending_review`.
- A **different** administrator must approve; approval marks the previous
  approved revision as `superseded` and makes the new settings publicly
  effective. Rejection keeps public defaults or the prior approved revision.
- Records retain author, reviewer, decision, timestamps and an approval audit.

### Course curriculum

- Create course drafts with category, CEFR level, delivery method, summary
  and full description.
- Add, edit, reorder and delete draft modules and plain-text lessons.
  A module may contain multiple ordered lessons and lesson durations.
- Courses require a published category, full description, at least one
  module and at least one lesson in **each** module to submit for review.
- Lifecycle: `draft` → `pending_review` → `published` or `rejected`.
  Rejected courses can be edited and resubmitted.
- Review must be performed by a **different** administrator from the author.
- Pending and published curricula are immutable through these routes.
  New editions of published courses need a future versioning workflow.
- Only reviewed published courses whose category remains published appear
  in the public catalogue or student application list.

### Enrollment and private learning

- Students may request enrollment only for already-published courses.
- Duplicate requests cannot overwrite an existing reviewed status.
- Students can see only their own requests and cancel a pending or approved
  enrollment; another student receives 404.
- Administrator review approves or declines with recorded reviewer and
  decision. Declines require a reason.
- Approved students can read course modules and lessons only when
  their enrollment remains approved and the course remains published.
- No billing, paid enrollment, grades, certificates, notifications or live
  streaming is enabled.

### Administrator approvals

- `/dashboard/reviews?focus=courses`
- `/dashboard/reviews?focus=appearance`
- `/dashboard/reviews?focus=enrollments`

Decisions are transactional and recorded in `approval_events`. Requests
against statuses other than pending are rejected (409). Staff assignment
and access to the Admin workspace remain controlled by Milestone 2 role
provisioning.

## Database and rollback boundaries

New schema changes use sequential migrations 000008–000011. Existing
public editorial and identity records are preserved. No default course,
staff, billing or credential records are seeded. Before any real deployment:
backup and restore the target database independently; rehearse migrations
against a disposable clone and verify schema rollback and forward-restore.

## QA checklist

- [x] Automated authorization and end-to-end workflow tests defined
- [ ] Independent browser QA for desktop and physical iPhone/Android/tablet
- [ ] Concurrent approval/rejection and enrollment collision review
- [ ] Independent vulnerability and content escaping assessment
- [ ] Screen-reader, keyboard and translated content review
- [ ] Academic validation of courses and third-party exam claims
- [ ] Real aaPanel/PHP-FPM/MySQL staging deployment and restore rehearsal
- [ ] Legal Privacy/Terms and student-data retention acceptance
- [ ] Final PR stack reconciliation and production release approval

## Disabled dependencies

No payment gateways, Google/Meta advertising SDKs, live-chat providers,
social OAuth, external authentication, automatic notifications or public
certificate issuance. Menu sections for unimplemented features remain
informational, not functional integrations.
