# Learn Cambodia — Milestone 5

**Repo:** dombong2026/learncambodia  
**Branch:** `feat/instructor-assignments-grading-controls`  
**Draft PR:** https://github.com/dombong2026/learncambodia/pull/5  
**Base:** `feat/learning-progress-practice-assessments` (draft PR #4).

## Instructor role and assignments

- A fourth role, `instructor`, is recognized by the existing account model.
  Users still register as students. Instructors are provisioned only by the
  existing `accounts:assign-role` operator CLI with explicit `--confirm`,
  operator and reason. The transition and token revocation are audited.
- Demoting instructors revokes all active course assignments.
- Administrators assign/revoke instructors to independently approved courses,
  with transactions and approval-event records.
- The private `/instructor` workspace has separate Dashboard, Teaching
  Courses, and Assignment & Grading menu groups, profile/password management,
  and no access to Admin or Developer tools.
- Course assignment authorization is checked for every draft, course and
  grading request; revocations take immediate effect.

## Reviewed coursework and grading

- Authorized instructors may create bounded, plain-text homework drafts with
  title, instructions, optional assessment rubric and due date.
- Instructor submission triggers `pending_review`. Administrators approve
  or reject; rejected drafts may be edited and resubmitted. Published
  assignments cannot be changed from the instructor workspace.
- Students see only approved homework in courses for which their own
  enrollment is approved. Submissions are text-only (10–16,000 chars).
- A unique (assignment, enrollment) constraint prevents duplicate records.
  Responses have append-only version history; returned work may be revised up
  to three versions before the approved deadline. Submitted/graded work cannot
  be overwritten.
- Assigned instructors may record a grade (0–100) with required feedback or
  return work for revision. Grade/return actions are transactional and
  recorded in `approval_events`.
- Private student history remains visible to the owning student. Administrative
  gradebook is read-only and excludes submitted answer text.

## Advanced assessment / certificate safety

- Approved homework may contain a human-readable evaluation rubric; it must
  be independently reviewed before learner-facing publication.
- The administrator-only `/dashboard/academic/evidence` page aggregates
  actual lessons self-marked complete, internal practice passes, and graded
  homework for approved enrollments.
- An administrator may request an evidence review snapshot. A **different**
  administrator can record `reviewed` or `needs_revision` with mandatory
  notes. Stale snapshots cannot be marked reviewed; resubmissions preserve
  decision audit history.
- This is **not a certificate approval or issuance process**. There is no
  certificate model, credential number, PDF, verification link, external
  recognition claim, or award download route. Future academic and legal
  policies must explicitly authorize any of those features.

## Required independent acceptance before deployment

- [ ] Latest GitHub Actions tests, DB migrations and frontend build succeed.
- [ ] Independent authorization, text-escaping, SQL/concurrency, privacy and
      role-revocation review.
- [ ] Real MySQL and aaPanel install/backup/restore/rollback rehearsal.
- [ ] Desktop/Safari/physical Android/iPhone/tablet and screen-reader QA.
- [ ] Academic approval of grade scale, rubrics, staff qualifications and
      retention/appeals rules.
- [ ] Final reconciliation and review of stacked PRs #1 → #2 → #3 → #4 → #5.

## Explicitly disabled

Paid enrollment, advertising, live chat, external/social authentication,
student uploads, automatic external notifications, official language tests,
credential issuance, certificates, badges and academic accreditation claims.

**Production unchanged. Do not merge or deploy without acceptance.**
