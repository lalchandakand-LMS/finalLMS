# Course Content Studio & Digital Products

This implementation is tracked by [Issue #73](https://github.com/lalchandakand-LMS/finalLMS/issues/73).

## Product architecture

### Content post library
A reusable content item is distinct from a purchasable course. It stores a title, short description, long description, video source, thumbnail, SEO metadata, tags, status and many-to-many category assignments. Draft content is never exposed to public course discovery. Publishing a content item does not automatically publish a course containing it.

### Category taxonomy
Categories are first-class records with unique slugs, descriptions, image, SEO fields, sort order and active/hidden state. Content items and courses may each be assigned to multiple categories. Deleting a category must detach relationships, not cascade-delete learning content or historical orders.

### Course builder
A course is a curated collection of content posts arranged into named sections/modules with an explicit position. The builder must snapshot neither the source post nor its media: edits to a content item should update the reusable source while course ordering and section membership remain stable. A course may only be published when it has a valid teacher/owner, price policy and at least one publishable learning item. Existing Course/Lesson/Enrollment identifiers and payment history must remain backward compatible during migration.

### Flash sale rules
Campaigns target explicit courses and optionally categories. At checkout, the server calculates one eligible campaign using priority and validity windows, applies redemption/per-student limits, and persists the base price, discount and final price as immutable order/payment snapshots. Client-supplied discounted totals are never trusted. Expired, paused, draft or capped campaigns are not eligible.

### Question sets
Question sets are reusable authored assessments. Questions support MCQ, true/false and short answer, marks, explanation, difficulty and ordering. Published course attachment creates a stable assessment configuration; attempts must freeze question selection/order and scoring rules so later edits cannot alter an in-progress or historical attempt. Reuse the existing question bank and exam-attempt security where possible.

### PDF/eBook commerce
A digital product has catalog metadata and a private storage object. The storage key is never rendered as a public URL. A successful verified payment grants a revocable entitlement; every view/download checks the entitlement, product availability and rate limits, and writes an audit event. Payment callbacks must be idempotent. Preview material is a separate intentionally public asset with strict size/page limits.

## Delivery gates

1. **Content library + categories + course assembly**: additive migrations, admin CRUD, category multi-select, draft/preview/publish, ordering, public course curriculum, SEO, tests.
2. **Flash-sale pricing**: course/category targeting, scheduling, caps, checkout-side price calculation, immutable order price snapshots, boundary tests.
3. **Question-set authoring**: set/question CRUD, course attachment, preview, randomized attempts, frozen snapshots, grading and authorization tests.
4. **PDF/eBook store**: private storage, catalog CRUD, verified payment entitlement, protected reader/download, audit log, abuse controls and tests.
5. **Hardening**: Bengali/English translations, responsive/accessibility review, cPanel PHP 8.3/storage/cron runbook, package build, Laravel CI and CodeQL.

## Non-negotiable safety and compatibility

- Admin-only mutation routes with server-side authorization, validation, CSRF and audit logging.
- No destructive cascade from category/content deletion into courses, payments, attempts, enrollments or download history.
- Validate external video URLs against an explicit allowlist; never render arbitrary iframe HTML.
- Validate uploaded files by MIME, extension and size; store private PDFs outside public web root.
- Use database transactions for multi-record publish, ordering, payment entitlement and campaign updates.
- Preserve existing courses, lessons, enrollments, flash-sale campaigns, exams and question bank data.
- Do not mark the feature complete until exact-head tests/package/CodeQL pass and staging/browser acceptance is recorded.
