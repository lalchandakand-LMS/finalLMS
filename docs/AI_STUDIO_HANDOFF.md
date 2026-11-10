# AI Developer Handoff — Alom's English Center LMS

Last reviewed: 2026-11-10
Repository: https://github.com/lalchandakand-LMS/finalLMS
Production domain: https://alomsenglishcenter.com
Canonical application: Laravel 12, PHP 8.3+, MySQL/MariaDB

## 1. Purpose and how to use this document

This is the durable handoff for any developer or AI coding agent, including Google AI Studio. It records the product decisions, known implementation context, open work, hosting constraints, quality rules and the process for continuing safely.

**Start here before changing code.** Then inspect the current repository, branches, pull requests, tests, migrations and deployment files. Documentation is context, not proof that code works. The actual current code and fresh test evidence are authoritative for implementation status.

### Required reading order

1. `README.md` — application setup and high-level scope.
2. `docs/AI_STUDIO_HANDOFF.md` — this file; architecture, rules and continuation instructions.
3. `docs/PROJECT_STATUS.md` — current work/status snapshot and known blockers.
4. `docs/DECISION_LOG.md` — owner-approved product and technical decisions.
5. `docs/PROJECT_ROADMAP.md` if present on the checked-out branch; treat its timestamps and branch-specific notes carefully.
6. Relevant GitHub issues and PRs linked from these documents.
7. The real Laravel routes, models, migrations, policies, controllers, views, tests and workflows.

If a referenced file does not exist on the checked-out branch, do not invent its contents. Search the repository and report the discrepancy.

## 2. Source-of-truth and conflict rules

Use this priority order:
1. The owner's latest explicit instruction in the current project conversation.
2. Latest accepted decision recorded in `docs/DECISION_LOG.md`.
3. Current application code, migrations, policies and passing tests for what is actually implemented.
4. Current GitHub issue/PR descriptions and review comments.
5. Older README/roadmap notes.

When two documents conflict, do not silently choose an old description. Identify the conflict, apply the newest owner-approved decision, and update the affected docs together. In particular, an older architecture issue may describe four portals; the later explicit requirement is **five operational panels with one unified Admin and a separate Branch Panel** (see Decision Log).

## 3. Product identity and target

- Product: Alom's English Center LMS.
- Target use: commercial LMS for online and offline English coaching.
- Expected initial scale discussed by owner: 2,000+ students in year one.
- UI: Bangla and English.
- Responsive public website and dashboards for desktop, tablet and mobile.
- Owner wants a complete, integrated commercial platform—not a demo, menu-only prototype or admin-only starter.
- Existing public domain: `alomsenglishcenter.com`.

## 4. Portal architecture — current owner-approved decision

There are **five operational panels**:

1. **Admin Panel** — one unified central administration panel. Do not create a separate Super Admin panel. The authorized Admin manages platform-wide users, teachers, students, guardians, content, courses, payments, settings, reports, permissions and system operations.
2. **Branch Panel** — a separate branch operations panel, restricted server-side to assigned branch(es). It is not a second Super Admin. Branch staff must never see or mutate another branch's data without explicit central permission.
3. **Teacher Panel** — assigned classes/batches/courses, lessons, attendance, assignments, assessments, grading, live classes and permitted student progress.
4. **Student Panel** — admission/account, enrolled courses, lessons, video player, progress, assignments, exams, attendance, results, certificates, invoices/payments, notices and support.
5. **Guardian Panel** — guardian account linked to one or more students; only linked children's attendance, results, progress, fees/invoices, notices and approved communications.

### Guardian security requirements

- Link a guardian to a student through a verified and auditable relationship, not by trusting a submitted student ID.
- Support multiple children for one guardian and safe child switching.
- Enforce relationship checks server-side in every route, API, download, search, export and notification.
- Never expose another student's records by changing URL IDs or request parameters.
- Guardian cannot modify academic or financial records unless a separately approved permission explicitly allows it.
- Test allowed linked-child access and denied unrelated-child access for every sensitive resource.
- Keep relationship creation, verification, revocation and audit history manageable by authorized Admin.

## 5. Technical baseline and hosting

