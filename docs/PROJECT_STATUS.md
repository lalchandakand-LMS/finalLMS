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


## Live GitHub recheck — 2026-11-10 (latest snapshot)

This section supersedes older status statements above where they conflict. Snapshot was fetched from GitHub on 2026-11-10; re-fetch before the next release decision.

### Open PRs and exact-head CI evidence

All PRs below were fetched as open and unmerged. The listed workflow conclusions belong to the exact PR head SHA returned by GitHub during this recheck:

| PR | Current head SHA | State | Exact-head checks |
|---|---|---|---|
| [#65 Homepage Builder](https://github.com/lalchandakand-LMS/finalLMS/pull/65) | `3f98f7c9fbc137a7bb2fed956054c128b2f7e662` | Open, ready for review, mergeable | Laravel CI success; CodeQL success |
| [#74 Course Content Studio/eBook foundation](https://github.com/lalchandakand-LMS/finalLMS/pull/74) | `9111c10fe8dac2572b7ec213d208adfbce585195` | Open, draft, mergeable | Laravel CI success; CodeQL success |
| [#72 Lesson video management](https://github.com/lalchandakand-LMS/finalLMS/pull/72) | `a0bb34f71caf0b084486ff6fcd2d88bcec168a71` | Open, draft, mergeable | Laravel CI success; CodeQL success |
| [#71 Flutter app foundations](https://github.com/lalchandakand-LMS/finalLMS/pull/71) | `3dc2bf61500d9c81053c362b77d963e53281e95b` | Open, draft, mergeable | Flutter mobile apps CI success; Laravel CI success |
| [#69 Exam autosave/resume](https://github.com/lalchandakand-LMS/finalLMS/pull/69) | `4d90ee0ecaa81555e8333d950f0281838e8f833f` | Open, draft, mergeable | Laravel CI success |
| [#68 Question bank/exam integrity](https://github.com/lalchandakand-LMS/finalLMS/pull/68) | `e40c781b13730bfef58e1930911e274d6c7c3cea` | Open, draft, mergeable | Laravel CI success; CodeQL success |
| [#67 Assignment workflow](https://github.com/lalchandakand-LMS/finalLMS/pull/67) | `0d1b4f43e4ce933310152cf59b797f0e6dbeee8f` | Open, draft, mergeable | Laravel CI success; CodeQL success |
| [#66 bKash callback verification](https://github.com/lalchandakand-LMS/finalLMS/pull/66) | `1ad7009cb2aa6d17b1e893d233b676aa0fdef8b0` | Open, draft, mergeable | Laravel CI success; CodeQL success |
| [#77 AI handoff documentation](https://github.com/lalchandakand-LMS/finalLMS/pull/77) | `5296a720f6ffe1bb68ee6887dc7a1b2160f321fa` | Open, draft, mergeable | Laravel CI and package build success; CodeQL success |
| [#75 Uploaded ZIP security audit](https://github.com/lalchandakand-LMS/finalLMS/pull/75) | `4482c2ea1e55538fd9394a74eb6f62c9a1e3bb50` | Open, draft, mergeable | Laravel CI success; CodeQL success |

These checks prove the named automated jobs passed for those commits. They do **not** prove review approval, integration with main, staging acceptance, provider sandbox acceptance, or production readiness.

### Integration and dependency risks discovered

- Several open PRs target other feature branches rather than `main`, including #69, #60, #59, #58, #57, #56, #55, #54, #53, #52, #51, #50, #49, #48, #45, #44, #43 and #42. They are stacked work, not independent mainline releases. Before merging any stack, map the full base/head dependency chain and validate the combined result; do not merge a child branch before its required parent is integrated.
- PRs #47 and #46 were open but GitHub reported `mergeable=false` at this snapshot. Resolve/rebase conflicts only on their feature branches after reviewing changed files; do not force-push or rewrite unrelated history.
- PR #77 is still a draft and has not been merged. The handoff docs are therefore not yet on main.
- The current `main` tree snapshot contains no Guardian model, guardian relationship migration, Guardian controller or Guardian panel route. Treat Guardian as not yet implemented on main; do not mark the fifth panel complete based on the architecture documents alone.
- The current main tree includes a Branch model, branch-manager role and branch-scoped routes/tests. That is a branch-manager foundation, not proof of a fully complete Branch Panel with all operations and granular permissions.
- Issue #40 has feature checklist items marked [x], but those checkboxes are not acceptance evidence. Verify each feature against actual code, tests and provider acceptance before calling it complete.

### Updated immediate work order

1. Reconcile and safely integrate the existing green PR stacks in dependency order, after full diff/review and exact-head combined CI. Owner has pre-authorized safe PR merges; do not ask for routine merge approval. Merge only after complete review, exact-head checks and dependency/conflict validation.
2. Complete the Guardian panel as a focused feature: verified guardian-student relationship, multiple children, safe child switcher, read-only linked-child views, server-side isolation and negative IDOR tests across each sensitive route/API/export/download/notification.
3. Finish the Student course-learning workflow and Teacher workflow, verifying private lesson media, progress/resume and access boundaries.
4. Complete Branch Panel operations and branch isolation tests; keep one unified Admin.
5. Finish admissions, payment reconciliation, eBook entitlement, live-provider integrations, full Bangla translations and Flutter end-to-end flows.
6. Establish isolated cPanel staging (subdomain + separate DB) and test install/upgrade/backup/restore there before any production change.

### Explicit unverified items

No real cPanel staging install, production deployment, official bKash/Nagad/SSLCommerz merchant acceptance, Zoom/Jitsi provider API acceptance, Flutter store release, or end-to-end guardian workflow is evidenced by this GitHub snapshot.

### Live update-system work — 2026-11-10

- PR #78 (Admin Update Center specification) was merged to main as 1bf77fb534fff77d2662724416ee9aafde35cb79. The spec is in docs/ADMIN_UPDATE_SYSTEM.md; it distinguishes no-code content updates from signed application releases and records security/recovery acceptance gates.
- PR #79 (first signed-release workflow attempt) was closed as superseded after actionlint/ShellCheck found an unsafe ! grep shell guard. The issue was fixed on a clean branch; no failed attempt was merged.
- PR #80 (signed immutable release pipeline) was merged to main as ce3224862b9fcb917d83bf997b69eb00dac126cc after exact-head Laravel CI (test and package jobs) and CodeQL all passed. It adds a tag-triggered PHP 8.3/Laravel 12 release build, exact-tag MySQL-backed tests, SHA-256 package manifest, RSA detached signature, fail-closed signing secret check, public-key fingerprint and release setup guide.
- The release signing key secret RELEASE_SIGNING_PRIVATE_KEY is not configured/verified in repository settings through the available integration. A repository owner must provision it securely before a tagged signed release can run. Never put the private key in Git, chat or production cPanel.
- The release workflow publishes signed artifacts only. The Admin Update Center, production public-key pinning, staged package activation, migration orchestration, rollback and real cPanel staging acceptance are not yet implemented.
- Current immediate update-system sequence: provision the signing key securely; implement public-key-pinned manifest verification and Admin-only update status/preflight; implement staged activation and health checks; implement recovery/rollback; then rehearse install/upgrade/rollback on isolated cPanel staging.
