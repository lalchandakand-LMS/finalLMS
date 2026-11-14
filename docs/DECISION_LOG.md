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
- The owner pre-authorizes autonomous feature-branch/PR work and safe PR merges to main when full diff review, exact-head CI/CodeQL, dependency/conflict checks and risk review pass; do not ask for routine merge approval. Production deployment, live payment activation, irreversible production data migrations and app-store releases still require verified backups, rollback/recovery readiness and applicable release safeguards.
- Never call a feature complete based only on UI/menu/table/syntax; require complete user workflow, authorization, automated tests and relevant staging/provider acceptance.

## D-011 — AI handoff and project memory
- Keep README, AI_STUDIO_HANDOFF, PROJECT_STATUS and this decision log aligned.
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

- The approved eBook/PDF store scope in D-007 is being implemented in a focused Laravel PR, not by merging the stale broad commerce PR.
- Paid eBook access must remain private and must be granted only after an Admin independently verifies the manual payment. Free eBooks may be fulfilled immediately.
- A student must not be able to repeatedly check out an already-active entitlement to reset its download limit. Expired or exhausted entitlements may be repurchased.
- A purchased PDF cannot be replaced or deleted while order history exists; archive the product instead. Store PDF files and payment proofs on the private local disk and serve them only through authorization-checked controllers.
- PR #124 is an implementation tranche, not a complete commerce system. It does not activate bKash/Nagad/SSLCommerz, automate payment verification, provide refunds/revocation UI, or satisfy staging/production acceptance.
- Merge only after exact latest-head Laravel CI, CodeQL, full diff review and migration compatibility checks pass. Production rollout remains a separate release gate.

