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
- Teacher-owned lesson create/update/delete and student progress overview.
- Lesson video URL migration and model field.
- Regression tests for student access, cross-course access, preview access, lesson progress, and teacher ownership.
- Course page links to lessons and a teacher-only lesson-management shortcut.

The first attempt to transplant isolated tests for question-bank and branch-manager changes failed because the matching production implementation was not present on main. Those tests were removed from this increment and are deferred until their implementation and migrations are integrated together. This is intentional: tests must not be merged ahead of the code they exercise.

Stage 1 CI is being rerun on the integration branch. Do not mark this stage complete until the latest test and cPanel package jobs pass.
