# Learn Cambodia — Milestone 10: full-width long-form public pages and Appearance

**Repository:** \`dombong2026/learncambodia\`  
**Stacked draft PR:** https://github.com/dombong2026/learncambodia/pull/10  
**Base:** \`feat/seven-reference-public-pages-profiles\` (draft PR #9)  
**Main / production:** not changed

## Scope and design

The five supplied reference screenshots depict two separate content areas placed side by
side. This implementation does **not** reproduce that split-screen arrangement. Every
page below is one continuous document with full-width section backgrounds and a
responsive content width of up to 1,600 px. Cards flow into 2–5 columns on large
screens as appropriate, and stack on narrow screens. Heading, spacing and graphical
treatments use the established navy / gold / off-white visual language.

No external image assets, copied institution logos, official seals, invented
testimonials, 50,000+ student figures, verified location maps, fake reviewers,
KHQR payment buttons, Telegram messages, prerecorded media, or sham signup
CTAs are created from the screenshots.

## Implemented public pages

### 1. Categories — \`/categories\`

- Continuous hero with a functioning search, public-category and approved-course
  counters, live CEFR reference totals and featured opt-in instructors.
- Full-width group explorer for configured subject groupings.
- Searchable, grouped, paginated (12/page) published category cards.
- Only currently discoverable published-course counts are shown.
- Wide learning-format cards with working links to courses, CEFR and teachers.
- Actual independently reviewed instructor profiles and approved articles,
  with meaningful empty states when publication has not begun.

### 2. Single Category — \`/categories/{category-slug}\`

- Meaningful category hero and actual-course counters.
- Approved course cards, reusable links to the existing course page and secure
  enrollment request logic; private lesson content is not exposed.
- Resource guidance, actual assigned and consented instructor profiles,
  approved resources, related published categories and visible academic limits.
- One continuous page (no persistent dividing line, no half-page shells).

### 3. Authors — \`/authors\`, optional \`/authors/{profile-id}\`

- Published editorial library with search and pagination (9/page).
- Actual article, category and course counts; no invented reader ratings.
- Contributor identity is linked only if a currently published, independently
  approved public *instructor* profile exists for the published post author.
- Single author page shows only that consenting public biography and their
  actually published articles. Unpublished posts and withdrawn author profiles
  return 404 and private legal name/email is not disclosed.

### 4. About — \`/information/about\`

- Editable, reviewed mission, vision, introduction and methodology sections.
- Real counts of published courses, categories, articles and CEFR levels.
- Learning-process, privacy/quality principles and only approved public
  educator biographies, plus FAQ and genuinely working navigation.
- No unsupported government accreditation, partner, institution or
  examination-centre claims.

### 5. Contact — \`/information/contact\`

- Functional private \`POST /contact\` message intake with CSRF, validation,
  5-per-minute throttle, honeypot, consent acknowledgement, visible feedback.
- Validated fields: name, email, topic, subject, message; never request
  passwords, sensitive documents, school IDs, or payment details.
- \`contact_inquiries\` migration with private administrator-only status
  management at \`/dashboard/contact-inquiries\`, 10 records per page,
  status filters and internal reviewer notes.
- No Telegram/SMTP push, false response-time guarantees, fabricated address,
  support hotline or location map. Outbound notifications remain disabled.
- Before accepting public production inquiries, legal teams must confirm
  privacy notices, data retention/deletion policy, data-controller details
  and operational support staffing.

## Appearance Manager

Open \`/dashboard/appearance\`. Five distinct page cards are named:

- **Categories**
- **Single Category**
- **Author**
- **About**
- **Contact**

Each has functional **Customize** and **Preview** links. Preview for
Single Category opens an actual published category if available, falling back
to the category directory if none are yet published. The original Home,
Course, Catalog, Authentication, Header, Menu, Footer, Typography controls
are preserved in a separate panel. Each section has safe, length-bounded
editable headings/introduction/section text. The second-administrator
approval workflow and audit history remain mandatory; drafts cannot alter
the public website.

The shared files \`resources/views/partials/header.blade.php\`,
\`resources/views/partials/footer.blade.php\`, and
\`resources/views/layouts/public.blade.php\` must remain unchanged from PR #9.

## Language support

New layout labels, buttons, FAQ text, protection/consent explanations and
contact-form labels are localized into **English, Khmer and Simplified Chinese**.
Editorial and Appearance Manager freeform copy is not automatically translated:
operator-edited content is presented as authored, until separately translated
and reviewed.

## Security and release acceptance

The following gates remain outstanding before merging or deploying:

1. Independent real-browser page inspection at desktop, laptop, tablet,
   iPhone, Android, Chrome and Safari widths; keyboard, accessible focus,
   contrast, touch target and Khmer line-height review.
2. Isolated aaPanel physical Nginx/PHP-FPM and MySQL fresh-install acceptance
   with database backup/restore, file permissions and app-key verification.
3. Security review: contact inquiry CSRF, throttling, XSS, admin role
   boundaries, spam mitigation, data minimization, retention and deletion.
4. Human academic/legal review of published educational copy and official
   organization/partner claims, including public student safeguarding.
5. Reconcile stacked draft PRs #1 through #10, review changes against newer
   main and require independent acceptance before merging.
6. Live payments, advertising, Telegram, social OAuth, live chat,
   automatic official certification and school accreditation claims remain off.

**No GitHub main merge or production change is performed by this PR.**
