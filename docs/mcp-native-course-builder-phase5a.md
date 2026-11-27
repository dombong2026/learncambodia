# Phase 5A — Native course builder rehearsal

Based on the Phase 4B.9 OAuth release, not current main. Three additional tools are gated OFF by default and hard-disabled in production. They use the existing locked Passport OAuth authorization callback; no legacy bridge dispatch.

- get_draft_curriculum: counts/ordered IDs only, never answer keys.
- append_draft_module: up to ten lessons per call, up to ten four-choice questions per lesson, feedback required. Use current module_count and a fresh UUID per module; identical retry replays receipt. Appends only; no overwrite or deletion. Native lesson quiz_questions (not separate course-level quizzes) and curriculum revision records.
- attach_draft_course_cover: selects an existing owned library image, checks storage existence, only if no existing featured media.

Rehearsal requires existing OAuth draft gates and MCP_OAUTH_NATIVE_COURSE_BUILDER_REHEARSAL=true in local/testing/staging. This is not a production activation instruction. Production requires reviewed consent expansion, explicit separate acceptance, revocation/re-consent strategy, transport and storage upload testing, and reconciliation with latest main/production overlays. Raw binary upload is intentionally not included; upload in the existing Admin Media Library, then use its ID in rehearsal. No publishing or destructive actions.

Course 30 target: ten module batches, ten lessons per batch, ten questions each; final counts 10/100/100/1000, then attach cover. No course 30 content has been imported by this change. Native lesson quizzes do not currently expose independent per-quiz pass mark, attempt limit, or timer settings in the native LessonQuiz data structure; do not report these as configured.
