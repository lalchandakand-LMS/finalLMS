# Universal Plugin Platform — Architecture and Delivery Contract

Status: approved architecture; implementation is tracked in Issue #241.
Target: Laravel 12, PHP 8.3+, MySQL/MariaDB, shared cPanel hosting.

## 1. Product decision

Alom's LMS will use one universal plugin platform across all five operational panels: unified Admin, Branch Manager/Branch Panel, Teacher, Student and Guardian. The current Add-on Registry is only a fixed allow-list switch; it is not the requested installable plugin system.

All first-party and approved third-party plugin packages live under one application-root `plugins/` directory. This directory must be outside the public document root, denied by web-server rules as defense in depth, and excluded from public media/static serving. Plugin packages are never uploaded into `public/`.

**Universal coverage rule:** every optional module and integration—not only third-party APIs—must be represented in the Plugin Manager. This includes SMS, Gmail/Google mail, email delivery, OTP and account verification, notice boards, AI features, authentication add-ons, portal widgets, builders, reports and operational tools. A module may be delivered as a first-party plugin bundled with the LMS; it does not have to be downloaded from an external marketplace. Core safety, authorization, database integrity and the minimum login/recovery path remain protected platform services and cannot be disabled by a plugin.

## 2. Admin lifecycle

Admin → Plugin Manager will provide:
- Discover and view installed/available packages.
- Upload/install trusted package, inspect manifest and preflight report.
- Configure settings and credentials without displaying stored secrets.
- Activate, deactivate, update and uninstall.
- View version, author, compatibility, dependencies, conflicts, supported portals, requested permissions, health state, install/update history and audit events.
- Restore/disable a failed plugin using a safe staged lifecycle.
- Filter by module category, portal, provider, status and whether a plugin is first-party or external.
- Enable/disable a module per supported portal or branch where explicitly supported, without allowing branch users to change global security policy.

A package lifecycle is:
`discovered → validated → staged → installed → active`
with safe transitions to `inactive`, `update-staged`, `failed`, and `uninstalled`.
The registry state is authoritative; directory presence alone never enables code.

## 3. Package contract

Each plugin has a unique immutable key and a versioned JSON manifest, for example:

```json
{
  "schema_version": 1,
  "key": "aec.analytics.meta-pixel",
  "name": "Meta Pixel",
  "version": "1.0.0",
  "type": "integration",
  "entrypoint": "Plugin.php",
  "requires": {
    "php": "^8.3",
    "laravel": "^12.0",
    "lms": ">=1.0.0"
  },
  "dependencies": [],
  "conflicts": [],
  "portals": ["admin", "public"],
  "permissions": ["analytics.configure", "analytics.emit"],
  "capabilities": ["settings", "consent-aware-tracking"],
  "migrations": "database/migrations"
}
```

The manifest is declarative. It cannot grant itself permissions or bypass platform policy. The Admin must explicitly approve requested capabilities. Unknown manifest fields, unsupported schema versions, duplicate keys, invalid paths, dependency cycles and incompatible requirements fail closed.

## 4. Security and trust model

- Accept only packages signed by a provisioned trusted release key. Verify signature and SHA-256 before extraction; key provisioning and rotation are separate audited operations.
- Upload to a private staging directory, validate archive entry count/expanded size, reject traversal, absolute paths, symlinks, hard links, special files, duplicate paths and executable files in public locations.
- Verify package identity, manifest schema, compatibility, dependencies, conflicts and required permissions before activation.
- Extract atomically into `plugins/<vendor>/<plugin-key>/<version>`; retain the previous known-good version for rollback.
- Never execute an untrusted package during validation. Plugin PHP is loaded only after signature, manifest, compatibility and Admin activation checks pass.
- No plugin may modify protected core files, .env, Composer dependencies, deployment scripts, or another plugin's directory.
- Secrets are encrypted at rest where stored in the database, redacted from HTML/logs/exports and never committed to Git. Prefer server environment variables for high-value credentials.
- Every lifecycle/configuration action is permission-checked, CSRF-protected, rate-limited where relevant and audit logged.
- Deactivation must disable routes, API endpoints, jobs, scheduled tasks, event listeners, hooks and portal navigation—not merely hide the menu.
- No shell/Composer execution at runtime; package must be compatible with cPanel shared hosting and prebuilt release artifacts.

## 5. Runtime extension contract

Plugins integrate through explicit platform interfaces, not arbitrary file inclusion:
- Service provider / boot contract registered only for active, verified packages.
- Typed event and action hooks with documented payloads and bounded execution.
- Namespaced, collision-checked route registration with middleware and permission declarations.
- Admin settings schema with validation, secret fields, defaults and health checks.
- Portal navigation/widgets declared in manifest and filtered by portal + server-side permission.
- Queue jobs and scheduled work namespaced, rate-limited, retry-safe and disabled when plugin inactive. Plugin-owned queue work must extend `App\Jobs\Plugins\PluginJob`, which rechecks active state, trusted signing key and Admin-granted permission at execution time; scheduled callbacks must dispatch guarded jobs rather than execute plugin code directly. Scheduler registration and webhook/event lifecycle adapters remain follow-up work.
- Migrations are versioned and tracked per plugin; destructive migrations require backup/preflight and explicit data-retention policy.
- Module capability contracts define stable interfaces for replacing a provider (for example SMS vendor A with vendor B) without rewriting OTP, notices or business workflows.
- Core workflows call provider interfaces; they must not contain hard-coded provider credentials or depend directly on a single vendor SDK.

