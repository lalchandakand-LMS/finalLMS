# Project Status — Alom's English Center LMS

Last consolidated: 2026-11-14
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
| [#116](https://github.com/lalchandakand-LMS/finalLMS/pull/116) | Premium homepage/CMS, course catalog/posts/login, course combos and browser-device policy | Open Draft PR; re-fetch exact head and CI/CodeQL/package results before claiming verification. Do not merge or deploy under the current owner instruction. |

## Known completed/verified evidence from prior checks (historical; re-check before release)

- Course Content Studio PR #74: earlier exact head `9111c10fe8dac2572b7ec213d208adfbce585195`; earlier Laravel CI run #2639 passed (242 tests / 1,099 assertions), package job and CodeQL run #2639 passed. This is historical evidence only; confirm current PR head still matches before relying on it.
- PR #65: hero slider work was added on head `3f98f7c9fbc137a7bb2fed956054c128b2f7e662`; last observed Laravel CI run #2647 and CodeQL #390 were running, not passed. Re-check current runs.
- PR #75: uploaded ZIP security audit commit `4482c2ea1e55538fd9394a74eb6f62c9a1e3bb50`; documentation/audit only.
- Main branch baseline previously recorded as `e8ec871f230fcf948938eabc785e0ff03370480c`, but current main has moved. Always fetch fresh SHA.

## Current PR #116 implementation snapshot (2026-11-13)

- Admin CRUD for fixed-price course combos; public catalog/detail pages; course selection restricted to published courses without open batches.
- Paid combo checkout creates one manual payment plus an immutable purchased-course snapshot; Admin approval enrolls the student into all snapshot courses atomically. Free combos enroll immediately.
- Combo payment history is retained by archiving rather than deleting a combo; course deletion is blocked while combo or payment history references the course.
- Student browser login now enforces one approved browser device: first-device SMS OTP, additional/revoked-device Admin approval, revocation of previous approved devices, and server-side session invalidation.
- Student mobile API login now shares the approved-device registry: secure installation ID, SMS OTP, Admin approval for additional devices, device-bound bearer tokens and server-side token invalidation. Teacher API login remains password-based. Flutter client UI/secure-storage/polling and live SMS staging acceptance remain pending.
- Exact-head CI status must be checked from GitHub before moving this snapshot to CI-verified. No merge, deployment, production data mutation or live-payment activation is authorized by this workstream.

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
- [ ] Cart/coupons/subscriptions/affiliate and purchase reporting beyond the current fixed course-combo workflow.
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

The secure Admin Update Center has progressed beyond the initial design specification. The following implementation PRs are merged into `main`:

- PR #78 — Admin Update Center and safe-release specification (merge `1bf77fb534fff77d2662724416ee9aafde35cb79`).
- PR #80 — immutable, signed release pipeline (merge `ce3224862b9fcb917d83bf997b69eb00dac126cc`); release artifacts are built from immutable version tags and include a SHA-256 manifest plus RSA detached signature.
- PR #86 — Admin-only signed manifest checker, version compatibility checks, release history, pinned RSA public-key fingerprint, trusted asset URL checks and downgrade rejection (merge `11fe5d21302394357c3130636dacaa6e4eb61e60`).
- PR #87 — signed package download, SHA-256 verification, migration-set digest verification and private staging metadata (merge `6d9978cf171142126645a5e2a1d5e01ceab46bb8`).
- PR #88 — ZIP archive hardening against path traversal, duplicate/conflicting paths, secrets/runtime files, public executable payloads, symlinks and special files (merge `a3162ca3c675bd68a1219e73c7e47c50b86ae2e0`).
- PR #90 — extraction of verified packages into an isolated private release directory with restrictive permissions, disk-space checks and expiry cleanup; this does not activate code (merge `dfe0261b730740875577c87449a3c9720f52826b`).
- PR #91 — feature test confirming the private staging schema contains the metadata fields used by the package stager (merge `a00662edeb4ae67d1221a3574908a73cf6245cef`).
- PR #92 — accessible bilingual public sliding navigation; kept the admin navigation unchanged and completed the Bangla catalog for the new public drawer (merge `2c2c0a41c721fa3045d1ba4488d92a7cd6215b25`).
- PR #94 — durable Admin-only read-only activation preflight with server/filesystem/database/migration checks and an explicit verified-backup blocker (merge `a7dc5eebe372df3953a76729e44d9e290281d882`).
- PR #96 — private current-code snapshot plus recent HMAC-verified database/media backup, tied to a staged release and re-verified by preflight; also restores configured public header links in the bilingual drawer (merge `c07542a36656730bc2d91549cc95e90cf9dcf552`).
- PR #65 — 15 premium homepage presets and the visual homepage builder merged into `main` (merge `499d54d21cd65f893c8f890a2df9963457a09836`).

The latest PR #96 exact-head MySQL test suite, route/build checks, cPanel package assembly, and CodeQL all passed. PR #94's exact-head MySQL tests, cPanel package build and CodeQL also passed. Earlier PR #86–#92 checks are recorded in their respective GitHub Actions runs.

**Current capability:** Admin can check signed release metadata, see update history, download a signed package, validate its signed metadata/checksum/migration digest and archive structure, extract it into isolated private storage, create a private current-code snapshot, tie it to a recent HMAC-verified database/media backup, and run a durable read-only activation-readiness preflight. The code snapshot excludes `.env`, runtime storage, uploads, backups, logs, development metadata and composer bin symlinks. Preflight re-verifies both backup files and checks the staged release, application/document-root layout, private `.env` placement, writable directories, PHP extensions/version, MySQL connection/driver, pending migration names, filesystem device layout, disk space and symlink capability. It still blocks activation because the activation runner, controlled migration execution and tested restore/rollback path are not implemented. Expired staging artifacts are cleaned up when the preparation/extraction workflow runs.

**Still not implemented:** automatic database/media backup creation from the updater (Admin must first create one in Admin → Backups), complete impact review, persistent shared-storage wiring, update lock/maintenance orchestration, production code activation, controlled migration execution, post-activation health checks, durable installation progress logs, tested code restore/rollback, and real cPanel staging acceptance.

The release signing private key and public-key fingerprint still require secure owner provisioning/verification. Do not enable signed checks until the public key is installed outside the web root and its fingerprint independently verified. Do not deploy or run an update on `alomsenglishcenter.com` yet. The cPanel deployment guide remains explicit that manual backup/release procedures are required until activation/recovery and staging acceptance pass.

## Student mobile device authentication (2026-11-13)

- Student API login now uses a cryptographically random 32-byte installation ID stored by the Flutter app in platform secure storage.
- New/revoked devices require SMS OTP; additional devices require Admin approval through the existing Student Devices dashboard.
- Student bearer tokens are linked to the approved device record. Tokens without a current approved device are rejected; Admin approval/revocation invalidates existing mobile sessions.
- The Student Flutter app includes OTP entry, Admin-approval waiting, 15-second approval polling, secure token persistence, session restoration and logout. Teacher API login remains password-based.
- Exact-head verification on ff778f24eb4b7e1478eb23583ccea38b2a1bdee7: Laravel CI passed 309 tests / 1,608 assertions, migrations, route listing and frontend build; CodeQL passed; Flutter analyze and tests passed for both student and instructor apps.
- Still required before app distribution: configure production-like SMS credentials and perform end-to-end login, wrong/expired OTP, additional-device approval, revocation and logout tests on isolated staging. Do not test against production student accounts or activate/deploy this PR.
## eBook Store implementation — 2026-11-14

- **Merged to main:** PR [#124](https://github.com/lalchandakand-LMS/finalLMS/pull/124), squash commit `65c5e20b79bf9709ebd4a713552d9f531258cfe0` (source head `0aba691c320cdf07c0ed5e416ba356c840505aed`).
- Added a dedicated eBook catalog, admin product/category management, private PDF uploads, manual-reference checkout, private payment-proof review, student order/library pages and protected downloads.
- Paid orders remain pending until an Admin independently verifies payment and approves. Free eBooks receive immediate entitlement.
- Download authorization checks student ownership, completed order, revocation, expiry and remaining count. Active entitlements prevent repeated purchases from resetting a download limit; exhausted/expired entitlements may be purchased again.
- Product creation and category assignment are transactional. Purchased product files cannot be replaced; products with order history are archived rather than deleted. Private payment-proof responses are non-cacheable.
- **Validation:** exact source head `0aba691c320cdf07c0ed5e416ba356c840505aed` passed Laravel CI run #3923 and rerun #3924: 353 PHPUnit tests / 1,845 assertions, MySQL migrations, route listing and Vite build. cPanel package assembly/ZIP verification passed in both runs. CodeQL runs #762 and #763 passed. Artifact `AlomsEnglishCenter-cpanel` was uploaded (ID `14104506122`).
- No production deployment, production database migration, or live gateway activation has occurred; cPanel staging acceptance remains outstanding.
- **Not included:** official bKash/Nagad/SSLCommerz gateway verification, refunds/revocation workflow UI, cart/coupons, staging acceptance and production rollout. This PR uses manual payment verification only.



## Live GitHub recheck — 2026-11-14 (continuation)

This section records a fresh repository/PR recheck. Merge status is based on GitHub PR search; CI/staging claims are kept separate from merge status.

### Recently merged implementation

- [PR #65](https://github.com/lalchandakand-LMS/finalLMS/pull/65) — 15 responsive homepage presets, homepage visual builder, managed hero carousel, draft/publish and revision rollback. GitHub reports merged; recorded merge commit: 499d54d21cd65f893c8f890a2df9963457a09836.
- [PR #116](https://github.com/lalchandakand-LMS/finalLMS/pull/116) — premium homepage, admin-managed header/footer and navigation, custom Website Pages CMS, public Flash Sale page, course catalog/detail and course-combo/device-login workflows. GitHub reports merged. Its PR body still contains stale text saying “Draft and unmerged”; do not rely on that stale sentence for current merge status. The PR description records Laravel CI (309 tests / 1,608 assertions), CodeQL and both Flutter app analyze/test checks passed on head 81524c06af1d2bb72cdd885beb6b44453abab82a; re-check exact merge-head checks before any release decision.
- [PR #149](https://github.com/lalchandakand-LMS/finalLMS/pull/149) — secure private uploaded-lesson video streaming for the Student mobile app, byte-range seeking and authorization tests. Merged commit observed: 3277737451e3bef6e9f9e4635aed32ab15a0d679. Its commit message records 399 PHPUnit tests / 2,201 assertions, Flutter analyze/tests for both apps, CodeQL and Student/Instructor Android APK builds passed. App-store publication and staging SMS/device acceptance remain separate.
- [PR #152](https://github.com/lalchandakand-LMS/finalLMS/pull/152) — direct course-category assignments, course metadata/SEO/tags/outcomes/requirements/level/language/duration, admin filters and safe archive/deletion rules. Merged commit observed: da7e8449de6a6c8388a2cd46851f4f10397accae.
- [PR #153](https://github.com/lalchandakand-LMS/finalLMS/pull/153) — Student mobile course wishlist using the existing wishlist table, student-scoped API operations and app UI. Merged commit observed: a3b61fcbcb75e976c94860b06446a980c5aefa64.
- [PR #155](https://github.com/lalchandakand-LMS/finalLMS/pull/155) — role-scoped Teacher dashboard metrics and Student learning-progress insights with Bengali labels and feature tests. GitHub reports merged; commit observed: ffd2d95f0004870e4479e848080ceebbc82d11b5. The connector did not return check-run/status records for this SHA during this recheck; do not claim fresh exact-head CI from this status note alone.

### Homepage and navigation implementation check

- WebsitePageController supports draft/published page states, duplication as draft, menu label, show_in_menu and menu_order validation, and published-page rendering.
- The shared public layout includes partials.public-navigation; the public navigation drawer has close-on-link, backdrop and Escape-key handling.
- WebsiteBuilderController keeps homepage drafts separate from the published homepage, supports draft preview, explicit publish and revision rollback. Unsafe header/footer/slide/section URLs are filtered in preview.
- These are source-level checks, not a browser/responsive acceptance test. Confirm the owner's exact reference-image sections, menu ordering and Bangla/English rendering on desktop/mobile before calling visual acceptance complete.

### Current release blockers and next order

1. **Staging blocker:** the owner previously reported no staging subdomain or separate staging database. The cPanel staging runbook explicitly says to stop rather than use the live database. Create an isolated HTTPS staging subdomain and empty database before installation, upgrade, restore or release rehearsal.
2. Run fresh exact-head Laravel CI, CodeQL and cPanel package checks for the current main SHA before release; do not infer these from an earlier PR head.
3. Complete browser acceptance for the homepage/navigation, custom pages, student course player, teacher workflows and guardian privacy in both locales.
4. Verify backup/restore and recovery on staging. The Admin Update Center still cannot activate code or perform a tested rollback; do not advertise one-click updates or use it to update production.
5. Keep live gateway activation blocked until official merchant credentials, callback verification and sandbox acceptance are completed. Nagad direct API and SSLCommerz direct API are not implemented per the cPanel runbook.

No production deployment, production database mutation, live gateway activation or app-store publication is evidenced by this recheck.


## Verified continuation update (2026-11-14, after PR #156/#157)

- PR #157 — quiz competition compliance safeguard (free-entry skill-based competitions only; paid-entry and random winner selection remain disabled pending legal/provider confirmation) — merged to `main` at `6259142f7952ba1836ff512b93156008cd047a59`. Exact-head Laravel CI run #4401 and CodeQL run #854 both completed successfully.
- PR #156 — this project-status documentation refresh — merged to `main` as `8b3749dc1c5f21774785e09cfcc7cf0e1ab179ce`. Exact-head Laravel CI run #4397 and CodeQL run #853 both completed successfully.
- PR #158 — question-bank admin access and exam answer autosave/resume proposal — remains open and is not merge-ready. Its base is behind current `main`, GitHub currently reports mergeability false, and no exact-head CI/CodeQL runs were returned at the latest check. Review/rebase and fresh checks are required before considering merge. Do not assume its proposed changes are live.
- These merge records do not constitute staging/browser acceptance or production deployment. No production database or hosting files were changed.
