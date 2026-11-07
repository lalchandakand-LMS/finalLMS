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

The integration branch is isolated from main. No production code has been merged or deployed.

### Stage 1: student learning portal and recorded lessons

Ported into the integration branch:
- Student "My Courses" list with enrollment status and lesson completion progress.
- Recorded lesson route and protected lesson playback for enrolled students, with public preview support.
- Privacy-enhanced YouTube embeds and Vimeo embeds; arbitrary host URLs are not embedded.
- Teacher/admin lesson create/update/delete and student progress overview.
- Lesson video URL migration and model field.
- Regression tests for student access, cross-course access, preview access, lesson progress, and teacher ownership.
- Course page links to lessons and a teacher-only lesson-management shortcut.

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

Stage 1 CI passed on commit `9582f231f1ad339ee0746ba2515a2f48fac37e1c` (test and cPanel package). Stage 2 CI passed on commit `d84e96cee5a4ae59febddbe1b7fed93cc19e3a97` (test and cPanel package). Stage 3 branch-scope and role-assignment tests passed on commit `a6d202efebb4bf72ec89f27ad357099edec2990a` (test and cPanel package).

Branch-scope and role-assignment tests are now being reconciled against main's stricter rules: both branch managers and accountants must have an active branch, and only branch managers are restricted to branch-owned batches. Tests must reflect those existing business rules rather than weaken them.


### Stage 4: bKash server-side checkout

In progress:
- Token grant, checkout creation, execute, and payment-status query use server-side credentials.
- Enrollment activation requires a completed server-side status query matching payment ID, amount, BDT currency (when returned), transaction ID, and invoice reference (when returned).
- Callback status alone is never treated as proof of payment; cancellation/failure is accepted only when the server-side status confirms it.
- Gateway payment IDs are unique, callbacks are idempotent, and gateway-backed payments cannot be manually approved/rejected through the generic admin payment review.
- Checkout URL hosts are allowlisted and TLS verification defaults to enabled.
- cPanel environment placeholders and merchant setup instructions are included.
- Nagad remains disabled/configuration-only until official merchant API documentation and credentials are available.

Stage 4 must pass gateway service/flow tests and cPanel packaging before being marked complete.