## 6. Five-panel support

Each plugin declares supported portals: `admin`, `branch`, `teacher`, `student`, `guardian`, and/or `public`.
- Admin controls installation, permissions and configuration centrally.
- Branch modules always enforce assigned-branch scope server-side.
- Teacher modules enforce assigned courses/batches and ownership.
- Student modules enforce enrollment and personal-record access.
- Guardian modules require a verified guardian-child relationship for every read, write, export, download and notification.
A plugin cannot widen a user's role or bypass existing policies. Disabled plugins return unavailable/404 for all endpoints and reject queued work.

## 7. First-party plugin families and complete module coverage

Every item below must have an independently managed plugin/module entry (or a clearly documented sub-plugin) with its own settings, permissions, health status and lifecycle. Shared dependencies must be declared; turning off one provider must not unexpectedly disable unrelated modules.

### 7.1 Communication and identity verification
- SMS gateway/provider plugins (provider-neutral adapter; Bangladesh providers and international gateways can be added independently).
- Gmail / Google Workspace mail integration using supported Google OAuth/API or SMTP configuration; generic SMTP and other email providers remain separate selectable adapters.
- Email templates, transactional email delivery and delivery logs.
- OTP delivery and verification plugins for SMS and email, including expiry, resend cooldown, attempt limits, abuse throttling, replay prevention and audit trail.
- Student/guardian/teacher phone or email verification flows; optional login MFA and new-device verification.
- Password reset and account recovery channels, with a protected fallback so disabling a delivery provider cannot lock out the entire institution.
- Notice Board / announcements: admin, branch, teacher, student and guardian views; audience targeting; scheduling; read receipts; attachments; urgent notices; bilingual content; optional SMS/email/push delivery adapters.
- In-app notifications, push notification providers and notification preferences.
OTP verification is a workflow plugin using the platform's protected identity and rate-limit primitives. Never store plaintext OTPs; store a keyed hash, compare safely, expire promptly, invalidate after success, and avoid revealing whether an account exists.

### 7.2 Academic and institution modules
- Admission/application, student profiles, enrollment/approval and batch management.
- Branch management and branch dashboards.
- Teacher profiles, assignments, timetable and workload tools.
- Course catalogue, curriculum, lessons, recorded video and live-class provider integrations.
- Attendance, exams, quizzes, question banks, assignments, grading, results and certificates.
- Fees, invoices, discounts, payment records, due reminders, refunds and financial exports.
- Guardian-child linking, guardian dashboard, progress reports and attendance/fee notifications.
- E-books/resources, downloads, learning progress and completion tracking.
- Reports, analytics, audit exports, imports, backup/restore controls and institution settings.
Core records and authorization remain governed by platform policies. Plugins may add workflows and UI, but may not bypass data ownership, branch scope or financial audit rules.

### 7.3 Integrations
- Google Analytics 4, Google Tag Manager, Meta Pixel and Google Search Console site verification/API where supported.
- Google Drive and S3-compatible storage.
- SMS/email providers.
- Payment gateways (bKash, Nagad, SSLCommerz and other supported providers).
Provider configuration is not proof of live integration. Each provider requires official docs, merchant approval, secret setup, signed callback verification, idempotency, sandbox tests and reconciliation before activation.

Analytics and marketing tags must respect consent choices, regional privacy requirements, and public/student portal data minimization. Never send student identity, grades, phone, payment or guardian data to advertising pixels.

### 7.4 Builders and CMS
- Slider / hero carousel builder.
- Post / blog / news builder.
- Course / curriculum / lesson builder.
- Page / landing-page builder.
- Menu, header/footer, popup, banner and form builders.
- Reusable blocks, templates, SEO metadata, media library and bilingual content tools.
Existing Website Builder, Course Builder, Course Content Studio and Blog/News workflows must be integrated or wrapped by plugins without duplicating tables or breaking current content. Existing builder data remains core-owned until a reviewed migration proves otherwise.

### 7.5 AI Builder and AI Developer
The existing AI Developer and AI-assisted builder functionality become first-party AI plugins using the same lifecycle and permissions. AI may generate draft page sections, sliders, posts, course outlines, lesson drafts, quizzes, question suggestions, notices, translations and SEO metadata. Generated output is preview-only until an authorized human reviews and publishes it. Provider API keys stay server-side; apply role-based access, prompt/output audit, rate and spend limits, sensitive-data minimization, output validation and explicit human approval. AI must not install plugins, change permissions, delete data, publish content or deploy code autonomously.

