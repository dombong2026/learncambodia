# Learn Cambodia — Milestone 12: private student saved courses

**Status:** Development-only, stacked draft PR based on the reviewed QA branch at `4acf556e468a54487a263596594c4a4d93942143`.

## Implemented

- New `course_bookmarks` model and database migration. One saved course per student, foreign keys with deletion cleanup.
- Authenticated **student-only** GET/POST/DELETE routes under `/account/saved-courses`. CSRF, role middleware, rate limits and database uniqueness apply.
- Students may save only currently approved and publicly discoverable courses. Draft, withdrawn, superseded, unpublished and future-dated courses are not savable.
- Personal saved list shows only currently discoverable courses and does not expose other users' bookmarks. A once-saved course that becomes unpublished automatically disappears from the list.
- Available Courses view has save/remove actions and a direct link to the private saved list. The workspace navigation and enrollments screen include the saved list.
- English, Khmer and Simplified Chinese interface copy with form aria-labels.
- Feature tests cover authentication, roles, double submits, owner isolation, publication withdrawal, duplicate prevention, removal and cascade deletion.

## Explicit non-goals

- Saving does **not** create an enrollment, grant access to lessons or assessments, reserve a place, purchase a course, email users or publish private content.
- No public student profiles, social discovery, notifications, external storage or production services are enabled.
- Existing public frontend layout, course review and enrollment approval flows are preserved.

## Acceptance

- Run the Laravel PHPUnit feature suite (SQLite) and isolated MySQL CI, source-security tests, TypeScript/Vite build and Chromium/WebKit tests on the new exact feature commit.
- Independent review of visibility and ownership restrictions is required before promotion.
- **No aaPanel installation, DNS change, database provisioning, installer execution, `main` merge or production deployment.** The parent QA PR stays draft and the domain is deferred.

A future milestone may add student-owned study notes or a learning plan, but should be separately reviewed to avoid changing academic records and safeguarding controls.