- Canonical backend: Laravel 12.
- PHP target: 8.3+.
- Database: MySQL/MariaDB; SQLite may be used for suitable local tests, but MySQL compatibility must also be checked.
- Frontend assets: use the repository's existing Vite/build setup; inspect package scripts before changing.
- Hosting discussed: Web Host BD cPanel.
- Domain: `alomsenglishcenter.com`; SSL was reported enabled.
- Latest owner-provided hosting facts: PHP 8.3 available; no staging subdomain and no separate staging database had been created; XAMPP was not installed on the owner's computer.
- Exact purchased hosting resource tier has not been confirmed in the project context.
- Never assume staging is available or claim a cPanel deployment was tested unless there is current evidence.

**Do not replace the Laravel application with the separately uploaded Node/Express prototype.** A security audit documented serious issues in that prototype (in-memory storage, hardcoded session secret, demo credentials/authentication bypass patterns, insecure token/session handling and missing production controls). Reuse only reviewed product ideas; port them into Laravel safely.

## 6. Existing capabilities reported in repository README

The current main README lists the following as implemented foundations. This is a repository-level claim; verify code/tests before extending or representing any as production-accepted:

- Student registration, email verification, profile updates and Twilio phone OTP integration.
- Admin, branch manager, accountant, teacher and student role foundations and protected routes.
- Branch manager scoping for assigned branch batches; accountant-limited invoice access.
- Courses, lessons, enrollment, certificates and manual payment review.
- Branch/batch CRUD, teacher-scoped batches, batch enrollment associations.
- Teacher attendance entry and student-private attendance history.
- Assignments, teacher grading/feedback and private attachment submissions (10 MB stated limit).
- Live-class scheduling, teacher ownership, student enrollment filtering and time-windowed meeting links.
- MCQ examinations, one-attempt enforcement, timed attempts, automatic scoring, teacher result reports, reusable question banks and randomized selection.
- Public published course catalog, slug URLs, enrollment-protected lessons and preview lessons.
- Lesson progress and completion-gated certificates.
- Pending-payment batch seat reservation and transaction references.
- SEO metadata, consent-gated analytics tags and queued bulk SMS foundation.
- Admin homepage/payment/user/course/settings controls.
- cPanel first-run installer with database checks, empty-database guard, app key and first-admin creation.
- Student-to-branch assignment.
- Invoices, discounts, installment receipts, refund ledger, expenses and finance reports.
- Admin activity log for successful authenticated write requests.
- Public About/Contact/FAQ/Blog-News and basic section-based homepage builder.
- Validated media library and signed administrator-triggered backup/restore.
- English/Bangla language selector and partial translation catalogue.

The README itself lists important gaps: full Bangla translation, richer drag-and-drop templates, deeper branch workflows, official gateway integrations, scheduled off-site backup/disaster recovery, full audit logging, real cPanel staging validation and real SMS/email provider credentials.

## 7. Owner-approved feature scope

The following is the intended full product. Some areas have partial implementations or open PRs; this list is not a declaration that they are complete.

### Learning and course management
- Course category taxonomy and multi-category content posts.
- Course builder with modules/sections, reusable posts/lessons, ordering, free previews, outcomes, prerequisites, duration, teacher, level, language, price and SEO.
- Recorded lessons via YouTube/Vimeo and protected direct video upload; private PDF, notes and resource files.
- Student learning dashboard, resume learning, lesson progress, course reviews/follow-up, wishlist and course analytics.
- Question Bank with reusable question sets, MCQ, true/false, short answer and essay; options, correct answers, marks, explanations, difficulty, tags, image support, import/export and duplicate detection.
- Exams with randomization, per-student question count, timer, attempt limits, autosave/resume, auto-submit, auto/manual grading, results, review/appeal and integrity controls.
- Assignments with draft/published/closed states, due dates, late policy, resubmission, private attachments, review queue, feedback and notifications.
- Attendance, correction/approval, student/guardian views and reports.
- Certificates with eligibility, unique verification code and public verification.
- Course bundles/subscriptions, SCORM, H5P, course forums, bootcamps, team training, noticeboard and teacher appointment booking.

### Live classes
- Secure scheduling, teacher ownership and batch/student scoping.
- Zoom/Jitsi/other approved provider integration, role-based join access, attendance capture and recording synchronization.
- A secure HTTPS meeting-link workflow is not the same as a completed provider API integration.
- Do not claim API integration, automated attendance or recording sync until implemented and tested with provider sandbox/credentials.

