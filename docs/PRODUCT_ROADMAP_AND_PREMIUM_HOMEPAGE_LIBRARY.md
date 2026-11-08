# Alom's English Center LMS — Completion Roadmap

## Product direction

Build a complete, commercial-ready, responsive LMS and institute-management platform for Alom's English Center. Keep one full-control Admin panel and a separate Branch panel. Every feature must be permission-scoped, mobile-friendly, test-covered, and manageable without editing code.

This document is the implementation checklist and acceptance baseline. Items are not considered complete merely because a menu item or placeholder screen exists: each must have working create, view, edit, status/change, delete/archive (where appropriate), validation, authorization, and automated tests.

## Priority roadmap

### P0 — Core teaching operations
- [ ] Teacher dashboard: assigned courses/batches, today's classes, attendance due, pending assignment reviews, upcoming exams, student progress and recent activity.
- [ ] Teacher workspace: roster and student profiles limited to assigned batches; lesson/course publishing; lesson resources; live-class scheduling; attendance; assignments and grading with feedback/rubrics; exam creation; question-bank management; results and downloadable reports.
- [ ] Student learning: course/lesson player, protected lesson access, completion tracking, assignment submission and feedback, exam attempts/results, attendance, certificates and progress analytics.
- [ ] Academic integrity: prevent cross-course and cross-branch data access; validate ownership server-side on every action.

### P0 — Premium homepage and website builder
- [ ] Provide **15 premium homepage templates** (within the requested 10–20 range), selectable from the Admin website builder.
- [ ] Each template must be a real, distinct responsive layout—not just a different color preset—and include a desktop/mobile preview before activation.
- [ ] Admin can customize logo, favicon, brand colors, typography, button styles, page width, header/navigation, hero layout, hero image/video, CTA text/links, course cards, teacher profiles, testimonials, statistics, notices, FAQ, blog/news, gallery, contact details, footer and social links.
- [ ] Section controls: add, remove, duplicate, reorder (drag/drop), enable/disable, edit content and choose/upload images from the media library.
- [ ] Support reusable sections, saved drafts, preview, publish/rollback, and a safe default theme. Preserve current site content when switching templates; ask for confirmation before replacing layout.
- [ ] Responsive breakpoints, accessibility, optimized images, SEO metadata, Open Graph, structured data, sitemap and performance checks.
- [ ] No template may contain hard-coded developer branding or external demo credentials. All branding must be editable by Admin.

### P1 — Commerce and finance
- [ ] E-book store: categories, book details/covers, PDF/EPUB or protected download delivery, pricing, coupons, orders, receipts, inventory/availability, refund workflow and sales reports.
- [ ] Course sales: public course catalogue, course images, pricing, discounts, enrollment requests, approval and payment status.
- [ ] Finance: invoices, partial payments, due reminders, refunds, expenses, branch-wise income/expense, teacher payments and exports.
- [ ] Payment gateway adapters for bKash and Nagad, with sandbox/configuration screens, callback signature verification, idempotency and reconciliation. Live mode remains disabled until official merchant credentials and current official integration documents are supplied and verified. Never store secrets in source control.

### P1 — Branch and people management
- [ ] Branch dashboard and strict branch data isolation for students, teachers, batches, courses, attendance, payments, inventory and reports.
- [ ] Admin can create/manage branches and centrally edit staff/student records; branch staff can only manage authorized branch records.
- [ ] Student admission: school, class, roll, guardian/contact, phone verification, photo capture/upload with consent, documents, course choice, review and approval.
- [ ] Teacher onboarding, verification, profiles, availability, workload and payroll/reporting.

### P1 — Assessment and engagement
- [ ] Question bank with categories, difficulty, tags, question types, media, import/export and reusable questions.
- [ ] Exams: scheduling, time limits, randomized questions, attempt controls, autosave, objective auto-marking, manual marking, result publishing and analytics.
- [ ] Assignment workflows: deadlines, file/link submissions, late rules, rubric grading, teacher feedback and resubmission.
- [ ] Quiz/contest and prize draw: transparent eligibility, entry/payment ledger, auditable random draw, winner records, terms and compliance review before any paid-entry promotion is enabled.
- [ ] Announcements, SMS/email notifications, templates, delivery logs and opt-out/consent handling.

