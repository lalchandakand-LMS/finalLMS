# Alom's English Center LMS — Project Roadmap

Last updated: 2026-11-07
Repository: https://github.com/lalchandakand-LMS/finalLMS
Working branch: feature/admin-dashboard-refresh
Pull request: #2 (keep open; do not merge without owner approval)
Production deployment: NOT authorized / NOT done.

This is the persistent project checklist. Update it whenever a task is started, completed, blocked, or verified. A feature is not complete merely because a dashboard link or UI card exists: it must have backend authorization, validation, migrations, tests, and working UI.

## Current status

### Implemented in the working branch — verification pending
- [x] Admin dashboard refresh and shared responsive styling.
- [x] Randomized question order persisted for each exam attempt.
- [x] Optional per-student random question count for exams.
- [x] Question Bank: create, edit, delete, search, category, difficulty, filters, pagination.
- [x] Assignment submission foundation: text answers and private file attachment handling.
- [x] Assignment attachment download endpoint with role/ownership checks.
- [x] Teacher grading fields and feedback workflow.
- [x] Configurable late-submission policy (default closed after deadline).
- [x] Resubmission after grading; preserve existing attachment when changing only the text answer.

- [ ] Add lesson editing, provider-specific playback tests, and full teacher lesson management workflow.

### Current blocker
- [x] Diagnose and fix CI migration ordering: Question Bank category/difficulty migration now runs after its table-creation migration; verified by passing CI run #504.
- [ ] Add/complete automated tests for assignment create, enrollment authorization, file validation/storage/download, grading, deadline, and resubmission.
- [x] Add student course overview with active/completed enrollments and lesson progress; hide unpublished courses from student view.
- [x] Add feature tests for student portal access, lesson progress display, idempotent completion, inactive enrollment denial, and cross-course lesson access denial.
- [x] Verify student portal changes in CI (run #535 passed both test and cPanel package jobs).
- [x] Re-run full Laravel CI and cPanel package job; both jobs passed in [run #504](https://github.com/lalchandakand-LMS/finalLMS/actions/runs/42283409532).
- [ ] Review migration order and schema compatibility against clean MySQL and existing deployment databases.

## Priority backlog

### P0 — Stabilize current work
- [x] Fix latest GitHub Actions migration ordering issue; rerun CI to verify.
- [ ] Verify new migrations on clean SQLite/MySQL test databases.
- [ ] Run tests, route:list, frontend build, and cPanel package workflow.
- [x] Harden assignment uploads: private local disk, extension/size validation, download authorization, storage-failure handling, and replacement-file cleanup implemented; full regression verification pending.
- [ ] Verify teacher grading permissions and admin policy.
- [ ] Add regression tests for random exam persistence and Question Bank CRUD/filtering.
- [ ] Keep PR #2 open until CI is green and owner approves merge.

### P1 — Complete core teaching workflows
- [ ] Question Bank bulk import/export, tags, question types, explanations, image support, duplicate detection.
- [ ] Exams: MCQ/short/essay types, timer, auto-submit, negative marking, retake policy, result publication, review/appeal workflow.
- [ ] Assignments: review queue, draft/published/closed states, late labels, resubmission limits, feedback notifications, attachment cleanup.
- [x] Student portal foundation: dedicated enrolled-courses page, per-course lesson completion progress, student-only authorization, and dashboard entry point (resources and expanded learning tools remain pending).
- [x] Recorded lesson foundation: YouTube/Vimeo URL support, privacy-enhanced YouTube embeds, preview lessons, protected playback route and enrollment/ownership checks; CI verification pending.
- [ ] Live classes: provider integration, schedules, attendance, access control, recording links.
- [ ] Attendance: teacher marking, student view, correction/approval, reports.
- [ ] Certificates: eligibility, issue/download, unique verification code/public verification page.

### P2 — Operations and administration
- [ ] Role/permission matrix for super admin, admin, branch manager, teacher, accountant, student, guardian.
- [ ] Branch-scoped users, batches, courses, attendance, payments, reports.
- [ ] Admission application, admin review, enrollment approval, onboarding.
- [ ] Fees/invoices: fee plans, installments, discounts, due reminders, receipts, refunds, reconciliation.
- [ ] bKash, Nagad and payment gateway integrations; webhook signatures and idempotency.
- [ ] Notifications: in-app, email, SMS provider, templates and delivery logs.
- [ ] Reports: enrollment, attendance, exam performance, revenue, dues, teacher activity.
- [ ] Activity/audit logs and exportable reports.

### P3 — Public website and content management
- [ ] Website builder with editable sections and preview/publish workflow.
- [ ] Media library with upload validation, access rules, search, cleanup.
- [ ] Blog/news: categories, drafts, scheduled publishing, SEO metadata.
- [ ] Complete Bangla/English translations across all portals and public pages.
- [ ] Responsive public home, course, admission, contact and policy pages.

### P4 — Installation, security, and release
- [ ] Secure cPanel installer with preflight, DB setup, admin creation, installer lock/removal.
- [ ] Production environment/secrets handling; no credentials in Git.
- [ ] Verify PHP 8.3, MySQL/MariaDB, writable storage/cache, queue/cron.
- [ ] Security review: CSRF, XSS, SQL injection, mass assignment, IDOR, rate limits, sessions, password reset, uploads, authorization.
- [ ] Backup/restore: database and files, retention, restore test.
- [ ] Web Host BD cPanel deployment guide and rollback plan.
- [ ] Staging deployment and acceptance test before production.
- [ ] Production deployment only after explicit owner approval.

## Release acceptance checklist
- [ ] Migrations succeed on a fresh database.
- [ ] Automated tests pass.
- [ ] No critical/high security findings remain.
- [ ] Admin, teacher, student and branch workflows tested on mobile and desktop.
- [ ] Bangla and English UI checked.
- [ ] Payment callbacks tested in sandbox.
- [ ] Backup and restore verified.
- [ ] cPanel package installs on PHP 8.3.
- [ ] Owner approves PR merge and separately approves production deployment.

## Working rules
1. Work on feature/admin-dashboard-refresh unless the owner changes the target.
2. Never merge PR #2 or deploy without explicit approval.
3. Update this checklist with meaningful project progress.
4. Mark tasks complete only after implementation and verification; otherwise mark verification pending.
