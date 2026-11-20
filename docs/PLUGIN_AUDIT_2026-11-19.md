# Alom's English Center LMS — Plugin and Security Autopsy

**Audit date:** 2026-11-20  
**Repository:** lalchandakand-LMS/finalLMS  
**Main snapshot:** 1dbb1c86e8ca3cd2f53a202805340c66c562e4e0  
**Scope:** executable first-party plugins, the declarative module catalogue, plugin lifecycle/runtime, authentication, Guardian privacy, live classes, branch scoping, payment callbacks/refunds, private eBook access, installer and updater boundaries.

## Executive result

The repository has a meaningful signed-plugin foundation, but it is not yet accurate to call every LMS module an independently installable plugin.

- plugins/aec/ contains **8 executable first-party packages**.
- The other **56 top-level module directories** under plugins/ contain metadata_only catalog manifests. Their Laravel controllers, models, routes, views and migrations still live in the application. They can describe the module catalogue, but cannot be installed, upgraded or uninstalled independently.
- The plugin runtime requires signature and installed-file integrity verification, private package paths, explicit Admin activation and controlled migrations. Plugins are trusted PHP code, **not sandboxed code**; a signed plugin has application-level privileges.
- This refreshed pass includes the merged Admin TOTP MFA remediation (PR #341), Popup Ads visitor-cap/cross-tab frequency hardening (PR #356), Universal AI Assistant streaming/privacy work (PR #362), and Flash Sale/negative-marking integration (PR #363). All four merged with exact-head Laravel CI and CodeQL success. The Flash Sale standalone signed distributable and staging lifecycle acceptance remain release gates.
- This is a source review plus automated regression/CI review. It is **not** a production penetration test, provider certification, staging acceptance or proof of release readiness.

## First-party executable plugin autopsy

| Package | Reviewed behavior | Result / follow-up |
|---|---|---|
| aec.universal-ai-assistant | Encrypted provider key storage, role-aware context construction, scoped student/course/batch/assignment/exam data, request/token budgets, provider endpoint validation, incremental streaming and chunk-safe secret redaction. | Provider endpoint custom-port DNS-pinning gap fixed in merged PR #339. PR #362 added streaming and role-scoped privacy disclosures. Admin must still disclose external AI data processing and configure provider allowlists/budgets; test against a staging provider. |
| aec.board-question-solver | Student-owned request history, server-side provider budget reservations, image/question handling and dependency on Universal AI Assistant. | A dependent entrypoint could require dependency PHP before the dependency's own integrity check because runtime loaded alphabetically. Fixed by two-phase package preflight and dependency-topological loading in merged PR #337. |
| aec.learn-earn | Server-side exam attempt snapshots, ownership checks, attempt locking, server scoring, paid entitlement and gateway-backed payment state. | No new critical defect confirmed in the reviewed paths. Rehearse duplicate callbacks, checkout failure/retry, refund and entitlement reconciliation against the official payment sandbox before launch. |
| aec.phonetics-practice | Admin-managed word/sentence content, private audio storage, course-enrollment-scoped student access and separate pronunciation/audio fields. | No new critical defect confirmed in the reviewed paths. Complete browser/device testing for private audio playback, speech recognition permissions, unsupported browsers and accessibility. |
| aec.flash-sale | Admin campaign management, course/category targeting, server-side pricing, payment/enrollment redemption tracking, global/per-student caps and public/homepage promotion. | Merged in PR #363 with safe negative marking, SQLite/MariaDB rollback checks and a free-cart redemption-cap regression. Signed distributable/key provisioning and staging lifecycle acceptance remain pending. |
| aec.popup-ads | Rich HTML sanitizer, safe URL normalization, public campaign filtering, signed tracking endpoints and consent-aware aggregate event tracking. | PR #356 rechecks all visitor caps at display time and coordinates once-per-session frequency across browser tabs. Complete browser testing for consent behavior, mobile layout, keyboard access and campaign targeting. |
| aec.student-tracking | Student/Teacher/Guardian/Branch Manager scopes through shared resource-access policies, risk indicators, action records and bounded CSV export with formula-injection protection. | No new critical defect confirmed in the reviewed paths. Confirm institutional retention policy, who can see risk notes, and branch/guardian access in staging. |
| aec.bcs-bank-admission-question-bank | Admin-curated question bank and paper/question-set workflows. | Manifest declared unsupported type: education, so the install validator rejected it. Changed to supported type: module and added a test that validates every executable first-party manifest and its dependencies in merged PR #338. |

### Flash Sale package — merged, release gates remain

PR #363 integrated Flash Sale and negative marking on current main as `1dbb1c86e8ca3cd2f53a202805340c66c562e4e0`. It supersedes the stale PR #277, which is closed without merging. Exact-head Laravel CI passed the full PHPUnit suite, SQLite plugin-route/rollback checks, MariaDB negative-score rollback check, route listing and frontend build; CodeQL and the cPanel package job also passed.

A final checkout audit found and fixed a mobile-cart edge case: free Flash Sale enrollments now persist the campaign ID, so global and per-student redemption caps cannot be bypassed. The migration intentionally refuses destructive rollback while awarded negative marks remain.

**Not production-ready yet.** A separately signed plugin distributable, trusted release-key provisioning, cPanel staging install/upgrade/deactivate/uninstall/rollback rehearsal, browser acceptance and payment sandbox verification remain outstanding.

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
| Administrator authentication | Password-only admin login bypassed the trusted-device MFA middleware. PR #341 merged as `58925acc054fcc0da8553497c48b2469acd7ff76` after exact-head Laravel CI and CodeQL passed. It adds verified-email bootstrap, TOTP, one-time recovery codes, encrypted secrets, replay protection, account-level rate limits and forced invalidation of old admin sessions. Isolated staging enrollment/recovery acceptance is still required. |
| Mobile API | Student/Teacher tokens are device-bound; docs/tests were reconciled with the actual Teacher trusted-device policy in merged PR #334. Admin API login is not part of the mobile Student/Teacher login flow. |
| Branch Manager access | Reviewed batch/students/attendance paths use branch scope and teacher assignment checks. Existing tests intentionally permit a student with an enrollment in a branch batch to appear in that branch's workspace; confirm this matches transfer policy and keep guardian access tied to current approved links. |
| Payments/refunds | bKash callback flow verifies provider state/amount server-side, uses event/payment locking and idempotent reconciliation. Refund stale-state overwrite guard merged in PR #332. Official merchant sandbox and live callbacks remain unverified. |
| Private eBook access | Download path checks user ownership, completed order, revocation, expiry and remaining downloads under a locked permission record. Private proof/file delivery is not public catalog access. Complete staging tests for expiry, revocation, concurrency and storage permissions. |
| Installer/updater | Source review found private staging, archive path/symlink/size controls and signed release checks. AdminUpdateController currently supports signed manifest checks, package download, private extraction, activation preflight and pre-activation backup, but has no release activation or release rollback endpoint. BackupController separately supports HMAC-verified backup restore under an exclusive operation lock with a retained pre-restore safety snapshot; that is not an automated release rollback. Implement atomic release activation, migration orchestration, health checks and rollback before calling the Update Center complete. |
| Sessions/device revocation | Device sessions are tracked and revocation is checked on protected requests. PR #341 additionally forces old admin password-only sessions to reauthenticate; legacy non-admin sessions retain the existing grace behavior until sign-out/expiry. |

## Open release gates

- [ ] Provision the trusted plugin release signing key outside the web root/repository and produce a separately signed Flash Sale distributable; verify tamper rejection and key rotation.
- [ ] Rehearse Admin TOTP bootstrap, login, replay rejection, one-time recovery codes, lost-factor owner recovery and old-session invalidation on isolated staging.
- [ ] Rehearse Flash Sale plugin install/activate/deactivate/update/uninstall and guarded rollback on isolated cPanel staging; verify data retention and browser flows.
- [ ] Create isolated HTTPS staging subdomain and separate database. The last owner update reported neither was available.
- [ ] Test MySQL/MariaDB migration/rollback, cPanel package install/upgrade, private storage, queue/cron, SMS/email delivery, bKash sandbox, live class join/recording and browser/mobile accessibility.
- [ ] Add recent step-up MFA for high-risk admin actions (refunds, plugin install/activation/uninstall, backup restore and updater rollback). Backup restore currently requires password confirmation and a safety snapshot, but not a fresh MFA challenge.
- [ ] Begin the 56-module extraction program with an explicit dependency/contract inventory; do not mark catalogue manifests as installable plugins.
- [ ] Perform a separate staging penetration test after the above changes. No production penetration test has been performed in this audit.

**Production deployment remains blocked.** PR #341 and PR #363 are merged, but no production migration, live payment activation, plugin activation or hosting-file deployment was performed by this audit. Post-merge main CI and CodeQL for `1dbb1c86e8ca3cd2f53a202805340c66c562e4e0` were started and should be confirmed green before the next release checkpoint.
