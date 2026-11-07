# Safe integration audit

Date: 2026-11-07

## Scope

Integrate verified LMS work from `feature/admin-dashboard-refresh` into protected `main` without replacing the newer main-branch installer, finance, refund, and deployment work.

## Verified starting points

- `main`: `f30375d1ca0dc87094db4824ee8596bc1322b1ce`
- `feature/admin-dashboard-refresh`: `27e00babd9f6cdbb58330e9faf4bdb6fa036dd06`
- Merge base: `ce147470f7872142976f92feda2a13f2ff2732c2`
- Commit comparison: diverged; development is 375 commits ahead and 8 behind main.
- Recursive tree comparison: main has 230 tracked files; development has 229.
- Relative to the merge base, 95 paths were changed on both sides. These require deliberate three-way reconciliation, not blind overwrite.
- The development tree omits 21 files that exist on main, including the cPanel installer, installer middleware, expense/finance/refund controllers, models, migrations, views, and their tests. These main-only files must be preserved.

## Integration rules

1. Work only on `integration/lms-to-main`, created from the verified main head.
2. Never force-push or bypass branch protection.
3. Never delete main-only installer/finance/refund files merely because they are absent from the development branch.
4. Reconcile overlapping files with a three-way review against the merge base.
5. Bring over feature groups with their migrations, routes, authorization checks, translations, and tests together.
6. Run the full Laravel test suite and cPanel packaging workflow after each coherent feature group.
7. Open a PR to `main`; merge only when required status checks pass and GitHub reports the PR mergeable.
8. Do not deploy to hosting as part of repository integration.

## Staged review order

- Student learning portal and recorded lesson access.
- Question bank, randomized exams, and assignment hardening.
- Live classes and invoices.
- bKash server-side verification; keep Nagad in manual/configurable mode until official merchant API documentation and credentials are supplied.
- CMS, website builder, media library, blog/news, Bangla translations, activity logs, and backup/restore.
- Reconcile role/branch workflows and verify cPanel installer compatibility.

## Current status

The integration branch was merged into protected `main` through PR #18 on 2026-11-07. No production code has been deployed to hosting.

### Stage 1: student learning portal and recorded lessons

Ported into the integration branch:
- Student "My Courses" list with enrollment status and lesson completion progress.
- Recorded lesson route and protected lesson playback for enrolled students, with public preview support.
- Privacy-enhanced YouTube embeds and Vimeo embeds; arbitrary host URLs are not embedded.
- Teacher/admin lesson create/update/delete and student progress overview.
- Lesson video URL migration and model field.
- Regression tests for student access, cross-course access, preview access, lesson progress, and teacher ownership.
- Course page links to lessons and teacher/admin lesson-management shortcuts.

An initial isolated test transplant correctly exposed that question-bank category metadata and frozen randomized attempts were missing from main. Stage 2 therefore brought in the production controller/model changes, three supporting migrations, the student attempt view, teacher forms, and their regression tests as one coherent group. Legacy subject filtering and existing question-bank update forms were retained.

### Stage 2: Question Bank and randomized exams

Integrated:
- Question category and difficulty metadata with filtering.
- Four-option MCQ validation and teacher ownership checks.
- Optional difficulty field for compatibility with existing question-bank forms.
- Random question selection from a teacher's own bank.
- Per-attempt question limits and persisted/frozen randomized question order.
- Attempt-question model, relation, unique constraints, and migrations.
- Regression tests for ownership, validation, and randomized attempt stability.

Stage 1 CI passed on commit `9582f231f1ad339ee0746ba2515a2f48fac37e1c` (test and cPanel package). Stage 2 CI passed on commit `d84e96cee5a4ae59febddbe1b7fed93cc19e3a97` (test and cPanel package). Stage 3 branch-scope and role-assignment tests passed on commit `a6d202efebb4bf72ec89f27ad357099edec2990a` (test and cPanel package). Stage 4 and 5 passed together on commit `34ae5f60c7b4381dd92f74fe2a3560b9429c2dd5` (test and cPanel package). Stage 6 CMS media hardening passed on commit `7d107e62dcd47e62c9a9e66f9a836dbdf7f00b2e` (test and cPanel package).

Branch-scope and role-assignment tests are now being reconciled against main's stricter rules: both branch managers and accountants must have an active branch, and only branch managers are restricted to branch-owned batches. Tests must reflect those existing business rules rather than weaken them.


### Stage 4: bKash server-side checkout

