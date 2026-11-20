# Owner Decision Log — Alom's English Center LMS

Last consolidated: 2026-11-15

This file records decisions that should not be casually reversed by a developer or AI agent. If the owner gives a newer explicit instruction, append a dated decision and update dependent documentation/code.

## D-001 — Product and brand
- Product name: Alom's English Center LMS.
- Target: complete commercial LMS for online and offline coaching, not a demo or admin-only starter.
- Target scale discussed: 2,000+ students in the first year.
- Public brand/domain: alomsenglishcenter.com.
- Public UI must support Bangla and English and responsive layouts.

## D-002 — Technology baseline
- Canonical application is Laravel 12, PHP 8.3+, MySQL/MariaDB.
- Do not replace the canonical app with a separate Node/Express prototype.
- Reuse existing code only after compatibility/security review; avoid duplicate models/workflows.
- Hosting target is Web Host BD cPanel. Staging capability must be established and tested separately.

## D-003 — Five operational panels and unified Admin
- Current owner direction: five panels — Admin, Branch, Teacher, Student, Guardian.
- Admin and Super Admin must not be separate portals; use one unified Admin Panel with granular permissions.
- Branch Panel remains separate from Admin and must be server-side scoped to assigned branch(es).
- Teacher, Student and Guardian remain separate portals.
- This later explicit decision supersedes older four-panel text in Issue #38. Issue #40 also records the five-panel requirement. Reconcile stale issue text before treating it as current architecture.

## D-004 — Guardian privacy
- Guardian must be linked to one or more students through a verified relationship.
- Guardian can only see linked students' attendance, results, progress, fees, notices and permitted communication.
- No IDOR or cross-student access via URL, API, exports, search or notifications.
- Multiple children and secure child switching are required.

## D-005 — Unified Admin responsibilities
- One Admin Panel centrally manages all permitted modules: users, teachers, students, guardians, branches, courses, batches, admission, content, finance, website/CMS, SEO, reports, settings, security, backups, integrations and updates.
- Staff access is granular and least-privilege. Not every staff account gets unrestricted admin rights.

## D-006 — Homepage and brand customization
- Homepage should have 15 premium visual design presets.
- Admin can preview, configure, publish and roll back the active homepage.
- Admin can control brand colors without coding; preferred header direction is blue gradient, not orange gradient.
- Homepage hero slider and other carousels should have admin-controlled content/settings and polished responsive behavior.
- Include featured-course/category carousels, testimonials, flash-sale section and other planned content blocks when their data models/workflows are ready.
- Template switching must not damage courses, students, orders, settings or published content.

## D-007 — Course content and eBook store
- Admin course/post creation fields include title, short description, long description, video URL, thumbnail, SEO, tags and categories.
- Course categories must be created/managed by Admin and used as the source for category selection.
- Admin needs create/edit/update/archive/delete controls with safe dependency handling.
- eBook/PDF selling is in approved scope. Paid files must remain private and be accessible only after server-side verified purchase; free products are allowed.

## D-008 — Live class and recorded lessons
- Support recorded lessons and live classes.
- Meeting URLs must be HTTPS and only visible to eligible enrolled students during the configured time window.
- Completed students may view published recordings if entitled, but must not receive an expired live meeting link.
- Secure link workflows must not be misrepresented as completed Zoom/Jitsi provider API integrations.

## D-009 — Payments and external integrations
- bKash/Nagad and online payment gateways are desired.
- Do not guess undocumented cryptographic signing, encryption or callback contracts.
- Never store merchant credentials or private keys in Git or chat.
- Payment success must be verified server-side; browser redirect alone is never proof.
- Each provider remains disabled until official documentation, merchant approval, sandbox tests and reconciliation are complete.

## D-010 — Delivery and release safety
- Work in focused feature branches and PRs.
- Successful code and docs should be committed to the project branch; never rely only on chat memory.
- Add migrations, authorization, validation, tests, bilingual strings and deployment notes with features.
- Verify CI/CodeQL on the exact final commit SHA.
- The owner pre-authorizes autonomous feature-branch/PR work and safe PR merges to main when full diff review, exact-head CI/CodeQL, dependency/conflict checks and risk review pass; do not ask for routine merge approval. Production deployment, live payment activation, irreversible production data migrations and app-store releases still require verified backups, rollback/recovery readiness and applicable release safeguards.
- Never call a feature complete based only on UI/menu/table/syntax; require complete user workflow, authorization, automated tests and relevant staging/provider acceptance.

