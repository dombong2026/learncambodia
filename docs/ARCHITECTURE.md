# Learn Cambodia v0.0.1 architecture

## Application boundaries

- `app/Http/Controllers/`: authenticated and public HTTP workflows.
- `app/Http/Middleware/SetLocale.php`: allows only configured locales.
- `app/Models/`: courses, categories, users and CEFR reference data.
- `database/migrations/`: versioned source-of-truth relational schema.
- `database/seeders/`: idempotent editorial taxonomy and CEFR reference data.
- `resources/views/layouts/`: separate public and authentication shells.
- `resources/views/partials/`: reusable header and footer.
- `resources/js/app.ts`: accessible dropdowns and category tray interaction.
- `resources/css/app.css`: Tailwind source with reduced-motion support.
- `lang/en` and `lang/km`: initial interface translations.

## Content model

A `Category` is an informational learning pathway, not a claim of course
availability. A `Course` belongs to a category and optionally a CEFR level.
Courses default to `draft` and are public only when `status=published` and
`published_at` is non-null and not in the future. No course rows are seeded.

Roles initially default to `student`. Instructor and administrator workflows
will require dedicated policy gates; self-registration never accepts a role.

## Authentication and privacy

Laravel's web middleware supplies sessions, CSRF checks, cookies and output
escaping. Rate limiting prevents unlimited login guesses. Login/logout rotate
session state appropriately; credentials are never written to logs. Avoid
recording student assessment contents before consent and retention policies
have been approved.

## Python integration (deferred)

If a validated assessment or learning analytics subsystem needs Python, use
an independently deployable private worker, consuming signed jobs from the
Laravel queue. Limit data passed to the worker, validate all input, define
explicit timeouts and retention, and never execute arbitrary Python from a
web form. Core website requests must not rely on the worker's availability.

## Production boundaries

Keep the review branch separated from `main`. Do not enable payment,
certification issuance or institutional claims until business, legal and
academic acceptance is documented.
