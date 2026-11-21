# AEC repository feature-parity audit

Audit date: 2026-11-21

## Repositories reviewed

- Target application: `lalchandakand-LMS/finalLMS` (default branch `main`)
- Feature reference: `aec-lms/alom-s-english-center-lms-laravel` (default branch `main`)

Both are PHP/Laravel 12 applications targeting PHP 8.3. They are not drop-in compatible: the reference application lives under `laravel/` and uses `Plugins\\Modules\\...` providers and its own Core plugin manager; finalLMS uses a root-level Laravel application and the `App\\Services\\Plugins\\PluginContext` contract with versioned packages under `plugins/<vendor>/<slug>/<version>`.

## Inventory observations

- The recursive Git tree contained 1,236 paths in finalLMS and 1,775 in the reference repository at review time. Path counts are not a quality or completeness score.
- The reference repository currently has 60 module directories under `laravel/plugins/modules/`.
- finalLMS has a broad root-level application and plugin catalogue, plus 8 implemented packages under `plugins/aec/`. Its own `plugins/README.md` explicitly says many first-party module directories are metadata-only and do not yet make their features independently installable.
- Therefore module-name parity is not implementation parity. Each candidate must be traced through routes, controllers, services, migrations, views, permissions and tests before porting.

## Features already represented in finalLMS (verify depth; do not duplicate blindly)

- Learn & Earn, BCS/admission question bank, Board Question Solver, Phonetics Practice, Student Tracking, Flash Sale, Popup Ads and Universal AI Assistant have packages under `plugins/aec/`.
- Question banks, course assessments, course combos/bundles, course wishlists, ebooks/media library, guardian portal, attendance, assignments, exams, live classes, payment ledger, SMS, email/OTP, push notifications, reporting, branch management and website builders also have application code, migrations, controllers or plugin metadata in finalLMS.
- The reference repository has more explicit module-level feature tests for many of these areas; test coverage and actual business workflow parity still need review.

## High-priority candidate gaps or richer reference implementations

1. **Admission Management** — the reference plugin provides public intake, applicant tracking, screening/interview/reviewer assignment, private document storage, branch-scoped review, approved-applicant conversion to a student and optional admission-fee invoicing. No matching admission module/controller/model was found in the finalLMS tree.
2. **HR & Staff Management** — reference includes staff profiles, departments/designations, leave status and payroll ledger/disbursement. finalLMS has a staff-management area, but equivalent payroll and HR workflows were not established by the static inventory.
3. **Physical Library Management** — reference includes ISBN/book inventory, issue/return tracking and overdue fines. finalLMS has media/e-book resources, which are not equivalent to physical book circulation.
4. **Referral Income** — reference includes referral attribution, role-based commissions, a commission ledger, payout requests and reversal/refund handling. No matching referral-income package was found in finalLMS.
5. **Payment gateway adapters** — reference has separate Card, PayPal, Stripe and SSLCOMMERZ plugins. finalLMS has a gateway abstraction and bKash integration, but explicit equivalent provider packages were not found in the tree. Provider sandbox testing and verified credentials remain separate acceptance gates.
6. **Student Activity Trace** — reference has a dedicated branch-scoped activity timeline and CSV export. finalLMS has activity logs and student tracking; confirm whether the event coverage, branch scope, privacy and export workflows are equivalent before adding another implementation.
7. **Phonetics depth** — both repositories contain phonetics functionality. The reference module has learning assignments, exams, batch memberships, speaking recordings and audit events; finalLMS already has a pronunciation package and speaking-recording tests. Compare capability-by-capability and extend the existing package rather than importing a duplicate.

## Porting rules

- Do not copy the reference repository's provider, routes or migrations unchanged. Its namespaces, Core services, role model and plugin lifecycle differ from finalLMS.
- Reimplement/adapt each selected feature to finalLMS contracts, branch scoping, authorization, private storage, audit logging, plugin lifecycle and cPanel constraints.
- Preserve the Alom's English Center branding. Do not introduce third-party builder or AI-platform branding.
- Keep payment credentials out of source control; provider activation stays disabled until sandbox callback, amount/currency validation, idempotency and refund paths pass.
- Add focused feature tests, migration tests and plugin lifecycle/disabled-state tests for each port. Do not mark a module accepted based on a manifest or copied UI alone.
- Keep production deployment disabled until staging, backup/restore and release acceptance gates pass.

## Recommended implementation order

1. Admission Management.
2. HR & Staff Management (including payroll ledger safeguards).
3. Physical Library Management.
4. Referral Income (including refund/reversal and payout concurrency safeguards).
5. Payment gateway adapters, one provider at a time, beginning with SSLCOMMERZ for the Bangladesh use case.
6. Close Student Activity Trace and Phonetics capability gaps without duplicating existing packages.

This is a static source-tree comparison. It is not a runtime audit of either repository and does not certify the reference modules as production-ready. The reference repository's own acceptance documentation also notes that provider sandbox/live callbacks and full independent per-plugin acceptance remain separate release gates.