## D-011 — AI handoff and project memory
- Keep README, PROJECT_HANDOFF, PROJECT_STATUS and this decision log aligned.
- Every meaningful change must record: date, branch, PR, commit SHA, files/modules touched, behavior changed, tests run/results, known limitations, next task and merge/deployment status.
- AI agents must read these files and inspect actual source before coding. They must not invent implementation or CI status.
- Preserve a dated history; do not erase previous decisions without recording why they changed.

## D-012 — Post-launch content and application updates
- Routine website/LMS content and configuration (courses, lessons, homepage, pricing, flash sales, eBooks, notices, translations and supported settings) must be maintainable by Admin through existing no-code modules.
- Application-code updates must use a trusted, versioned, signed release from the CI pipeline, not arbitrary ZIP uploads or arbitrary URLs.
- The Admin Update Center must show release notes, compatibility/preflight, backup, progress, audit history, health checks and safe recovery; only unified Admin may use it.
- Preserve .env, credentials, student records, private media and uploaded files. Use backward-compatible migrations and tested staged activation/rollback.
- The specification is recorded in docs/ADMIN_UPDATE_SYSTEM.md. Do not call the updater complete until its implementation and cPanel staging acceptance pass.

## D-013 — Autonomous repository workflow authorization
- Owner authorizes autonomous feature-branch/PR implementation and safe merges to main after complete review and exact-head checks pass; no repeated approval request is needed for routine merges.
- Production deployment and irreversible live-data operations remain subject to demonstrable backup, rollback and environment safety gates.

## D-014 — Course combos and single active student browser device (2026-11-13)
- Admin may create fixed-price course combos containing at least two published courses. Combo checkout does not bypass batch capacity: courses with open batches cannot be added or purchased through combos.
- A paid combo is one manual payment. Its included course IDs/titles/order are snapshotted at checkout; Admin approval grants the original included courses together. Editing the current combo must not change an existing paid order.
- Combos with payment history are archived rather than deleted. Courses referenced by combos or payment history must be archived/removed from those references before deletion.
- Student web/browser sign-in allows one approved browser device at a time. First browser device is activated after SMS OTP; additional or previously revoked devices require SMS OTP and Admin approval. Approving a new device revokes the previous approved browser device, and its server-side web session is invalidated on the next protected request.
- The mobile API shares this approved-device registry. Student mobile login requires a secure 64-character installation ID; new/revoked devices require SMS OTP and additional devices require Admin approval. Student bearer tokens are bound to an approved device and are rejected/revoked if that device is no longer approved. Admin approval/revocation invalidates prior mobile tokens. Teacher API login remains password-based.
- Flutter client UI/secure storage/polling implementation and real SMS delivery on staging remain separate acceptance checks.

## D-015 — eBook store implementation tranche (2026-11-14)
- The approved eBook/PDF store scope in D-007 was implemented in focused PR #124 and merged to `main` as `65c5e20b79bf9709ebd4a713552d9f531258cfe0`, without merging the stale broad commerce PR.
- Paid eBook access must remain private and must be granted only after an Admin independently verifies the manual payment. Free eBooks may be fulfilled immediately.
- A student must not be able to repeatedly check out an already-active entitlement to reset its download limit. Expired or exhausted entitlements may be repurchased.
- A purchased PDF cannot be replaced or deleted while order history exists; archive the product instead. Store PDF files and payment proofs on the private local disk and serve them only through authorization-checked controllers.
- PR #124 is an implementation tranche, not a complete commerce system. It does not activate bKash/Nagad/SSLCommerz, automate payment verification, provide refunds/revocation UI, or satisfy staging/production acceptance.
- Merge completed after exact-head Laravel CI, cPanel package verification, CodeQL and full diff review passed. Production rollout remains a separate release gate; isolated staging acceptance is still required.

