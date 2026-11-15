# Alom's LMS — Complete Plugin Module Catalogue

Status: owner-approved product scope; runtime implementation is tracked by Issue #241.
Canonical package location: private application-root `plugins/` (outside the public document root).
All modules below are independently visible and manageable in Admin → Plugin Manager. A first-party plugin may ship inside the LMS release; it does not need to be downloaded from a marketplace.

## Platform core (protected, not optional plugins)

The core is deliberately small. It owns authentication primitives, authorization and policy enforcement, tenant/branch boundaries, verified guardian-child relationships, database transactions and integrity, audit trail, plugin lifecycle/loader, secrets encryption, rate limiting, file safety, backup/restore coordination, update verification and health monitoring.

These protected services cannot be uninstalled or disabled by a plugin: emergency admin access/recovery, minimum sign-in and password reset path, permission enforcement, payment ledger integrity, private-file authorization, audit/security logging, database integrity and plugin kill-switch. Optional delivery providers can be switched without disabling the protected workflow.

## Plugin package and lifecycle requirements

Every plugin has a unique immutable key, signed/versioned manifest, declared category, supported portals, permissions/capabilities, dependencies/conflicts, compatibility range, settings schema, health check and migration/data-retention policy. Admin lifecycle: discover → validate → stage → install → configure → activate; deactivate, update, rollback and uninstall are separate audited transitions.

A plugin must be disabled across every entry point (web, API, navigation, events, queue jobs, scheduled tasks and webhooks) when inactive. Package installation is signature-verified, checksum-verified, staged privately and protected against traversal, symlinks, duplicate paths, oversized archives and incompatible dependencies. No arbitrary unsigned code, no runtime shell/Composer execution, no self-granted permissions and no plugin writes to protected core.

## 1. Identity, login and verification plugins

| Plugin family | Independently manageable modules | Notes |
|---|---|---|
| Authentication extensions | Email login, phone login, optional social sign-in, login MFA, trusted/new-device approval | Must retain protected recovery and admin access |
| OTP | SMS OTP, email OTP, resend/cooldown policy, OTP templates, verification workflow | Keyed-hash storage, expiry, replay protection, attempt limits and throttling |
| Account verification | Student, guardian, teacher and staff email/phone verification | Must not leak whether an account exists |
| Recovery | Password reset delivery adapters, recovery notifications | At least one protected recovery path remains available |

## 2. Communication and notification plugins

| Plugin family | Independently manageable modules | Portal reach |
|---|---|---|
| SMS | Generic SMS adapter, Bangladesh SMS provider adapters, delivery report/webhook adapter, SMS campaign tools | Admin, Branch (scoped), Teacher (permissioned) |
| Email | Gmail / Google Workspace OAuth/API, generic SMTP, other mail adapters, transactional email templates and logs | Admin; role-scoped notification preferences |
| Notice Board | Institution notices, branch notices, teacher notices, student notices, guardian notices, scheduling, audience targeting, read receipts, attachments | All five panels |
| Notifications | In-app inbox, push notification adapter, email/SMS dispatch adapters, user preferences | All five panels |
| Messaging | Optional support/contact messaging, staff communication and guardian communication | Strict role and relationship scope |

A notice remains a first-class content/workflow plugin even if SMS or Gmail delivery is inactive. Delivery providers are replaceable adapters and are not hard-coded into notice or OTP business logic.

## 3. Institution and academic plugins

| Plugin | Primary capabilities | Portal reach |
|---|---|---|
| Admission & application | Application forms, review, approval, admission status | Admin, Branch, Student |
| Student management | Student profile, documents, status, enrollment | Admin, Branch, Student |
| Guardian management | Guardian profiles, verified child links, child switching | Admin, Branch, Guardian |
| Branch management | Branch profile, branch staff, branch dashboard and scoped settings | Admin, Branch |
| Teacher management | Teacher profiles, availability, workload and assignment | Admin, Branch, Teacher |
| Batch management | Batch creation, capacity, schedules and enrollment | Admin, Branch, Teacher, Student |
| Course management | Course catalogue, categories, pricing, publication, archive and access rules | Admin, Branch, Teacher, Student |
| Course create / curriculum builder | Course posts, title, short/long description, thumbnail, video, SEO, tags, categories, modules, lessons and resources | Admin, Branch (permissioned), Teacher (assigned courses) |
| Lesson delivery | Recorded lessons, protected video, lesson progress and completion | Admin, Teacher, Student, Guardian (progress only) |
| Live class | Live session scheduling, provider adapter, meeting access window, attendance and recordings | Admin, Branch, Teacher, Student |
| Attendance | Class/session attendance, correction history and reports | Admin, Branch, Teacher, Student, Guardian (linked child) |
| Exam & quiz | Exam scheduling, question bank, quiz attempts, timing and grading | Admin, Branch, Teacher, Student |
| Assignment | Assignment publishing, submissions, feedback and grading | Admin, Branch, Teacher, Student |
| Results & certificates | Result publication, report cards and certificates | Admin, Branch, Teacher, Student, Guardian (linked child) |
| Fees & finance | Fees, invoices, discounts, due tracking, receipts, refunds and reports | Admin, Branch (scoped), Student, Guardian (linked child) |
| Payment providers | bKash, Nagad, SSLCommerz and future gateway adapters | Admin configuration; Student checkout |
| E-book & resources | Product catalogue, private PDF access, entitlements and resource downloads | Admin, Teacher, Student |
| Timetable & calendar | Class timetable, teacher schedules, student calendar | Admin, Branch, Teacher, Student, Guardian (linked child) |
| Support desk | Student/guardian tickets, staff queues and replies | Admin, Branch, Teacher (scoped), Student, Guardian |

