# Alom's English Center LMS — Plugin and Security Autopsy

**Audit date:** 2026-11-19  
**Repository:** lalchandakand-LMS/finalLMS  
**Main snapshot:** 5b5796f5e9059246d5815224929007c4ad28a3fc  
**Scope:** executable first-party plugins, the declarative module catalogue, plugin lifecycle/runtime, authentication, Guardian privacy, live classes, branch scoping, payment callbacks/refunds, private eBook access, installer and updater boundaries.

## Executive result

The repository has a meaningful signed-plugin foundation, but it is not yet accurate to call every LMS module an independently installable plugin.

- plugins/aec/ contains **7 executable first-party packages**.
- The other **56 top-level module directories** under plugins/ contain metadata_only catalog manifests. Their Laravel controllers, models, routes, views and migrations still live in the application. They can describe the module catalogue, but cannot be installed, upgraded or uninstalled independently.
- The plugin runtime requires signature and installed-file integrity verification, private package paths, explicit Admin activation and controlled migrations. Plugins are trusted PHP code, **not sandboxed code**; a signed plugin has application-level privileges.
- This pass found and remediated several security/operational defects through merged PRs. One high-severity admin MFA remediation remains open in PR #341. Flash Sale extraction remains gated in PR #277.
- This is a source review plus automated regression/CI review. It is **not** a production penetration test, provider certification, staging acceptance or proof of release readiness.

## First-party executable plugin autopsy

| Package | Reviewed behavior | Result / follow-up |
|---|---|---|
| aec.universal-ai-assistant | Encrypted provider key storage, role-aware context construction, scoped student/course/batch/assignment/exam data, request/token budgets and provider endpoint validation. | Provider endpoint custom-port DNS-pinning gap fixed in merged PR #339. Admin must still disclose external AI data processing and configure provider allowlists/budgets; test against a staging provider. |
| aec.board-question-solver | Student-owned request history, server-side provider budget reservations, image/question handling and dependency on Universal AI Assistant. | A dependent entrypoint could require dependency PHP before the dependency's own integrity check because runtime loaded alphabetically. Fixed by two-phase package preflight and dependency-topological loading in merged PR #337. |
| aec.learn-earn | Server-side exam attempt snapshots, ownership checks, attempt locking, server scoring, paid entitlement and gateway-backed payment state. | No new critical defect confirmed in the reviewed paths. Rehearse duplicate callbacks, checkout failure/retry, refund and entitlement reconciliation against the official payment sandbox before launch. |
| aec.phonetics-practice | Admin-managed word/sentence content, private audio storage, course-enrollment-scoped student access and separate pronunciation/audio fields. | No new critical defect confirmed in the reviewed paths. Complete browser/device testing for private audio playback, speech recognition permissions, unsupported browsers and accessibility. |
| aec.popup-ads | Rich HTML sanitizer, safe URL normalization, public campaign filtering, signed tracking endpoints and consent-aware aggregate event tracking. | No new critical defect confirmed in the reviewed paths. Complete browser testing for frequency caps, consent behavior, mobile layout, keyboard access and campaign targeting. |
| aec.student-tracking | Student/Teacher/Guardian/Branch Manager scopes through shared resource-access policies, risk indicators, action records and bounded CSV export with formula-injection protection. | No new critical defect confirmed in the reviewed paths. Confirm institutional retention policy, who can see risk notes, and branch/guardian access in staging. |
| aec.bcs-bank-admission-question-bank | Admin-curated question bank and paper/question-set workflows. | Manifest declared unsupported type: education, so the install validator rejected it. Changed to supported type: module and added a test that validates every executable first-party manifest and its dependencies in merged PR #338. |

### Flash Sale package — not yet merged

PR #277 moves Flash Sale functionality into its own package and includes negative-marking work. Exact-head CI and CodeQL passed on its current branch, but it remains a draft with a prior changes-requested review, and its base is behind the current main branch.

**Do not merge or call it release-ready yet.** The standalone signed distributable, trusted release-key provisioning, fresh integration checks on current main, cPanel staging install/upgrade/deactivate/uninstall/rollback rehearsal and browser acceptance remain outstanding. The migration intentionally refuses destructive rollback while awarded negative marks remain.

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
| Administrator authentication | Password-only admin login bypassed the trusted-device MFA middleware. PR #341 adds verified-email bootstrap, TOTP, one-time recovery codes, encrypted secrets, replay protection, account-level rate limits and forced invalidation of old admin sessions. **Still open; do not claim fixed until PR #341 merges and staging enrollment/recovery succeeds.** |
| Mobile API | Student/Teacher tokens are device-bound; docs/tests were reconciled with the actual Teacher trusted-device policy in merged PR #334. Admin API login is not part of the mobile Student/Teacher login flow. |
| Branch Manager access | Reviewed batch/students/attendance paths use branch scope and teacher assignment checks. Existing tests intentionally permit a student with an enrollment in a branch batch to appear in that branch's workspace; confirm this matches transfer policy and keep guardian access tied to current approved links. |
| Payments/refunds | bKash callback flow verifies provider state/amount server-side, uses event/payment locking and idempotent reconciliation. Refund stale-state overwrite guard merged in PR #332. Official merchant sandbox and live callbacks remain unverified. |
| Private eBook access | Download path checks user ownership, completed order, revocation, expiry and remaining downloads under a locked permission record. Private proof/file delivery is not public catalog access. Complete staging tests for expiry, revocation, concurrency and storage permissions. |
| Installer/updater | Source review found private staging, archive path/symlink/size controls and signed release checks. The Update Center is not yet a production-safe one-click updater: signed release-key provisioning, backup/restore, atomic activation, migration orchestration, health checks and tested rollback still require staging completion. |
| Sessions/device revocation | Device sessions are tracked and revocation is checked on protected requests. PR #341 additionally forces old admin password-only sessions to reauthenticate; legacy non-admin sessions retain the existing grace behavior until sign-out/expiry. |

## Open release gates

- [ ] Merge PR #341 only after exact-head Laravel CI and CodeQL pass; rehearse verified-email bootstrap, TOTP setup/login, replay rejection, recovery-code one-time use, lost-factor owner recovery and old-session invalidation on isolated staging.
- [ ] Complete PR #277 signed package and staging lifecycle gates; do not merge based on CI alone.
- [ ] Create isolated HTTPS staging subdomain and separate database. The last owner update reported neither was available.
- [ ] Test MySQL/MariaDB migration/rollback, cPanel package install/upgrade, private storage, queue/cron, SMS/email delivery, bKash sandbox, live class join/recording and browser/mobile accessibility.
- [ ] Add step-up MFA for high-risk admin actions (refunds, plugin install/activation/uninstall, backup restore and updater rollback).
- [ ] Begin the 56-module extraction program with an explicit dependency/contract inventory; do not mark catalogue manifests as installable plugins.
- [ ] Perform a separate staging penetration test after the above changes. No production penetration test has been performed in this audit.

**Production deployment remains blocked.** No production migration, live payment activation, plugin activation or hosting-file deployment was performed by this audit.