Completed and CI-verified:
- Token grant, checkout creation, execute, and payment-status query use server-side credentials.
- Enrollment activation requires a completed server-side status query matching payment ID, amount, BDT currency (when returned), transaction ID, and invoice reference (when returned).
- Callback status alone is never treated as proof of payment; cancellation/failure is accepted only when the server-side status confirms it.
- Gateway payment IDs are unique, callbacks are idempotent, and gateway-backed payments cannot be manually approved/rejected through the generic admin payment review.
- Checkout URL hosts are allowlisted and TLS verification defaults to enabled.
- cPanel environment placeholders and merchant setup instructions are included.
- Nagad remains disabled/configuration-only until official merchant API documentation and credentials are available.

Stage 4 passed gateway service/flow tests and cPanel packaging.


### Stage 5: Assignment submission and grading hardening

Completed and CI-verified:
- Late-submission policy is explicit per assignment and disabled by default.
- Only active enrolled students can view or submit; cancelled/completed enrollment does not grant submission access.
- Attachments remain on private local storage and are downloadable only by the submitting student, assigned teacher, or admin.
- Re-submission is permitted only after grading; existing attachment is retained when the student updates only the text answer.
- Upload size/type, due-date, teacher ownership, grade bounds, and duplicate-submission checks are covered by feature tests.

This stage also repaired escaped namespace corruption in the integration branch's AssignmentController; the corrected controller and full regression test suite passed.


### Stage 6: CMS, website builder, media, Bangla, and backup

Audited on the current main-based integration branch:
- Public CMS pages, blog/news publishing, website builder, media library, activity logs, and backup/restore already exist in main and their existing feature tests are retained.
- Bangla catalog now has 614 keys and English catalog has 375 keys; every English key is present in Bangla. The development branch's smaller translation files are not copied over main's larger catalogs.
- Backup restore requires admin role, explicit `RESTORE` confirmation, current password, and an HMAC-valid backup. Tampered and invalid gzip archives are covered by tests.
- New hardening being added: homepage and blog cover references must point to image assets, and media used by homepage sections or article covers cannot be deleted.
- Stage 6 CI passed on commit `7d107e62dcd47e62c9a9e66f9a836dbdf7f00b2e` (test and cPanel package).


### Stage 7: deployment and security verification

Completed and CI-verified:
- CodeQL scanning for JavaScript/TypeScript runs on pull requests targeting main. CodeQL does not analyze this Laravel PHP codebase in the selected supported language configuration.
- Add PHP syntax lint across app, bootstrap, config, database, routes, and tests.
- Add Composer dependency vulnerability audit to CI.
- Compile Laravel configuration and all Blade views in the production package job.
- Update cPanel deployment documentation to describe the implemented bKash adapter and the remaining merchant-credential / sandbox activation steps.
- Real cPanel staging installation, production bKash merchant testing, off-site backup scheduling, and disaster-recovery rehearsal remain outstanding operational tasks.


Stage 7 CI passed on commit `69df4b6a44d68c1bc98d91b7f17f952484b04b04`: Laravel tests, Composer audit, PHP syntax lint, production config/view cache compilation, cPanel package verification, and CodeQL JavaScript/TypeScript analysis all succeeded.

## Final integration status

All seven planned code-integration stages are implemented on `integration/lms-to-main`. No files were removed from main in the PR comparison. PR #18 is merged as commit `379afe2fa3579a44d874280f96114b7f3cd189fc`. Post-merge Laravel tests, cPanel package verification, and CodeQL all passed on `main`. The PR changed 52 files and removed no files from `main`. Real cPanel staging installation, production bKash merchant activation, Nagad/SSLCommerz integrations, off-site scheduled backups, and disaster-recovery rehearsal remain operational follow-ups and are not represented as completed.


## Post-merge verification

- PR: [#18 — Integrate verified LMS features into main](https://github.com/lalchandakand-LMS/finalLMS/pull/18)
- Merge commit: `379afe2fa3579a44d874280f96114b7f3cd189fc`
- Main-branch Laravel CI: passed (test + package).
- Main-branch CodeQL: passed.
- Hosting deployment: not performed.


### Stage 8: complete Bangla invoice and receipt coverage

In progress:
- Add the full invoice/receipt finance vocabulary to both English and Bangla JSON catalogs.
- Localize admin invoice creation, payment ledger, student invoice balances, and printable receipts.
- Preserve branch-scoped accountant and branch-manager regression coverage.
- Main's existing translation keys and invoice finance behavior are retained; the incoming catalogs are supersets of the current main catalogs.


### Stage 9: Nagad official API handoff

- Added `docs/NAGAD_INTEGRATION_HANDOFF.md` to define exactly which official merchant documents, endpoint schemas, cryptographic details, callback rules, and sandbox materials are needed before implementation.
- Existing Nagad manual/configuration mode remains available; direct API checkout stays disabled.
- No endpoint, signing/encryption algorithm, callback rule, or credential format will be guessed.
- When the official guide is supplied, the implementation can be completed against that specification with server-side verification, idempotency, and automated failure/replay tests.