### Website and CMS
- 15 premium responsive homepage design presets.
- Admin homepage builder with draft, preview, publish and rollback; reusable blocks and safe media selection.
- Admin-controlled hero slider, featured-course carousel, category carousel, testimonials, flash-sale section, banners, popups and announcements.
- Show/hide, ordering, design and responsive controls for sections.
- Logo, favicon, brand colors, header/footer, navigation, pages, Blog/News, SEO and media library.
- No unsafe arbitrary HTML/JS or execution of uploaded code.

### Commerce and finance
- Course checkout/cart, coupons, discounts, scholarships, installments, invoices, receipts, refunds, purchase reports and reconciliation.
- Admin-controlled flash sales with eligibility, fixed/percentage discounts, start/end, redemption caps, per-student limits, pause/resume and server-side price calculation.
- PDF/eBook store with free/paid items, private storage, verified payment entitlements, protected reader/download, download audit, refund/revocation and sales reports.
- Offline/manual payment with admin verification and audit trail.
- Payment roadmap: bKash, Nagad, SSLCommerz and, where legally/technically supported, Paystack, Paytm, CCAvenue, iyzico and PayUMoney.
- A gateway is not live merely because a settings screen or code stub exists. Require official merchant documentation, credentials stored outside Git, signature verification, idempotency, sandbox tests and reconciliation.

### Mobile apps
- Flutter Student app and Instructor app.
- Student app scope: browse/filter, course detail, enrollment/purchase, my courses, lesson player, quizzes, progress, reviews, wishlist/cart/coupons, notices and support.
- Instructor app scope: assigned courses/batches, authorized student lists, lessons/content, live classes, assignments, quizzes, progress and communication.
- API contracts, authentication/session revocation, rate limits, role checks and privacy tests required.
- Device testing, signing and app-store publication are separate release gates.

### Add-ons, support and AI
- Controlled add-on registry with configuration validation, permissions, version/compatibility and health status.
- S3-compatible private object storage with server-side credentials and signed access.
- Zoom/Jitsi integrations, course affiliate, teacher appointments and customer support/helpdesk.
- OpenAI-powered features must be server-side, permission-gated, rate/cost controlled and must not expose student personal data or secrets.
- Admin AI Developer may inspect repository context and propose changes on feature branches, with diffs, CI visibility and audit logs; no arbitrary shell, direct protected-main writes, secret disclosure or autonomous production deployment.

## 8. Known workstreams and GitHub references

Check current GitHub state before acting; these are pointers, not guaranteed live statuses.

