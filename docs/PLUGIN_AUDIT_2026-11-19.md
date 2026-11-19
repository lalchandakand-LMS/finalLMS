# Alom's English Center LMS — Plugin and Security Autopsy

**Audit date:** 2026-11-19  
**Repository:** lalchandakand-LMS/finalLMS  
**Main snapshot:** 58925acc054fcc0da8553497c48b2469acd7ff76  
**Scope:** executable first-party plugins, the declarative module catalogue, plugin lifecycle/runtime, authentication, Guardian privacy, live classes, branch scoping, payment callbacks/refunds, private eBook access, installer and updater boundaries.

## Executive result

The repository has a meaningful signed-plugin foundation, but it is not yet accurate to call every LMS module an independently installable plugin.

- plugins/aec/ contains **7 executable first-party packages**.
- The other **56 top-level module directories** under plugins/ contain metadata_only catalog manifests. Their Laravel controllers, models, routes, views and migrations still live in the application. They can describe the module catalogue, but cannot be installed, upgraded or uninstalled independently.
- The plugin runtime requires signature and installed-file integrity verification, private package paths, explicit Admin activation and controlled migrations. Plugins are trusted PHP code, **not sandboxed code**; a signed plugin has application-level privileges.
- This pass found and remediated several security/operational defects through merged PRs, including administrator MFA in PR #341. Flash Sale extraction remains open in PR #277: its current exact-head Laravel CI and CodeQL are green, but GitHub branch protection still blocks merge because the earlier write-access review requesting changes has not been superseded.
- This is a source review plus automated regression/CI review. It is **not** a production penetration test, provider certification, staging acceptance or proof of release readiness.

## First-party executable plugin autopsy

| Package | Reviewed behavior | Result / follow-up |
|---|---|---|
| aec.universal-ai-assistant | Encrypted provider key storage, role-aware context construction, scoped student/course/batch/assignment/exam data, request/token budgets and provider endpoint validation. | Provider endpoint custom-port DNS-pinning gap fixed in merged PR #339. Admin must still disclose external AI data processing and configure provider allowlists/budgets; test against a staging provider. |
| aec.board-question-solver | Student-owned request history, server-side provider budget reservations, image/question handling and dependency on Universal AI Assistant. | A dependent entrypoint could require dependency PHP before the dependency's own integrity check because runtime loaded alphabetically. Fixed by two-phase package preflight and dependency-topological loading in merged PR #337. |
| aec.learn-earn | Server-side exam attempt snapshots, ownership checks, attempt locking, server scoring, paid entitlement and gateway-backed payment state. | No new critical defect confirmed in the reviewed paths. Rehearse duplicate callbacks, checkout failure/retry, refund and entitlement reconciliation against the official payment sandbox before launch. |
| aec.phonetics-practice | Admin-managed word/sentence content, private audio storage, course-enrollment-scoped student access and separate pronunciation/audio fields. | No new critical defect confirmed in the reviewed paths. Complete browser/device testing for private audio playback, speech recognition permissions, unsupported browsers and accessibility. |
| aec.popup-ads | Rich HTML sanitizer, safe URL normalization, public campaign filtering, signed tracking endpoints and consent-aware aggregate event tracking. | No new critical defect confirmed in the reviewed paths. Complete browser testing for frequency caps, consent behavior, mobile layout, keyboard access and campaign targeting. |
| aec.student-tracking | Student/Teacher/Guardian/Branch Manager scopes through shared resource-access policies, risk indicators, action records and bounded CSV export with formula-injection protection. | **Rollback data-loss defect found:** the risk-action migration dropped its audit table unconditionally. Fix and regression test are in PR #347; exact-head CI/CodeQL are pending. Confirm institutional retention policy and branch/guardian access in staging. |
| aec.bcs-bank-admission-question-bank | Admin-curated question bank and paper/question-set workflows. | Manifest declared unsupported type: education, so the install validator rejected it. Changed to supported type: module and added a test that validates every executable first-party manifest and its dependencies in merged PR #338. |

### Flash Sale package — not yet merged