Course management, course creation, curriculum, lesson delivery and live class are separate plugin entries, even when they share course and enrollment services. Disabling one must not delete course records or unintentionally disable unrelated learning modules.

## 4. Website, CMS and builder plugins

| Plugin | Primary capabilities |
|---|---|
| Homepage / theme builder | Theme presets, responsive sections, preview, publish and rollback |
| Slider / hero builder | Slides, media, links, scheduling, ordering and display rules |
| Page / landing-page builder | Reusable sections, drafts, preview and publish |
| Post / blog / news builder | Categories, tags, authoring, media, SEO and comments |
| Course content studio | Curriculum and course content authoring, integrated with Course Management |
| Menu / header / footer builder | Navigation and layout controls |
| Popup / banner builder | Targeted notices, campaigns and consent-aware display |
| Form builder | Admission, contact, feedback and custom forms |
| Media library | Uploads, metadata, private/public classification and retention |
| SEO tools | Metadata, sitemap, structured data and search-console verification |

Existing Website Builder, Website Page Builder, homepage slider, Blog/News, Course Builder and Course Content Studio must be wrapped or migrated incrementally. Preserve existing tables, route names, media references, published URLs and content. Do not create duplicate competing builders.

## 5. Analytics, storage and external service plugins

- Google Analytics 4
- Google Tag Manager
- Meta Pixel
- Google Search Console verification/API where officially supported
- Google Drive connection and storage adapter
- S3-compatible object storage adapter
- Backup destination adapters
- Calendar/meeting provider adapters (for example, Zoom/Jitsi only after real provider integration and acceptance)
- Import/export adapters and supported external data sources

Analytics plugins must obey consent settings and must never send student names, contact details, grades, payment records or guardian/student relationship data to advertising platforms.

## 6. AI plugins

| Plugin | Capabilities | Guardrails |
|---|---|---|
| AI Developer | Read-only diagnostics and code/task suggestions | No autonomous repository writes, install, permission changes or deployment |
| AI Website/Page Builder | Draft page sections and layouts | Preview and human publish approval |
| AI Course Builder | Draft course outline, curriculum and lesson text | Human academic review before publish |
| AI Quiz/Question Assistant | Draft questions, hints and rubrics | Human validation; correct answers protected from student APIs |
| AI Notice/Post Writer | Draft bilingual notices, posts and announcements | Human review and audience check |
| AI Translation & SEO | Draft translations, metadata, tags and descriptions | Human review before publication |

All AI providers are configured server-side. Apply role scopes, sensitive-data minimization, prompt/output audit, rate and spend limits, output validation and human approval. AI never autonomously activates plugins, changes permissions, deletes records, publishes or deploys.

## 7. Portal coverage

Each plugin declares one or more of: `admin`, `branch`, `teacher`, `student`, `guardian`, `public`. Admin controls global installation, activation and capability grants. Branch Manager is limited to assigned branches; teachers to assigned courses/batches; students to their own enrollments and records; guardians to verified linked students only. Every request, API, export, download, notification and background job enforces these boundaries server-side.

## 8. Definition of complete

A catalogue entry is not a completed plugin. A module is complete only after its package, manifest, lifecycle, settings, permissions, health checks, full workflow, bilingual UI, tests and cPanel packaging are delivered. Deactivation tests must prove all entry points are blocked. Uninstall must retain data by default and provide a reviewed backup/data-retention report. Provider activation additionally requires official documentation and sandbox/merchant acceptance.

Implementation order: core registry and runtime → safe lifecycle manager → migrate first-party modules in dependency order → communication/identity adapters → academic modules and builders → integrations → AI plugins → full security/regression and staging acceptance.
