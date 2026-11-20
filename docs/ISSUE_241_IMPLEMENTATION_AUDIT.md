# Issue #241 — implementation and compatibility audit

**Repository baseline:** `main` at `b4a8ec3baec3ff25e9468b6d574c8735fc62eb49`  
**Audit scope:** existing plugin runtime, manager, package installer, lifecycle, portal routing and first-party packages.  
**Status:** foundation exists; the universal platform acceptance criteria are not yet complete.

## Existing implementation

| Area | Existing code | Current behavior |
|---|---|---|
| Private package storage | `plugins/`, `plugins/.staging/`, web-server deny rules | Application-root package directory; staging is private and outside public assets |
| Manifest validation | `PluginManifestValidator` | Validates schema version, identity, compatibility declarations, paths, dependencies, conflicts, portals and permission strings |
| Signed releases | `PluginSignatureVerifier` | Ed25519 release signature and ZIP SHA-256 are checked against configured trusted public keys |
| ZIP extraction | `PluginArchiveInstaller` | Enforces archive size/count/ratio limits, rejects unsafe paths, symlinks, special entries and collisions, then installs atomically |
| Installed-file integrity | `PluginIntegrityVerifier` | Rejects modified, missing, extra or linked files before plugin code is loaded |
| Registry and lifecycle | `PluginInstallation`, `PluginLifecycle` | Persists package identity and lifecycle transitions |
| Runtime | `PluginRuntime`, `PluginContract` | Loads active, trusted and integrity-verified entrypoints; failed packages are blocked |
| Route boundary | `PluginContext::routesForPortal()`, `EnsurePluginActive` | Supports declared portal routes with authentication, email verification and role middleware; active state is rechecked per request |
| Admin lifecycle UI | `AdminPluginManagerController`, Plugin Manager view | Signed ZIP install, activate, deactivate and uninstall; uninstall retains application data and migration records |
| Plugin migrations | `PluginMigrationRunner` | Tracks plugin migrations and checks installed-file integrity |

The current repository includes a first-party Universal AI Assistant package. Popup Ads and Announcements was merged into `main` in PR #267. Existing module registry entries and builders are not yet all independently installable packages.

## Gaps against Issue #241

### P0 — authorization and isolation

- **Implemented in this branch:** authenticated plugin route groups must name a permission present in the signed manifest. Runtime middleware checks that permission against the manifest and the administrator-approved `metadata.granted_permissions` list. Missing grants are denied by default.
- **Implemented in this branch:** the Admin Plugin Manager can grant/revoke only manifest-declared permissions. Changes are audited and take effect on the next request; a package cannot grant itself permission.
- **Applied to Universal AI Assistant:** Admin routes require `ai-assistant.configure`; Branch Manager, Teacher, Student, Guardian and Support Staff routes require `ai-assistant.use`.
- **Implemented in this branch:** `PluginResourceAccess` plus `plugin.resource:{route-parameter}` middleware protects bound Student, Course, Batch and Enrollment records. Branch Managers are restricted to their active branch; Teachers to assigned courses/batches and enrolled students; Students to their own records; Guardians to approved active linked students. Unknown resource models are denied.
- **Implemented in this branch:** `PluginResourceAccess::scopeQuery()` applies actor-scoped query constraints for User, Course, Batch and Enrollment collections, with deny-by-default behavior for unsupported roles.
- **Still required:** report/export pipelines and nested relation queries must scope every underlying query as well; plugin developers must use the helper before pagination, aggregation or serialization and must not fetch unrestricted collections then filter them afterward.

### P1 — lifecycle completeness

- The current uploader rejects an already registered plugin and explicitly reports that in-place updates are not enabled.
- There is no complete version history, staged update, known-good rollback or compatibility-aware update workflow.
- The manager does not yet expose a full health dashboard, detailed preflight report, configurable settings schema or secret-field UI.
- Dependency checks exist for activation/deactivation, but dependency graph diagnostics and complete conflict/version resolution need expansion.
- Installation and activation need distinct side effects. This PR changes installation to extract and verify only; plugin migrations are deferred until explicit activation preflight.

### P1 — runtime integration contract

- Route registration and API route helpers, typed action hooks, portal navigation and permission grants are partially implemented. A new opt-in `PluginJob` base class and execution middleware now recheck plugin active state and Admin-granted permission, cryptographically re-verify the persisted Ed25519 release signature against the current trusted key, and verify every installed file against its integrity manifest immediately before queued handlers run. Stale jobs are dropped fail-closed.
- This does not yet provide scheduler registration, automatic wrapping of arbitrary jobs, provider-webhook lifecycle guards or migration of every existing plugin job. Each package must adopt the base class and prove deactivation/revocation behavior before claiming background-work isolation.
- Deactivation must be tested against every actual entry point, not only web routes.
- Secret storage/configuration needs a plugin-scoped encrypted settings contract with redaction in HTML, logs and exports.

### P2 — first-party module coverage and migration

- Existing Website Builder, Course Builder, Course Content Studio, Add-on Registry, AI Developer and other optional modules need a compatibility inventory and controlled migration into first-party packages without duplicating routes or data.
- Course Management, Course Create, Course Builder, curriculum/lesson management, recorded courses and Live Class need independently managed package/module entries or documented sub-plugin boundaries.
- SMS, email/Gmail, OTP/verification, Notice Board, payment providers, storage providers, analytics and AI integrations need separate provider-neutral adapters and lifecycle controls.
- Every portal-facing package needs explicit portal coverage, server-side authorization tests and data-scope tests.

## Delivery sequence

1. **Lifecycle safety:** separate package install from migration execution; preserve signed-package and installed-file verification. Add regression tests.
2. **Queued-work boundary:** require `PluginJob` for plugin-owned queue tasks, with active/trusted/permission rechecks at execution time; then add scheduler registration and webhook/event lifecycle guards.
3. **Permission contract:** administrator-approved permission grants, per-route permission requirements and tests for denied access.
4. **Portal data scopes:** reusable branch, teacher/course, student/enrollment and guardian-child authorization helpers; require their use in first-party portal plugins.
5. **Update and recovery:** staged version install, dependency/compatibility preflight, backup policy, atomic switch, retained previous version and tested rollback.
6. **Manager completeness:** catalogue/discovery, settings schema, secret fields, health checks, audit history and version history.
7. **First-party migration:** convert optional modules/builders and integrations in small, independently testable packages while preserving existing data and routes.
8. **Release gates:** exact-head Laravel CI, CodeQL, cPanel package tests, upgrade/restore tests and staging/browser/provider acceptance. Production deployment remains separate.

## Explicit non-goals for this change

- No direct production deployment or activation.
- No arbitrary uploaded PHP/JavaScript execution.
- No automatic permission grant from a package manifest.
- No deletion of LMS/customer data during package uninstall.

## Health checks and version history

- **Implemented:** explicit Admin health checks validate manifest/registry identity, current trusted-key configuration, persisted release signature (for new installs), dependencies/conflicts and installed-file hashes. An active plugin failing runtime verification is moved to `failed`; a failed package can be returned to `inactive` only after a successful health check.
- **Implemented:** plugin version/lifecycle history records install, activation, deactivation, uninstall and health-check events with package version/checksum, signing key ID, actor and safe metadata.
- **Still required:** staged version upgrades, immutable side-by-side package versions, dependency-aware update preflight, backup/atomic switch and tested rollback. Legacy packages installed before persisted detached signatures were added rely on their original verification timestamp plus installed-file integrity; new packages re-verify the stored detached signature against the current trusted key.