### P2 — Live learning and platform operations
- [ ] Live classes: meeting provider configuration, schedule, join permissions, attendance, recordings/links and reminders. Do not expose meeting links to unauthorized users.
- [ ] Secure media library, protected lesson/e-book delivery, storage limits and cleanup.
- [ ] Security: role/permission matrix, MFA for privileged accounts, login/device controls, rate limiting, audit logs, secure uploads, CSRF/XSS/IDOR review and dependency scanning.
- [ ] Operations: cPanel-friendly installer/upgrader, environment validation, database migrations, scheduled backups, restore validation, health checks, queue/cron diagnostics and maintenance mode.
- [ ] Localization: complete Bangla/English UI, timezone/currency settings, accessible forms and mobile QA.

## Homepage template library — 15 premium concepts

All templates share the same content/section editor and design-token controls. Templates differ in composition, hierarchy, navigation and section arrangement.

1. **Academy Prime** — premium blue education brand, split hero, featured courses, proof metrics and teacher cards.
2. **Modern Language School** — language-learning focus, level pathways, speaking outcomes and placement-test CTA.
3. **Minimal Editorial** — spacious typography, editorial hero, story-led sections and restrained course grid.
4. **Corporate Training** — B2B look, training programs, corporate clients, outcomes and inquiry form.
5. **Bright Youth Academy** — energetic cards, student achievements, learning paths and mobile-first CTA.
6. **University Classic** — formal academic header, departments, faculty, notices, events and accreditation blocks.
7. **Online Learning Marketplace** — searchable course catalogue, filters, instructor ratings and category navigation.
8. **Cinematic Hero** — large image/video hero, immersive storytelling, featured program and strong CTA.
9. **Community Learning** — testimonials, learner stories, community stats, events and social proof.
10. **Exam Preparation** — exam countdown, subject pathways, mock tests, results and success stories.
11. **Kids & Teens** — friendly illustrations, parent trust signals, age-based programs and safety information.
12. **Professional Tutor** — instructor-led hero, personal teaching profile, schedule, reviews and booking CTA.
13. **Book & Resource Hub** — e-book/resource discovery, featured publications, categories and purchase CTA.
14. **Branch Network** — location selector, branch cards, local schedules, branch contacts and enrollment links.
15. **Conversion Landing** — campaign-focused hero, benefits, lead form, FAQs, pricing and repeated enrollment CTAs.

## Definition of done

- Feature routes are protected by role middleware and resource-level authorization.
- Queries are scoped by authenticated user, assigned teacher, branch and enrollment as appropriate.
- Validation and safe output escaping are applied; uploaded files are type/size checked.
- Automated feature tests cover success, validation failure, unauthorized access and cross-branch/cross-user isolation.
- Laravel CI and CodeQL are checked on the exact PR head SHA; failed jobs are inspected and fixed.
- Mobile and desktop views are checked; no production deployment occurs until explicit approval and backup/rollback readiness.
- External integrations are not described as live until credentials, sandbox tests and verified callbacks succeed.

## Suggested delivery sequence

1. Complete and validate the current student insights work.
2. Teacher dashboard and teaching workspace.
3. Homepage template engine and the 15-template starter library.
4. E-book store and commerce workflows.
5. Assessment/question-bank and result reporting.
6. Branch, finance and people-management gaps.
7. Payment/SMS/live-class provider integrations.
8. Security, installer, backup/restore, accessibility, performance and production-readiness audit.

## Important integration dependencies

- bKash/Nagad: official merchant credentials, current API documentation and approved callback URLs.
- SMS/OTP: selected provider account, sender ID approval and API credentials.
- Live classes: chosen provider account and API credentials.
- Email: SMTP/provider credentials and domain DNS authentication.
- E-book licensing and content: rights to distribute each uploaded publication.
