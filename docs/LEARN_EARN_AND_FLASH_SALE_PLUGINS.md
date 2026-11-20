# Learn & Earn and Flash Sale plugins

Owner-approved product scope (2026-11-16). This document defines the acceptance contract; it does not claim the plugins are already implemented or production-ready.

## Architecture and lifecycle

- Deliver two independently managed signed packages: `aec.learn-earn` and `aec.flash-sale`.
- Each package declares only the portals, capabilities and permissions it needs. Admin can install, activate, deactivate and uninstall each through the universal Plugin Manager.
- Deactivation must immediately block that plugin's routes, navigation, public cards and checkout entry points. It must not delete question banks, exams, attempts, results, sale campaigns, payments, enrollments or course prices.
- Uninstall must use the Plugin Manager's verified backup/restore and retention safeguards. If safe backup/recovery is unavailable, uninstall must be blocked rather than deleting package data.
- No third-party builder/provider branding, hidden vendor labels, watermarks or AI Studio references in product UI, metadata or generated copy.

## Plugin A: Learn & Earn

### Admin authoring

1. Admin creates a reusable MCQ as one question record (the requested single-question/post workflow), with question text, exactly four distinct options, correct option, category, optional subject/difficulty/explanation, positive mark and negative mark.
2. Category is managed independently and supports filtering/searching in the question bank.
3. Admin creates an exam by selecting existing MCQs. The exam stores immutable question snapshots (question, options, answer key, category and both marks) so later edits to the bank cannot alter a live exam or an existing attempt.
4. Exam configuration includes title/description, publication status, start/end time, duration, pass mark, question count/randomization, attempt limit, access type (free or paid), price/currency and result visibility.
5. Validate that the selected questions are MCQs, the exam contains at least one question, selected question count is within limits, and pass mark does not exceed the maximum attainable score.

### Student exam and scoring

- Public/student exam catalogue shows only published exams within their availability window.
- Free exams can be started by eligible signed-in students without payment.
- Paid exams require a server-verified successful payment/order entitlement before starting. A browser redirect, client-supplied status or pending payment is never sufficient.
- Create one server-owned attempt with a fixed question snapshot, randomized order where configured, server deadline and attempt limit enforcement. Prevent duplicate concurrent starts/submissions with database transactions and row locks.
- Never send correct answers or answer keys to the client before submission/result release.
- Score each submitted answer on the server: correct = that question's positive mark; incorrect = negative mark; unanswered = zero. Negative scores are allowed per question but the final exam total is floored at zero. Use decimal-safe numeric storage if fractional marks are supported; otherwise validate integer marks consistently.
- Store per-question outcome and final score, maximum marks, pass/fail, submission time and audit metadata. Results/history must be private to the student and authorized staff.
- Enforce ownership and role/branch policies on every page, route, API, report and export; rate-limit start and submit endpoints.

### Required tests

- MCQ validation, category filtering, question selection, immutable exam snapshots.
- Free exam access and paid exam denial for pending/failed/unverified payments; allow only verified paid entitlement.
- Correct, incorrect, unanswered, negative-score and zero-floor calculations.
- Time expiry, duplicate requests, concurrent attempts, attempt limits and answer-key confidentiality.
- Cross-student IDOR, teacher/branch scope and plugin deactivation behavior.

## Plugin B: Flash Sale

### Admin campaign management

- Admin selects one or more published courses and configures a percentage or fixed-price discount, campaign title/badge/description/banner, start/end timestamps, priority, optional redemption cap and per-student limit.
- Publishing a campaign makes it eligible only inside its configured time window. Validate discounts so the payable price never becomes negative and preserve the original course price.
- Admin can activate/pause/archive individual campaigns and independently enable/disable the public Flash Sale page/tab. Page disabled means no public Flash Sale navigation, route content or homepage promotion is exposed.
- Scheduled end time automatically removes expired campaigns from public listings and checkout pricing without requiring a cron job for correctness. Background jobs may be used only for cleanup/notifications.
- Public Flash Sale page lists all currently eligible campaigns and their selected published courses. Course detail and checkout must use the same server-side active campaign and price calculation.
- At checkout, snapshot the applied sale ID, discount type/value and original/final price into the order/payment/enrollment record. Later campaign edits or deactivation must not rewrite an already completed purchase.
- Prevent stacking incompatible discounts unless an explicit future policy allows it. Enforce redemption limits atomically under transaction/row lock.
- Preserve course base price and existing payment/enrollment history when campaigns are edited, paused, expired or archived.

### Required tests

- Selected-course targeting, campaign schedule boundaries, page-level enable/disable and individual pause/archive.
- Percentage/fixed-price calculations, zero/negative price protection and unchanged base price.
- Expiry behavior without cron, per-student/global redemption limits under concurrency.
- Checkout price snapshot integrity, paid/free redemption and payment failure paths.
- Unauthorized Admin actions, public leakage when page/plugin is disabled and deactivation safety.

## Delivery sequence

1. Audit existing Question Bank, batch Exam, course Question Set and Flash Sale workflows; reuse only after matching the acceptance contract. Do not duplicate their tables/controllers blindly.
2. Extract the Learn & Earn exam workflow and Flash Sale workflow behind their signed plugin route/permission boundaries. Existing course/batch exams and current flash-sale checkout must not be broken during transition.
3. Add plugin-owned migrations/settings/navigation/views/controllers and focused feature tests. Keep core shared payment, enrollment, identity, audit and authorization services in core.
4. Verify fresh install and upgrade migrations on SQLite and MySQL/MariaDB CI, run PHP lint/tests/CodeQL and package signing/integrity checks on the exact PR head.
5. Keep both packages Draft/inactive until signed release packaging, staging acceptance, backup/restore and real purchase/score tests pass. Do not activate or deploy to the production domain as part of this PR.

## Current baseline found during initial audit

- Existing reusable question bank: `question_bank_questions`, with category and positive marks.
- Existing teacher/batch MCQ exams and student attempts exist; their current schema does not yet provide per-question negative marks or standalone free/paid Learn & Earn entitlement.
- Existing course question sets/attempts exist separately and are course-enrollment scoped.
- Existing Flash Sale model/controller/views and campaign/course/category targeting exist in core. A dedicated plugin boundary and a global public-page enable/disable setting still need to be completed.
- Therefore, this is a pluginization and capability-completion workstream, not a greenfield duplicate implementation.