PR #277 moves Flash Sale functionality into its own package and includes negative-marking work. Current head: `66af33148924ca9591f536000ca9ff2e51d36c52`; base is the current main snapshot `58925acc054fcc0da8553497c48b2469acd7ff76`. The earlier exact-head Laravel CI (test + cPanel package) and CodeQL passed; CI is rerunning on the new cross-channel payment guard head, with CodeQL already green. The PR is ready for review, but GitHub rejected the merge attempt because a write-access reviewer still has an outstanding `CHANGES_REQUESTED` review. Its current branch includes the latest main security fixes.

**Do not merge or call it release-ready yet.** The standalone signed distributable, trusted release-key provisioning, fresh integration checks on current main, cPanel staging install/upgrade/deactivate/uninstall/rollback rehearsal and browser acceptance remain outstanding. The migration intentionally refuses destructive rollback while awarded negative marks remain. CI exercised that refusal against SQLite and MariaDB/MySQL. The PR also adds row locking/revalidation for Flash Sale capacity during mobile cart checkout and rejects duplicate pending cart payments; its FlashSaleTest regression suite passes.

## Plugin platform findings

### Fixed and merged

1. **Dependency integrity bypass — PR #337.** Runtime now preflights signatures, manifest identity, package paths and complete installed-file hashes for all active packages before requiring any entrypoint. It then loads dependencies before dependents and fails closed for missing, invalid, cyclic or failed dependencies.
2. **Invalid executable manifest — PR #338.** BCS Question Bank now uses a supported manifest type; a regression test scans all executable first-party packages and verifies their canonical path/key/version and resolvable dependencies.
3. **AI provider DNS pinning — PR #339.** HTTPS provider URLs are restricted to port 443, where validated DNS answers are pinned. Userinfo, query strings and fragments are rejected both at save time and request time.
4. **Plugin code remains trusted code.** Signature verification and integrity checks protect package provenance and tampering; they do not isolate PHP execution. Only install reviewed, signed packages from trusted release keys. Never place the private signing key in the web root or repository.
5. **Lifecycle and data safety.** Upload is not activation; activation is explicit. Plugin migrations are controlled. Uninstall/rollback must not discard user data; where data exists, preserve/export it and follow the guarded migration behavior.

### Catalogue gap — 56 modules are not executable plugins

Top-level directories such as course_management, live_classes, exam_management, attendance, payments, sms, gmail, guardian_portal, branch_management and others currently contain metadata_only manifests. Their implementation remains in the Laravel application. This is a catalogue and module-toggle foundation, not independent plugin packaging.

To meet the requested install/activate/deactivate/update/uninstall model for every module, perform a deliberate extraction program:

1. **Contract and dependency inventory:** map routes, controllers, models, migrations, jobs, events, views, policies, settings, permissions, API endpoints, storage and module dependencies for each module.
2. **Low-coupling integrations first:** extract SMS, email/Gmail, OTP, AI provider adapters, push notifications and marketing integrations behind stable interfaces.
3. **Learning workflow modules next:** extract phonetics, question banks, certificates, support, reporting and content tools while preserving existing URLs and records.
4. **Core modules last:** courses, lessons, batches, enrollments, attendance, assignments, exams, live classes, finance and payments have shared database and authorization dependencies; do not split them until migration ownership, cross-plugin contracts and data-retention behavior are tested.
5. **Release lifecycle:** provide signed packages, dependency resolution, Admin preflight, permission grants, install/update/deactivate/uninstall, data export, health checks, backup, rollback and cPanel staging tests for every extracted module.

Do not generate empty wrapper packages and label them complete. A module becomes independently installable only when its executable code, migrations, permissions, routes/jobs and tests are actually packaged and lifecycle-tested.

## Whole-project security sweep

