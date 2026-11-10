# Project Status — Alom's English Center LMS

Last consolidated: 2026-11-10
Repository: https://github.com/lalchandakand-LMS/finalLMS
Production domain: https://alomsenglishcenter.com

## Status vocabulary

- **Implemented (code exists):** implementation found in repository; not automatically production-accepted.
- **CI verified:** exact current commit passed named required CI jobs.
- **Staging verified:** tested on a real staging environment matching deployment.
- **Provider verified:** tested against provider sandbox/official integration.
- **Production accepted:** owner-approved and deployed after release gates.
- **In progress:** active branch/PR or incomplete workflow.
- **Planned:** approved scope without complete implementation.
- **Blocked:** cannot proceed safely until stated dependency is met.

Never promote a feature from one status to another without evidence.

## Architecture decision

Five operational panels: Admin (one unified admin; no separate Super Admin), Branch (separate and branch-scoped), Teacher, Student, Guardian. This follows the owner's later explicit direction and Issue #40; older Issue #38's four-portal proposal must be reconciled. See `docs/DECISION_LOG.md`.

## Repository implementation snapshot

The current main README lists a number of foundations as implemented: registration/email verification/Twilio OTP; role foundations; courses/lessons/enrollments/certificates/manual payment review; branch/batch workflows; attendance; assignments/private attachments; live-class scheduling; MCQ exams/question banks; course catalog and lesson progress; invoices/installments/refunds/expenses/reports; CMS/blog/media; installer; backups; and partial Bangla/English translations.

**Important:** this is the README's implementation inventory, not independent verification that every workflow passes tests or is production ready. Inspect source and CI before changing status.

## Active / recent PR workstreams (last known context; re-fetch GitHub before relying)

