# Milestone 9 — Seven reference-inspired public frontend pages

**Branch:** \`feat/seven-reference-public-pages-profiles\`
**Draft PR:** https://github.com/dombong2026/learncambodia/pull/9
**Base:** draft PR #8 — \`feat/public-pages-auth-installer\`

## User reference images

Seven screenshots provided as visual design references for:
1. Home (\`/\`)
2. Course Catalog (\`/courses\`)
3. Single Course (\`/courses/{slug}\`)
4. Instructors (\`/instructors\`)
5. Single Instructor (\`/instructors/{profile-id}\`)
6. Students (\`/students\`)
7. Single Student (\`/students/{profile-id}\`)

### Visual implementation

- Navy/gold education-focused layouts, bento modules, white and light-grey
  content surfaces, compact cards, SVG icons, responsive filters and
  mobile-stacking columns.
- Existing **global header and footer remain unchanged**; this milestone does
  not copy logos, certification seals, payment QRs, student photos or fabricated
  statistics from the reference screenshots.
- Existing course/auth/installer pages from PR #8 are preserved.
- All new ordinary page actions use existing valid URLs or implemented
  workflows. No dead 'download', 'Telegram', 'follow', 'group join',
  'book class', 'earn badge', 'KHQR' or external authentication buttons.

### Home

- Actual published course, category and article counters.
- Real approved featured courses and voluntarily public approved instructors.
- CEFR pathways and practical study sequence, with working course,
  instructor, catalog, account and article destinations.
- No claims for attendance, financial results, student counts or awards.

### Course Catalog & Single Course

- Real search, category/group, CEFR and delivery-mode filters and pagination.
- Useful sidebar with filters, CEFR navigation and approved public teachers.
- Single-course page includes genuine academic metadata, module names
  and lesson counts, instructor links for *current* assigned instructors,
  and same-category published course suggestions.
- Private lesson titles, content, quizzes, exam questions, enrollment
  records and real student results are not exposed.
- Existing role-protected enrollment flow is reused; registered students
  can request enrollment, view their status or resume approved study.
- No payment, media preview, syllabus download, private reviews or official
  exam certification is fabricated.

### Instructor & Student Profiles

- \`public_profiles\` migration, PublicProfile model, public directory and
  single-profile pages, owner editor and second-administrator review queue.
- Instructor profiles are entirely opt-in, independently approved and
  immediately withdrawn on owner request. They show only self-entered
  approved public fields and assigned current published courses.
- Student profiles require explicit consent and self-attestation of adulthood;
  approval by a different administrator; **student public visibility is
  disabled by default** via \`PUBLIC_STUDENT_PROFILES_ENABLED=false\`.
- Student enablement is a separate future safeguard after independent
  minor-safety, identity/age verification, data-protection and
  educational-policy reviews. Self-attestation is **not** independent
  age verification.
- Student publicly shared counts are opt-in (separate checkbox) and limited
  to approved enrollment count and self-marked completed lesson count.
  Grades, assignment answers, attendance, private schedules, certificates,
  exact identity, email, phone, passwords and ID documents are excluded.
- Published or under-review text cannot be edited without withdrawing
  the profile first. Withdrawal immediately hides it; re-review is required.
- Role changes invalidate visibility; all approval events are audited.
- New public profile copy is English, Khmer and Chinese; academic author
  content remains in its original user-authored language.

## Release gates

- [ ] GitHub Actions exact head PHP tests, SQLite migrations, route check
      and TypeScript/Vite build.
- [ ] Real aaPanel Nginx/PHP-FPM and MySQL installation; backup/restore drill.
- [ ] Independent security, author-consent and learner-safeguarding review.
- [ ] iPhone, Android, tablet, Safari, Chrome, keyboard/AT, Khmer font shaping,
      Chinese line wrapping and real-browser visual acceptance.
- [ ] No changes to production or GitHub main; stacked PR #1 through #9
      reconciled and independently accepted before merging/deploying.

**No unsourced reviews, stats, badges, photos, QR codes, school seals, rankings
or testimonials are seeded or displayed as verified facts.**