## D-016 — Universal plugin platform across all portals and builders (2026-11-15)
- Replace the limited fixed allow-list Add-on Registry with a secure universal plugin platform using one private application-root `plugins/` directory.
- Admin must be able to discover, install, configure, activate, deactivate, update and uninstall trusted plugins through a Plugin Manager, with compatibility/dependency checks, health status, version history and audit logs.
- Plugin contract supports all five operational panels: unified Admin, Branch, Teacher, Student and Guardian, with declared portal scope and permission capabilities enforced server-side. Existing branch isolation and verified guardian-child privacy cannot be bypassed by plugins.
- Plugin families include integrations (GA4, GTM, Meta Pixel, Google Search Console where supported, storage, SMS/email and payment providers), slider/post/course/page builders, portal modules and AI Builder/AI Developer.
- Existing builders and integrations must be integrated/migrated without duplicating data models or breaking current content. AI output is draft-only until human approval; AI cannot autonomously install plugins, alter permissions, delete data, publish or deploy.
- Install/update requires signed trusted packages, checksum and manifest verification, private staging, archive/path safety, compatibility/dependency checks and verified backup before data-changing migrations. Uninstall defaults to retaining plugin data; no silent destructive purge.
- Plugin code is never served from public web root; no arbitrary unsigned PHP/JS execution, shell/Composer runtime install, or direct production activation.
- Architecture and delivery contract: `docs/UNIVERSAL_PLUGIN_PLATFORM.md`; implementation tracked by Issue #241. This is an approved product direction, not a claim that the plugin runtime or installer is already implemented.

## D-017 — Every optional LMS module is plugin-managed (2026-11-15)
- Owner explicitly requires every optional module to be independently listed and managed through the universal Plugin Manager, not merely external integrations.
- This includes SMS, Gmail/Google Workspace and other email adapters, OTP/account verification, Notice Board, notifications, AI Developer and AI Builder, course management, course creation/curriculum, lesson delivery, live classes, admission, branch, teacher, student, guardian, batch, attendance, exam/quiz, assignment, results, certificates, fees, payment adapters, eBooks, reports, support desk, CMS, homepage, sliders, posts/blog, page/forms/menu/popup builders, SEO, analytics, storage and backup adapters.
- Each module declares its own portal availability, server-enforced permissions, dependencies, settings, health checks, lifecycle and data-retention policy. Providers are replaceable independently; disabling Gmail must not disable notices, and disabling SMS must not remove OTP or lock out all account recovery.
- Course management, course creation, lesson delivery and live class remain distinct plugin entries with shared services and dependency declarations. Disabling one must not delete records or disable unrelated modules.
- Core is limited to protected platform infrastructure: plugin runtime/registry, authentication and recovery primitives, authorization/policy enforcement, branch/guardian isolation, database integrity, audit/security logging, secrets encryption, rate limiting, private-file authorization and backup/update safety. These protected safeguards cannot be disabled or removed by plugins.
- Full module inventory and acceptance definition: `docs/PLUGIN_MODULE_CATALOG.md`. The catalogue documents approved scope; it is not a claim that all listed plugins are implemented.

## D-018 — Learn & Earn and Flash Sale as independent plugins (2026-11-16)
- Owner requires Learn & Earn and Flash Sale to be separate, independently installable/activatable/deactivatable/uninstallable plugins.
- Learn & Earn: Admin authors reusable single MCQs with categories, positive and negative marks; builds exams by selecting saved MCQs; publishes free or paid exams; paid access requires verified server-side payment entitlement; scoring is server-side and preserves immutable question snapshots.
- Flash Sale: Admin selects published courses, sets discounts and campaign windows, publishes campaigns to a public Flash Sale tab/page, and can independently disable the whole page or pause individual campaigns. Base course prices and completed order snapshots must remain unchanged.
- Both plugins must preserve historical records on deactivation and use the verified backup/restore and data-retention safeguards before uninstall. Do not duplicate existing core workflows: audit and safely extract/reuse existing Question Bank, Exam, Question Set and Flash Sale capabilities.
- Detailed acceptance criteria and delivery gates: `docs/LEARN_EARN_AND_FLASH_SALE_PLUGINS.md`. This records approved scope, not completed implementation.
