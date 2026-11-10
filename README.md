# Alom's English Center LMS

Laravel 12 LMS application targeting PHP 8.3+ and MySQL/MariaDB. The repository was missing its runtime directories; they have been restored and the app boots on Laravel 12.

## Local development

```bash
composer install
cp deployment/.env.example .env
```

For local use, set `APP_ENV=local`, `APP_DEBUG=true`, `APP_INSTALLED=true`, `DB_CONNECTION=sqlite`, and `MAIL_MAILER=log` in `.env`, then run:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The local/testing seeder creates `admin@lms.test`, `teacher@lms.test`, and `student@lms.test` with password `password`. These accounts are local-only; production seeding does not create them. See [cPanel deployment](deployment/cpanel.md) for production setup.

## Implemented

- Student registration, email verification, profile updates, and Twilio phone OTP integration
- Admin, branch manager, accountant, teacher, and student role foundations; role middleware and protected routes
- Branch managers are assigned one branch and can create/update only that branch's batches; accountants can access the invoice ledger without admin payment-review or user-management access
- Courses, lessons, course enrollment, certificates, and manual payment review
- Branch and batch create/read/update/delete controls; teacher-scoped batch views
- Batch enrollment associations, teacher attendance entry, and student-private attendance history
- Batch assignments, one submission per enrolled student, teacher grading/feedback, and private PDF/DOC/DOCX/JPG/PNG attachment submissions (10 MB limit)
- Batch live-class scheduling with teacher ownership checks, student enrollment filtering, and time-windowed meeting-link visibility
- Batch multiple-choice examinations with one-attempt enforcement, timed attempts, automatic scoring, student result views, teacher-only paginated result reports, reusable teacher-owned question banks, and randomized question selection
- Public published-course catalog and slug URLs; lesson bodies require enrollment except preview lessons
- Lesson progress tracking; certificates require every course lesson completed
- Batch seat reservation for pending payments, unique transaction references, and last-admin protection
- SEO metadata, consent-gated GA4/GTM/Meta Pixel tags, and consent-based queued bulk SMS
- Admin homepage, payment, user, course, and settings controls, including a branded responsive operations dashboard
- Secure first-run cPanel web installer with database connection checks, empty-database guard, random app key, first-admin creation, and installation gate
- Administrator assignment of students to active branches, with inactive-branch validation
- Student invoices with fixed/percentage discounts (including student-specific scholarship adjustments), partial/installment receipts, and net outstanding balance tracking
- Refund request ledger tied to original receipts, refund caps, admin approval/rejection, and invoice balance recalculation
- Expense management with create/edit/delete, category and date filters, branch-scoped accountant access, and head-office expenses
- Finance reports with payment-method and expense-category summaries, date filters, current outstanding balance, approved refund totals, printable ledger, and net cash movement (collections minus refunds and expenses)
- Admin activity log for successful authenticated changes, with user/action/route/IP filters
- Public About, Contact, FAQ, and published Blog/News pages; admin-managed article drafts, publishing, SEO metadata, and cover images
- Section-based homepage Website Builder with up to 12 editable text/image/button sections; user content is escaped and does not accept arbitrary scripts
- Admin Media Library for validated image, document, and video uploads with protected deletion of assets referenced by articles
- Signed, application-key-protected database + uploaded-media + private assignment-attachment backup archives; administrator restore requires current password and explicit confirmation, with schema compatibility checks
- English/Bangla session-based language selector and expanded JSON translation catalogue for core navigation, authentication, course, profile, exam, live-class, and CMS screens

## Not yet complete

This remains an actively developed LMS and is not yet a fully validated commercial release. Remaining work includes complete Bangla translation coverage across every screen, richer drag-and-drop page design and reusable page templates, deeper branch-scoped enrollment, transfer, and teacher-assignment workflows beyond the current student-to-branch assignment, official bKash/Nagad/SSLCommerz API integration with verified callbacks, automated scheduled off-site backups and tested disaster recovery, and real cPanel staging validation of the installer and deployment process. The current backup/restore feature is administrator-triggered and should be tested on a staging database before production use. Activity logging covers successful authenticated write requests; it is not yet a full audit trail for reads, login attempts, failed actions, or field-level changes. SMS/email delivery requires real provider credentials.

## Development roadmap, open work, and planned modules

This section records the owner-approved scope across current implementation work, open pull requests and planned GitHub issues. **An open PR, draft, checklist item, menu link or prototype is not a completed production feature.** Mark a capability complete only after implementation, review, exact-head CI/security checks, staging validation and acceptance.

### Authoritative portal architecture