| Area | Review result |
|---|---|
| Guardian relationship privacy | Pending link requests previously revealed whether a verified email belonged to a student and displayed identity before consent. Generic responses, pending identity hiding and stale active-link filtering merged in PR #336. |
| Live classes | Authenticated Guardian/Branch Manager/Accountant roles previously fell through an unfiltered schedule query; meeting links could be exposed. Role restriction, current linked-student scope and no Guardian live-link rendering merged in PR #340. |
| Administrator authentication | Password-only admin login bypassed the trusted-device MFA middleware. PR #341 is merged in current main and adds verified-email bootstrap, TOTP, one-time recovery codes, encrypted secrets, replay protection, account-level rate limits and forced invalidation of old admin sessions. Code is merged; staging enrollment, recovery and old-session invalidation are still unverified. |
| Mobile API | Student/Teacher tokens are device-bound; docs/tests were reconciled with the actual Teacher trusted-device policy in merged PR #334. Admin API login is not part of the mobile Student/Teacher login flow. |
| Branch Manager access | Reviewed batch/students/attendance paths use branch scope and teacher assignment checks. Existing tests intentionally permit a student with an enrollment in a branch batch to appear in that branch's workspace; confirm this matches transfer policy and keep guardian access tied to current approved links. |
| Payments/refunds | bKash callback flow verifies provider state/amount server-side, uses event/payment locking and idempotent reconciliation. Refund stale-state overwrite guard merged in PR #332. Official merchant sandbox and live callbacks remain unverified. |
| Private eBook access | Download path checks user ownership, completed order, revocation, expiry and remaining downloads under a locked permission record. Private proof/file delivery is not public catalog access. Complete staging tests for expiry, revocation, concurrency and storage permissions. |
| Installer/updater | Source review found private staging, archive path/symlink/size controls and signed release checks. AdminUpdateController currently supports signed manifest checks, package download, private extraction, activation preflight and pre-activation backup, but has no release activation or release rollback endpoint. BackupController separately supports HMAC-verified backup restore under an exclusive operation lock with a retained pre-restore safety snapshot; that is not an automated release rollback. Implement atomic release activation, migration orchestration, health checks and rollback before calling the Update Center complete. |
| Sessions/device revocation | Device sessions are tracked and revocation is checked on protected requests. PR #341 additionally forces old admin password-only sessions to reauthenticate; legacy non-admin sessions retain the existing grace behavior until sign-out/expiry. |

## Open release gates

- [ ] Rehearse merged administrator MFA on isolated staging: verified-email bootstrap, TOTP setup/login, replay rejection, recovery-code one-time use, lost-factor owner recovery and old-session invalidation.
- [ ] Obtain a fresh exact-head approval that supersedes PR #277's outstanding changes-requested review; then complete signed package and cPanel staging lifecycle gates. Do not merge based on CI alone.
- [ ] Merge PR #347 only after exact-head Laravel CI and CodeQL pass; verify the Student Tracking audit row survives a refused migration rollback on CI and disposable MySQL/MariaDB staging.
- [ ] Create isolated HTTPS staging subdomain and separate database. The last owner update reported neither was available.
- [ ] Test MySQL/MariaDB migration/rollback, cPanel package install/upgrade, private storage, queue/cron, SMS/email delivery, bKash sandbox, live class join/recording and browser/mobile accessibility.
- [ ] Add recent step-up MFA for high-risk admin actions (refunds, plugin install/activation/uninstall, backup restore and updater rollback). Backup restore currently requires password confirmation and a safety snapshot, but not a fresh MFA challenge.
- [ ] Begin the 56-module extraction program with an explicit dependency/contract inventory; do not mark catalogue manifests as installable plugins.
- [ ] Perform a separate staging penetration test after the above changes. No production penetration test has been performed in this audit.

**Production deployment remains blocked.** No production migration, live payment activation, plugin activation or hosting-file deployment was performed by this audit.


## Complete declarative-module inventory

The repository tree and `FirstPartyPluginManifestTest` were checked against `config/modules.php`. The following **56** directories each contain only a `plugin.json` catalogue manifest on current main; none has an executable entrypoint or independently packaged controllers, views, jobs or migrations. They are therefore module-registry entries, not installable Plugin Manager packages.

| Domain | Metadata-only module keys |
|---|---|
| Academic, learning and portals | `academic_levels`, `assignment`, `attendance`, `batch_management`, `certificates`, `course_builder`, `course_content_studio`, `course_management`, `exam_management`, `lesson_management`, `live_classes`, `student_management`, `student_portal`, `teacher_management`, `guardian_management`, `guardian_portal`, `branch_management`, `staff_management` |
| Website, content and growth | `ai_builder`, `ai_developer`, `analytics`, `blog_post_builder`, `carousel_builder`, `content_store`, `coupons_promotions`, `marketing`, `media_library`, `meta_ads`, `meta_pixel`, `page_builder`, `seo`, `slider`, `slider_builder`, `website_cms` |
| Finance and operations | `expense_management`, `fee_management`, `finance`, `payments`, `refund_management`, `reporting`, `storage`, `support_center` |
| Communications and integrations | `communications`, `email`, `email_otp`, `gmail`, `integrations`, `mobile_api`, `mobile_verification`, `notice_board`, `otp_verification`, `push_notifications`, `sms`, `sms_otp`, `student_mobile_app`, `instructor_mobile_app` |