- [PR #76 — README roadmap / Guardian documentation](https://github.com/lalchandakand-LMS/finalLMS/pull/76) — documentation-only handoff work.
- [PR #75 — uploaded ZIP security audit](https://github.com/lalchandakand-LMS/finalLMS/pull/75).
- [PR #74 — Course Content Studio / digital products](https://github.com/lalchandakand-LMS/finalLMS/pull/74); [Issue #73 — detailed course/content/flash-sale/question-set/PDF scope](https://github.com/lalchandakand-LMS/finalLMS/issues/73).
- [PR #72 — advanced lesson video management](https://github.com/lalchandakand-LMS/finalLMS/pull/72).
- [PR #71 — Student/Instructor Flutter foundations](https://github.com/lalchandakand-LMS/finalLMS/pull/71).
- [PR #69 — exam autosave/resume/auto-submit](https://github.com/lalchandakand-LMS/finalLMS/pull/69).
- [PR #68 — Question Bank and exam integrity](https://github.com/lalchandakand-LMS/finalLMS/pull/68).
- [PR #67 — assignment workflow](https://github.com/lalchandakand-LMS/finalLMS/pull/67).
- [PR #66 — bKash callback verification](https://github.com/lalchandakand-LMS/finalLMS/pull/66).
- [PR #65 — 15 homepage presets and visual builder](https://github.com/lalchandakand-LMS/finalLMS/pull/65).
- [Issue #40 — owner-approved broad feature scope](https://github.com/lalchandakand-LMS/finalLMS/issues/40).
- [Issue #41 — homepage, mobile apps and full course experience](https://github.com/lalchandakand-LMS/finalLMS/issues/41).
- [Issue #38 — older four-portal architecture proposal](https://github.com/lalchandakand-LMS/finalLMS/issues/38). Its portal count conflicts with the later explicit five-panel decision; follow the current Decision Log and reconcile the issue text.
- [Issue #3 — master tracker](https://github.com/lalchandakand-LMS/finalLMS/issues/3).
- [Issue #30 — AI Developer requirements](https://github.com/lalchandakand-LMS/finalLMS/issues/30).
- [Issue #13 — release readiness](https://github.com/lalchandakand-LMS/finalLMS/issues/13).

## 9. Required working method for any AI coding agent

Before coding:
1. Fetch current default branch, PR list, relevant branch head SHAs and exact CI/CodeQL statuses.
2. Inspect the existing implementation before creating new tables, routes, models or UI. Reuse compatible systems; avoid duplicate subsystems.
3. Read migrations, policies, middleware, routes, models, controllers, Blade/Vue/JS views, tests and deployment scripts relevant to the task.
4. Identify data compatibility, authorization boundaries, branch scoping, payment/media security and backward-compatibility risks.
5. Write a short plan and acceptance criteria in the relevant issue/PR description or project status document.

During coding:
1. Work on a focused feature branch and small reviewable PRs.
2. Keep Laravel/PHP 8.3 compatibility and cPanel shared-host constraints in mind.
3. Use additive/reversible migrations; never drop/rename production data without a reviewed migration/backup plan.
4. Enforce policies/authorization server-side; hiding a button is not security.
5. Validate all inputs, file MIME/size, URL schemes and ownership. Keep paid content private.
6. Protect payment callbacks with signature/amount/reference checks and idempotency. Never trust browser redirects for payment success.
7. Add tests for success, validation failures, unauthorized access, cross-user/branch access, edge cases and regression.
8. Add/update Bangla and English strings, docs and configuration examples.
9. Do not commit credentials, private keys, real student data or production database dumps.

Before reporting completion:
1. Run the relevant tests, full test suite where feasible, migrations on clean DB, route checks, frontend build and cPanel packaging.
2. Verify Laravel CI and CodeQL on the **exact final head SHA**. If failed, inspect logs, fix and rerun.
3. Separate automated CI from browser QA, staging QA, provider sandbox and production acceptance.
4. Update `docs/PROJECT_STATUS.md`, `docs/DECISION_LOG.md` if decisions changed, `README.md` for user-facing scope, and relevant module documentation.
5. Record commit SHA, PR URL, test evidence, failures/limitations, next action and whether merged/deployed (usually no).
6. Never call a feature complete based only on a route, menu item, migration, mockup or passing syntax check.

## 10. Release safety rules

- No direct push to protected main; use PRs.
- No PR merge unless the owner explicitly approves that merge.
- No production deployment, live payment activation, data migration on production or app-store release without separate explicit owner approval.
- Never claim a staging install, browser test, payment sandbox or live gateway test unless actually performed.
- Keep secrets in hosting environment or secure files outside public web root and Git.
- Back up before any data-changing release and document rollback.
- Production release requires CI/CodeQL, staging install/upgrade, mobile/responsive QA, payment sandbox acceptance, backup/restore rehearsal, security review and owner sign-off.

## 11. Copy/paste instruction for Google AI Studio

You are continuing the existing Alom's English Center LMS repository, not starting a new project. First read README.md, docs/AI_STUDIO_HANDOFF.md, docs/PROJECT_STATUS.md and docs/DECISION_LOG.md, then inspect the actual Laravel 12 codebase, migrations, routes, models, policies, tests, GitHub branches and open PRs. Do not invent completed features based on documentation. Preserve the existing Laravel/PHP 8.3+/MySQL application and existing data; do not replace it with Node/Express or generate a disconnected demo. Follow the five-panel architecture: unified Admin (no separate Super Admin), separate branch-scoped Branch Panel, Teacher, Student and Guardian. Guardian must only see verified linked children. For each requested task, identify existing code to reuse, state the implementation plan and acceptance tests, make focused changes on a feature branch, add security and regression tests, run exact-head CI/CodeQL, and update the project status, decision log and relevant README/module docs with commit SHA and evidence. Distinguish implemented, tested, blocked and planned work. Never expose secrets, weaken authorization, trust client-side payment success, publish private files, merge PRs or deploy production without explicit owner approval. Continue from the highest-priority unfinished item in docs/PROJECT_STATUS.md and report any missing context instead of guessing.

