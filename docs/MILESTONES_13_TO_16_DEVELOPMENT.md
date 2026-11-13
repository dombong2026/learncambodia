# Learn Cambodia — Product milestones 13 to 16

**Status:** Development-only. All work is on a stacked draft feature branch, not GitHub main or any aaPanel runtime.

## 13. Student Learning Planner

Private study goals linked to the learner's own currently approved course enrollment, with target dates and goal completion. Personal tasks support due dates, completion and reopening, with page-level progress totals. Weekly recurring study sessions have weekday, start time and duration. These are self-reported learning plans; they never change institutional grades, enrollments or schedules. Validation, per-account ownership, enrollment revocation and bounded record counts are enforced.

## 14. Learning Notes

A private, editable and pinnable text note can be attached to each approved lesson/enrollment. The focused lesson view has a note editor; the student notes library supports owned-course filtering and text search. Notes cannot be read or edited by other students or instructors. Revoked enrollments hide notes from lists and prevent editing, while the original owner can still delete personal notes. No file/media intake.

## 15. Instructor Tools

Homework Builder supports status and text filtering for authored assignments. The grading queue filters by currently assigned published courses and submission status. Instructor-owned reusable feedback templates have create/update/delete and explicit review-before-use controls. Teaching Insights shows aggregate approved enrollment counts, recorded lesson completions and submitted/graded assignment counts for only active assignments. Personal notes and study plans are never exposed to instructors.

## 16. Academic Content Manager

The existing course builder gains draft-only safe duplicate-module and duplicate-lesson actions. Administrator-only lesson history stores append-only snapshots for create, edit, duplicate and deletion events. Submission validates the same course readiness checklist shown in the builder. An academic content SHA-256 is recorded for each new submission, and an independent administrator cannot approve the submission if that content changes. Existing two-administrator publication and approval audit remain authoritative. Older pending submissions without fingerprints retain their previous review path.

## Acceptance

Current-head PHPUnit/SQLite, MySQL, security, frontend Vite and Chromium/WebKit CI must pass. Independent privacy, academic-policy, legal-content, mobile-device, accessibility and installation acceptance are still release gates.

**Excluded until later user instruction:** domain registration, public or private aaPanel installation, DNS, MySQL account provisioning, PHP-FPM setup, installer execution, source sync to hosting, production changes, or merging to GitHub main.
