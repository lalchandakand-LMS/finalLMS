# Owner Decision Log — Alom's English Center LMS

Last consolidated: 2026-11-10

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
- No merge to protected main, production deployment, live payment activation or app-store release without explicit owner approval.
- Never call a feature complete based only on UI/menu/table/syntax; require complete user workflow, authorization, automated tests and relevant staging/provider acceptance.

## D-011 — AI handoff and project memory
- Keep README, AI_STUDIO_HANDOFF, PROJECT_STATUS and this decision log aligned.
- Every meaningful change must record: date, branch, PR, commit SHA, files/modules touched, behavior changed, tests run/results, known limitations, next task and merge/deployment status.
- AI agents must read these files and inspect actual source before coding. They must not invent implementation or CI status.
- Preserve a dated history; do not erase previous decisions without recording why they changed.