The seven executable packages reviewed separately are `aec.universal-ai-assistant`, `aec.board-question-solver`, `aec.learn-earn`, `aec.phonetics-practice`, `aec.popup-ads`, `aec.student-tracking`, and `aec.bcs-bank-admission-question-bank`. Flash Sale becomes an eighth only after PR #277 is approved and merged.

## Current code-level findings from the continuation pass

- The executable package entrypoints declare portal routes through `PluginContext` and require manifest-declared permissions; the route-name prefix lookup regression is covered by `PluginContextTest` and the Flash Sale feature setup.
- The current package lifecycle is more capable than the older Manager documentation stated: signed package update, retained prior version, manual package rollback, pre-uninstall recovery snapshot and package restore are implemented and covered by `AdminPluginManagerTest`. This is not the same as an atomic LMS release updater rollback.
- Plugin migration execution and lifecycle transitions use database row locks and persistent migration states. A failed/interrupted migration blocks activation/rollback pending recovery. Filesystem concurrency and real cPanel failure recovery still require staging stress tests.
- The continuation pass found one additional data-loss defect in the Student Tracking migration: `down()` dropped risk-action audit history without checking for rows. PR #347 adds a fail-closed guard and a regression test. Its first CI run caught a redundant global `RuntimeException` import; that was removed. Latest head: `d60339fd0c054af5653ed89d75ffe7f9e8673809`; CodeQL passed and Laravel CI is rerunning. Other reviewed package migration `down()` methods explicitly preserve populated business/audit tables or refuse rollback. This is not proof that no defects exist; provider, browser, accessibility and production-like concurrency tests remain outstanding.
- PR #277's cart checkout changes add stable-order campaign row locking/revalidation and reject duplicate pending course payments. A further cross-channel audit found that pending combo reservations could be bypassed by direct manual/bKash checkout, or vice versa. PR #277 now adds a shared `PendingCoursePaymentGuard` across web manual, bKash, mobile cart and combo checkout, plus tests for direct/combo overlap. Exact-head CI is rerunning after these additions; merge remains blocked by the outstanding write-access review state.

## Recommended extraction order for the 56 metadata-only modules

Do not convert all 56 modules in one release. Keep `core` and `identity` protected, and preserve the current Module Registry as a compatibility layer while each group is extracted and validated:

1. **Learning delivery:** course management, batch management, lesson management, attendance, assignment, exam management, certificates and course content. This is the highest-dependency group; it must preserve existing enrollment, result and attendance records.
2. **Portals and organizational scope:** branch management, student/teacher/guardian management and portals, staff management, instructor/student mobile app shells. Require branch/guardian/teacher isolation tests before each package is activatable.
3. **Finance and commerce:** fee management, payments, coupons/promotions, refunds, expense management, finance reports and eBook/content-store integrations. Require idempotency, reconciliation, refund, and pending-payment overlap tests.
4. **Communication and verification:** SMS, email, Gmail, OTP, mobile verification, notice board and push notifications. Keep provider secrets encrypted and test opt-in/consent, retries, quotas and delivery failure handling.
5. **Website and growth tools:** CMS, page/blog/slider/carousel builders, SEO, marketing, Meta integrations, analytics, media library and storage. Require upload validation, private-file access, CSRF and XSS tests.
6. **AI and integration builders:** AI builder/developer, integrations and support center. Require provider endpoint/DNS restrictions, per-role policy, cost limits, audit events and data-retention controls.

For every extraction: declare dependencies/portals/permissions, route ownership, signed package manifest, migration rollback guards, uninstall data-retention behavior, and a feature test suite. The module must remain in the old registry until the new package passes cPanel staging install/upgrade/deactivate/restore acceptance; never silently switch a live module to a package without a migration plan.