The current owner-approved decision is **exactly four primary portals** (Issue [#38](https://github.com/lalchandakand-LMS/finalLMS/issues/38)):

1. **Admin Panel** — one unified administration workspace for users, students, teachers, guardians, branches, batches, admissions, finance, CMS/SEO, media, reports, settings, security, audit logs, backups, support and integrations. Granular permissions control staff access; no separate Super Admin, Accountant, SEO or Branch Manager portal.
2. **Teacher Panel** — assigned courses/batches, lessons/content, attendance, assignments, exams, grading, live classes, student progress and permitted communications.
3. **Student Panel** — course discovery/enrollment, lessons/player/progress, exams, assignments, attendance, certificates, payments/orders, notices, reviews, wishlist and support.
4. **Guardian Panel** — guardian sign-in and verified links to one or more students; view only linked children's attendance, results, progress, fees/invoices, notices and permitted communications.

Branch operations are a branch-scoped, permission-controlled module inside Admin, not a fifth primary portal. Legacy Issue [#39](https://github.com/lalchandakand-LMS/finalLMS/pull/39) describes an older five-panel proposal and is superseded by Issue #38. Guardian Portal is **planned / not yet complete** until relationship linking, guardian dashboard, child switching, strict record privacy and end-to-end tests are verified.

### Open pull requests and active work

The following PRs were open at the time of this README update. They remain unmerged by this documentation change; check each current head, CI, review and dependencies before treating any item as verified.

- [#75 Uploaded ZIP security audit](https://github.com/lalchandakand-LMS/finalLMS/pull/75) — documents why the separate Express/Node prototype must not be imported into the Laravel application.
- [#74 Course Content Studio and digital product foundation](https://github.com/lalchandakand-LMS/finalLMS/pull/74) — posts/categories/course builder, flash-sale pricing, question sets, protected PDF/eBook commerce and payment callback hardening; staging and payment acceptance remain separate.
- [#72 Advanced lesson video management](https://github.com/lalchandakand-LMS/finalLMS/pull/72) — private direct video uploads, protected playback, thumbnails and cleanup; hosting upload limits need real-host validation.
- [#71 Student and Instructor Flutter app foundations](https://github.com/lalchandakand-LMS/finalLMS/pull/71) — initial foundations only; complete workflows, native builds and device testing remain.
- [#69 Exam autosave, resume and timed auto-submit](https://github.com/lalchandakand-LMS/finalLMS/pull/69); [#68 Question Bank/admin exam workflow and attempt integrity](https://github.com/lalchandakand-LMS/finalLMS/pull/68); [#67 Assignment workflow hardening and admin grading](https://github.com/lalchandakand-LMS/finalLMS/pull/67).
- [#66 bKash server-side callback verification](https://github.com/lalchandakand-LMS/finalLMS/pull/66) — automated tests do not constitute live merchant acceptance.
- [#65 Premium homepage designs and visual CMS builder](https://github.com/lalchandakand-LMS/finalLMS/pull/65) — 15 visual presets, builder, draft/publish/rollback and configurable hero carousel.
- [#60 Teacher workspace dashboard](https://github.com/lalchandakand-LMS/finalLMS/pull/60); [#59 remaining modules/homepage planning](https://github.com/lalchandakand-LMS/finalLMS/pull/59); [#58 Student learning analytics](https://github.com/lalchandakand-LMS/finalLMS/pull/58); [#57 Branch dashboard/scoped operations](https://github.com/lalchandakand-LMS/finalLMS/pull/57).
- [#56 Admin theme/color customizer](https://github.com/lalchandakand-LMS/finalLMS/pull/56); [#55 Admin dashboard/navigation](https://github.com/lalchandakand-LMS/finalLMS/pull/55); [#54 visitor analytics test fix](https://github.com/lalchandakand-LMS/finalLMS/pull/54); [#53 admission records](https://github.com/lalchandakand-LMS/finalLMS/pull/53); [#52 device MFA/session controls](https://github.com/lalchandakand-LMS/finalLMS/pull/52); [#51 role-based announcements](https://github.com/lalchandakand-LMS/finalLMS/pull/51); [#50 promotional popups](https://github.com/lalchandakand-LMS/finalLMS/pull/50); [#49 visitor analytics/views/comments](https://github.com/lalchandakand-LMS/finalLMS/pull/49).
- [#48 protected PDFs/notes/videos](https://github.com/lalchandakand-LMS/finalLMS/pull/48); [#47 Teacher KYC/protected content](https://github.com/lalchandakand-LMS/finalLMS/pull/47); [#46 student mobile verification/device approval](https://github.com/lalchandakand-LMS/finalLMS/pull/46); [#45 AI Developer read-only repository context](https://github.com/lalchandakand-LMS/finalLMS/pull/45); [#44 advanced course catalog filters](https://github.com/lalchandakand-LMS/finalLMS/pull/44); [#43 course wishlist](https://github.com/lalchandakand-LMS/finalLMS/pull/43); [#42 homepage presets](https://github.com/lalchandakand-LMS/finalLMS/pull/42).
- [#39 legacy five-panel architecture proposal](https://github.com/lalchandakand-LMS/finalLMS/pull/39) — superseded by #38; [#37 support/community navigation tests](https://github.com/lalchandakand-LMS/finalLMS/pull/37); [#36 community/messaging/AI support foundation](https://github.com/lalchandakand-LMS/finalLMS/pull/36); [#35 unified Admin account management/Update Center](https://github.com/lalchandakand-LMS/finalLMS/pull/35).
- [#33 homepage builder branding/slider](https://github.com/lalchandakand-LMS/finalLMS/pull/33); [#32 AI Developer secret redaction](https://github.com/lalchandakand-LMS/finalLMS/pull/32); [#31 AI Developer diagnostics workspace](https://github.com/lalchandakand-LMS/finalLMS/pull/31); [#29 skill-ranked quiz competition](https://github.com/lalchandakand-LMS/finalLMS/pull/29); [#26 bKash callback regression tests](https://github.com/lalchandakand-LMS/finalLMS/pull/26).

### Planned capabilities and remaining product scope

These requirements are tracked in owner-approved Issues [#38](https://github.com/lalchandakand-LMS/finalLMS/issues/38), [#40](https://github.com/lalchandakand-LMS/finalLMS/issues/40), [#41](https://github.com/lalchandakand-LMS/finalLMS/issues/41), [#73](https://github.com/lalchandakand-LMS/finalLMS/issues/73), the [master tracker #3](https://github.com/lalchandakand-LMS/finalLMS/issues/3), [release readiness #13](https://github.com/lalchandakand-LMS/finalLMS/issues/13), [AI Developer #30](https://github.com/lalchandakand-LMS/finalLMS/issues/30), and [compliance-gated competition #28](https://github.com/lalchandakand-LMS/finalLMS/issues/28). Some have partial foundations or open PRs; this list does not claim all are complete.

#### Guardian, admissions, identity and security
- Guardian registration/sign-in, verified guardian-to-student relationships, multiple children per guardian and child switching.
- Guardian access to linked-child attendance, results, progress, fees/invoices, payment history, notices and permitted teacher/institution communication.
- Strict privacy: no access to unrelated students through URL, API, search, exports or notifications.
- Admission applications/review, enrollment approval, school, class, roll, phone verification, consent-based profile/photo capture, course selection and admission-source tracking.
- Student/teacher/guardian profile lifecycle, KYC where required, MFA/device verification, session/device controls, password reset and account recovery.

#### Teaching, learning and assessment
- Course catalog/categories/tags, advanced search/filter/sort, details, curriculum builder, modules/sections, lesson sequencing, previews and course bundles/subscriptions.
- Recorded lessons (YouTube/Vimeo and protected direct uploads), private PDFs/notes/documents/resources and secure playback.
- Live classes with approved Zoom/Jitsi/other provider integration, scheduling, role-based join windows, attendance capture, recordings and automated sync where supported.
- Question Bank/question sets: MCQ, true/false, short answer and essay; tags, difficulty, explanations, images, import/export, randomized papers, attempt limits, timer, autosave/resume, auto-submit, grading, result publication and review/appeal.
- Assignments with deadlines, late policy, resubmission limits, private attachments, teacher feedback and notifications.
- Attendance marking, correction/approval, student/guardian views and reports.
- Course progress analytics, resume learning, follow-up/reminders, verified reviews, certificates with unique verification codes and public verification.
- SCORM, H5P interactive content, course forum, bootcamp, team training, course analytics and noticeboard.
- Flutter Student and Instructor apps with complete student purchase/learning and instructor course/batch/lesson/assessment/live-class workflows; native signing, device QA and store release are separate gates.

#### Website, CMS, marketing and communication
- 15 responsive homepage designs with admin preview/select/configure/publish/revert, reusable blocks, responsive controls, SEO, accessibility and mobile preview.
- Admin-controlled hero sliders, featured-course and category carousels, testimonials, flash-sale sections, banners, popups and announcements with visibility/order/design controls.
- Logo/favicon, brand/theme controls, header/footer, pages/menus, admission/contact/policy pages, media library, blog/news, SEO metadata and safe publishing.
- Visitor analytics, course/post views, consent-aware analytics, moderated comments, community/forum, messaging, customer support/helpdesk and role-targeted notices.
- Full Bangla/English localization across public pages and all portals.

#### Commerce, finance and integrations
- Course checkout/cart, coupons, wishlist, bundles/subscriptions, affiliate/referral, invoices, receipts, purchase reports, discounts, scholarships, installments, refunds and reconciliation.
- Flash sales with eligible courses/categories, fixed/percentage discounts, schedule, campaign banner, priority, redemption/student limits, pause/resume and server-side price enforcement.
- PDF/eBook store with catalog, cover/metadata/SEO, free/paid products, private storage, verified purchase entitlement, protected reader/download, audit, refund/revocation and sales reports.
- Offline/manual payment with admin verification and audit trail.
- Gateway roadmap: bKash, Nagad, SSLCommerz, Paystack, Paytm, CCAvenue, iyzico and PayUMoney only where official documentation, merchant eligibility, currency/country support, credentials, signed callbacks and sandbox acceptance are confirmed. A listed gateway is not necessarily live.
- Teacher appointment booking, course affiliate, private S3-compatible storage, Zoom/Jitsi, OpenAI tools and a controlled add-on registry with health/configuration/permission/version controls.
- Secure Admin AI Developer: Bangla/English task intake, redacted read-only diagnostics, feature-branch patch proposals, diff/CI visibility, audit history, rate/cost limits and human approval; no arbitrary shell, direct main writes, secret exposure or autonomous production deployment.
- Skill-based quiz/prize competition with transparent ranking and auditable results. Paid entry, lottery/random draw and pooled-prize functions stay disabled pending qualified Bangladesh legal advice and payment-provider approval.

#### Administration and platform operations
- Unified Admin management for users, students, guardians, teachers, branches, batches, courses, admissions, staff roles, granular permissions and branch-scoped data.
- Finance: fee plans, invoices, partial/installment payments, discounts/scholarships, dues/reminders, refunds, expenses, revenue/cash-flow reports and reconciliation.
- In-app/email/SMS notifications with templates, provider configuration, delivery logs and retry handling.
- Reports for admissions, enrollment, attendance, exams, progress, revenue, dues, refunds, expenses, teacher activity and branch performance.
- Comprehensive audit trail for sensitive actions, role changes, finance, publishing, exports, security events and restore operations.
- Database/private-media backups, scheduled off-site copies, retention, restore rehearsal, disaster recovery and rollback.
- Safe Update Center/add-on registry with compatibility checks, signed/allow-listed updates, backup-before-update and rollback; never execute arbitrary uploaded plugin code.

### Release and integration acceptance gates

- Laravel 12 / PHP 8.3+ / MySQL or compatible MariaDB remains the canonical stack. Do not replace it with the separately uploaded Node/Express prototype.
- Preserve users, courses, enrollments, payments, attendance, branches, progress and purchased digital products with additive, reversible migrations.
- Each feature needs functional UI and backend, authorization, validation, error states, translations, accessibility, relevant audit logging, automated tests and deployment documentation.
- Keep paid course/eBook/lesson files private; verify payments server-side; use signed/idempotent callbacks, CSRF/XSS/IDOR protection, rate limits, secure sessions and least-privilege credentials.
- Third-party services remain planned/blocked until official provider documentation, credentials, country/currency eligibility, sandbox tests and monitoring are confirmed. Never commit secrets.
- Before production: fresh-install and upgrade tests on staging, browser/mobile accessibility checks, PHP 8.3 + MySQL/MariaDB compatibility, exact-head CI and CodeQL, payment sandbox acceptance, backup/restore rehearsal, rollback plan and owner acceptance.
- Merge, production deployment, live payment activation and app-store release each require explicit owner approval. No open PR or roadmap checkbox authorizes release.

## First production administrator

Student registration only creates student accounts. After configuring the production database, create and verify the first admin through Tinker using a unique password:

```bash
php artisan tinker
```

```php
$admin = App\Models\User::create([
	'name' => 'Site Administrator',
	'email' => 'your-admin@your-domain.example',
	'password' => 'replace-with-a-unique-strong-password',
	'role' => 'admin',
]);
$admin->markEmailAsVerified();
```

Replace the example values before running the commands. Passwords are hashed by the user model.

## Tests and assets

```bash
vendor/bin/phpunit
npm ci
npm run build
```

The GitHub Actions workflow runs tests on PHP 8.3 with MySQL and builds Vite assets. Production does not require Node after `public/build` is generated.
