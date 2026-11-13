# Learn Cambodia — Milestone 4: student progress, practice assessment, and course editions

**Repository:** dombong2026/learncambodia
**Branch:** `feat/learning-progress-practice-assessments`
**Pull request:** https://github.com/dombong2026/learncambodia/pull/4
**Base:** `feat/appearance-curriculum-enrollment-approvals` (draft PR #3; stacked on #2 and #1)

## Student learning

- Students with approved enrollment may mark owned, published course lessons
  complete or return them to "needs review." Changes are persisted per
  (enrollment, lesson) with a unique constraint.
- `/account/progress` shows the percentage of lessons self-marked complete.
  This is not instructor-verified attainment, a grade or a certificate.
- `/account/practice` lists internal four-answer multiple-choice practice
  quizzes attached to published courses. The preview response does **not**
  carry the correct answer key.
- Quiz submissions are graded server-side in a database transaction, with
  exact question set validation, an upper bound of five attempts per enrollment
  and quiz, and a 6-per-minute route throttle.
- Private practice history shows past outcomes and explanations. Results
  are protected by student ownership even after cancellation.

## Administrator curriculum authoring

- `/dashboard/practice-quizzes` lists course quiz records (10 per page).
- Quizzes may have up to 50 graded questions; each question has exactly four
  distinct choices, one correct option, optional explanation, and ordering.
- Courses are limited to ten quizzes. A course cannot be submitted or approved
  while any included quiz is empty.
- Submitted or published course quiz content is immutable; changes require a
  new reviewed course edition.

## Course edition workflow

- From a published current course, administrators may use **Create next edition**.
- The controller creates a new independent *draft* with its own modules, lessons
  and quizzes/question copies. Student enrollments and scores are **never**
  copied into a new edition.
- Each parent course may have at most one immediate successor, enforced by a
  database unique constraint and transactionally locked source record.
- The new edition goes through the same independent administrator review as
  any other draft course.
- When approved, the source course is marked `superseded_at` and excluded
  from public discovery/new enrollment; the approved edition becomes
  discoverable. The previous course and its lessons remain available to
  students with existing approved enrollment.
- Rejection leaves the source course discoverable, and the revised draft can
  be edited and resubmitted for review.
- No auto-migration of existing learners, payments or historical progress.

## Administrator reporting

- `/dashboard/learning-report` shows actual course lesson/quiz counts, pending
  and approved enrollment counts, completed lesson records and practice
  submission counts.
- Reporting is aggregate by course, without displaying private student names,
  contact details or their submitted answers.

## Release and safety gates

- [ ] GitHub CI pass on the exact PR head
- [ ] Independent security review of student ownership, altered requests,
      grading and attempt collision handling
- [ ] Independent concurrency tests against actual MySQL/PostgreSQL database
- [ ] Browser/device accessibility and responsive layout acceptance
- [ ] Human review of practice questions and passing score semantics
- [ ] Academic and legal review for any educational certification claims
- [ ] Isolated aaPanel installation and migrations, restore/rollback rehearsal
- [ ] Reconcile and approve PR #1 → PR #2 → PR #3 → PR #4 before production

No advertisements, payment integrations, live chat, external social identity,
outbound notifications, official exams, grades or certificates are enabled.