| PR | Workstream | Last known note |
|---|---|---|
| [#76](https://github.com/lalchandakand-LMS/finalLMS/pull/76) | README roadmap and Guardian scope | Documentation-only PR created; confirm current state/head/CI. |
| [#75](https://github.com/lalchandakand-LMS/finalLMS/pull/75) | Uploaded ZIP security audit | Audit document; not production code remediation. |
| [#74](https://github.com/lalchandakand-LMS/finalLMS/pull/74) | Course Content Studio, categories, flash sales, question sets, PDF/eBook | Earlier CI was green on a previous head; re-run/check exact current SHA. |
| [#72](https://github.com/lalchandakand-LMS/finalLMS/pull/72) | Advanced lesson video management | Host upload limits and protected playback need staging validation. |
| [#71](https://github.com/lalchandakand-LMS/finalLMS/pull/71) | Student and Instructor Flutter foundations | Not equivalent to complete apps or store releases. |
| [#69](https://github.com/lalchandakand-LMS/finalLMS/pull/69) | Exam autosave/resume/timed auto-submit | Verify exact current head. |
| [#68](https://github.com/lalchandakand-LMS/finalLMS/pull/68) | Question Bank and exam integrity | Verify exact current head. |
| [#67](https://github.com/lalchandakand-LMS/finalLMS/pull/67) | Assignment workflow and grading | Verify exact current head. |
| [#66](https://github.com/lalchandakand-LMS/finalLMS/pull/66) | bKash server-side callback verification | Do not infer live merchant activation. |
| [#65](https://github.com/lalchandakand-LMS/finalLMS/pull/65) | 15 homepage presets and visual builder | Last recorded head: `3f98f7c9fbc137a7bb2fed956054c128b2f7e662`; Laravel CI run #2647 and CodeQL #390 were in progress at last check. Re-fetch current status. |

## Known completed/verified evidence from prior checks (historical; re-check before release)

- Course Content Studio PR #74: earlier exact head `9111c10fe8dac2572b7ec213d208adfbce585195`; earlier Laravel CI run #2639 passed (242 tests / 1,099 assertions), package job and CodeQL run #2639 passed. This is historical evidence only; confirm current PR head still matches before relying on it.
- PR #65: hero slider work was added on head `3f98f7c9fbc137a7bb2fed956054c128b2f7e662`; last observed Laravel CI run #2647 and CodeQL #390 were running, not passed. Re-check current runs.
- PR #75: uploaded ZIP security audit commit `4482c2ea1e55538fd9394a74eb6f62c9a1e3bb50`; documentation/audit only.
- Main branch baseline previously recorded as `e8ec871f230fcf948938eabc785e0ff03370480c`, but current main has moved. Always fetch fresh SHA.

## Priority backlog

### P0 — release safety and current branch health
- [ ] Fetch all open PRs, current heads, review status, required checks and failing logs; remove stale PR snapshots from docs.
- [ ] Verify exact-head Laravel CI, CodeQL, migrations and cPanel package for each release-candidate PR.
- [ ] Verify migration order and compatibility on clean MySQL/MariaDB and upgrade from representative existing schema.
- [ ] Run cPanel package install/upgrade on staging; validate writable storage/cache, queue/cron and private file paths.
- [ ] Complete security review: CSRF, XSS, SQL injection, mass assignment, IDOR, authorization, sessions, rate limits, uploads, secrets, payment callbacks and private files.
- [ ] Test backup/restore, offsite retention, rollback and disaster recovery.

### P1 — core learning experience
- [ ] Complete Student Portal course learning: catalog/detail, enrollment, My Courses, protected player, lesson navigation, progress/resume, resources and error states.
- [ ] Complete Teacher workflows for lessons, classes, assignments, exams, attendance, grading and progress.
- [ ] Complete exams/question bank/assignment edge cases, autosave, retake/appeal and reports.
- [ ] Attendance correction/approval, student/guardian views and reporting.
- [ ] Certificate eligibility and public verification.
- [ ] Live provider APIs, attendance capture and recording sync (not merely meeting URLs).

### P2 — guardian, admissions and branch operations
- [ ] Guardian account creation/sign-in and verified student relationship mapping.
- [ ] Multiple children, child switcher, linked-child attendance/results/progress/fees/notices/communications.
- [ ] Enforce and test guardian isolation across all routes/APIs/exports/notifications.
- [ ] Admission application/review/approval, school/class/roll, verified mobile, consent-based photo and admission source.
- [ ] Separate Branch Panel with server-side branch scope, while keeping one unified Admin Panel.
- [ ] Granular permissions, branch transfer/assignment, staff roles and audit.

### P3 — commerce and institutional operations
- [ ] Course fees, invoices, installment plans, discounts/scholarships, receipts, dues/reminders, refunds, expenses and reconciliation.
- [ ] bKash official merchant sandbox and live readiness; implement Nagad/SSLCommerz only with official docs and sandbox.
- [ ] Cart/coupons/bundles/subscriptions/affiliate and purchase reporting.
- [ ] Secure PDF/eBook store with private storage and verified entitlements.
- [ ] Notification providers, templates, retries and delivery logs.
- [ ] Reports for admission, attendance, performance, revenue, dues, branch and teacher.

### P4 — website, mobile and extended modules
- [ ] Homepage builder 15 presets, section builder, slider/carousels, featured course/category, testimonials, flash sale, popups and banners.
- [ ] Full Bangla/English translations; accessibility and responsive QA.
- [ ] Student and Instructor Flutter app end-to-end flows, API security, device QA and release signing.
- [ ] SCORM, H5P, forum, bootcamp, team training, appointment booking, support desk and controlled add-on registry.
- [ ] AI Developer with read-only diagnostics, safe branch patch proposals, audit, secret redaction and cost limits.
- [ ] Scheduled offsite backups, full audit trail, safe Update Center and rollback.

## Hosting and launch blockers

- Owner's hosting provider: Web Host BD cPanel; domain and SSL reported active.
- PHP 8.3 reported available.
- No staging subdomain or separate staging DB was available in the last owner update.
- No confirmed staging install, production deployment, or live gateway acceptance is recorded here.
- Need create staging subdomain + isolated database before production acceptance; never test upgrades directly on the live database.
- Exact hosting plan/resource limits must be confirmed before large video upload or queue workloads.

## Next safe action

1. Re-fetch PR #65/#74/#76 and their current workflow runs.
2. Fix exact-head CI failures and stale documentation.
3. Continue the homepage builder and course learning workflows in focused PRs.
4. Start Guardian implementation only against the five-panel architecture and strict relationship/privacy acceptance tests.
5. Do not merge or deploy without explicit owner approval.