### 7.6 Plugin availability matrix
- Admin: install/configure/activate/deactivate, global policy, provider credentials, audit and health.
- Branch Manager: only assigned-branch module settings and branch-scoped notice/audience workflows when granted.
- Teacher: assigned course/batch teaching tools, notices, attendance, assignments and grading.
- Student: enrolled learning, notices, OTP/account verification, quizzes, assignments and progress.
- Guardian: verified linked-student notices, attendance, results, fees and progress.
The Admin can control which optional features appear in each portal. Hiding a menu is never treated as authorization; every endpoint and data query must enforce permissions independently.

## 8. Install, update and uninstall safety

Install/update:
1. Admin uploads a signed package.
2. Verify signature/checksum and package limits before extraction.
3. Validate manifest, compatibility, dependencies, conflicts and permission requests.
4. Stage privately; run read-only preflight and migration compatibility checks.
5. Create and verify backup before data-changing migration.
6. Admin confirms activation; register plugin atomically and run approved migrations.
7. Run health checks; on failure disable plugin and preserve prior version for recovery.

Uninstall:
1. Deactivate first and verify all entry points are disabled.
2. Check dependent plugins and data references.
3. Create verified backup and show the Admin a data-retention report.
4. Default to retaining plugin-owned records/files in a private archive; destructive purge is a separate explicit action with a retention window.
5. Run only reviewed reversible cleanup migrations; never silently delete courses, posts, student records, payments, guardian links or uploaded media.

## 9. Compatibility with current code

Before runtime implementation, inventory:
- `AddonRegistryController` and `resources/views/admin/addons` (current allow-list).
- GA4/GTM/Meta Pixel consent handling and service configuration.
- Existing Google Drive/S3 storage connections.
- Website Builder, homepage presets, slider, Website Pages and Blog/News.
- Admin Course Builder and Course Content Studio.
- AI Developer controller, models, UI and any provider abstraction.
- SMS/email/OTP implementations, verification controllers, mail configuration and notice-board/announcement code.
- All five panel routes, middleware, policies, navigation and API routes.

Migrate one module at a time. Preserve existing route names, database records, settings and URLs. Keep a compatibility bridge until each module is proven under the plugin runtime.

## 10. Delivery phases and acceptance

Tracked by Issue #241:
1. Source inventory and contract tests.
2. Plugin manifest/catalogue, registry schema, discovery, lifecycle state machine and Admin UI.
3. Signed package staging/verification/install/update/deactivate/uninstall.
4. Portal extension APIs and first-party academic, communication and builder plugins.
5. Integration plugins and consent/secret controls.
6. AI Builder/AI Developer integration.
7. Full tests, cPanel packaging, docs and staging acceptance.

Required tests include lifecycle transitions, tampered/unsigned package, traversal/zip-bomb/symlink, incompatible version, missing dependency/cycle/conflict, secret redaction, unauthorized admin action, disabled plugin routes/jobs, branch isolation, guardian-child isolation, OTP expiry/replay/rate limits, notice audience isolation, migration rollback, retained data, bilingual UI and cPanel package build.

No production plugin installation or activation until exact-head CI/CodeQL pass and isolated cPanel staging install/upgrade/backup/restore/browser acceptance is complete.

---

## Current implementation status (2026-11-17)

The following capabilities are present in the current mainline code; this is a code inventory, not a production-readiness claim:

- Private `plugins/` directory with private staging and web-server deny rules.
- Signed ZIP installation, manifest validation, SHA-256 archive binding, safe extraction limits and installed-file integrity records.
- Admin activation/deactivation, compatibility checks for PHP/Laravel/LMS, dependency/conflict preflight, tracked plugin migrations, health checks, permission grants and data-preserving uninstall.
- Verified runtime loading through `PluginContract`, portal route registration, active-state middleware, hook/capability registry, portal navigation and resource-scope helpers.
- First-party Popup Ads and Universal AI Assistant package source are present under the private plugin tree.
- Automated tests cover package safety, signatures, integrity, runtime loading, lifecycle and selected role/branch/guardian boundaries.

The following remain incomplete or require external acceptance before being called production-ready:

- Signed in-place plugin updates, side-by-side versions, safe rollback and verified backup/restore orchestration.
- A full marketplace/catalogue and a complete plugin lifecycle UI for every optional core module.
- Comprehensive queue/scheduler/webhook isolation and cancellation for every background entry point.
- Migration of all existing academic, finance, communications, OTP, notice-board, CMS/builder and portal modules into independently managed first-party plugins without duplicate data stores.
- Provider-specific sandbox/merchant acceptance for SMS, email, payment and storage integrations.
- Full browser acceptance across Admin, Branch Manager, Teacher, Student and Guardian portals; disposable cPanel install/upgrade/backup/restore rehearsal; and real-device mobile QA where applicable.

A green CI run verifies the tested code on that commit. It does not prove live hosting, external provider credentials, mobile hardware, customer data migration or production acceptance. Keep production deployment and live plugin activation gated until the relevant staging, backup, rollback and owner acceptance checks are complete.
