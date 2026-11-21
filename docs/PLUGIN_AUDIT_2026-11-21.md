# Alom's English Center LMS — Plugin Platform Autopsy (2026-11-21)

**Repository:** `lalchandakand-LMS/finalLMS`  
**Base:** `main`  
**Purpose:** Correct and refresh the earlier plugin audit against the current source. This is a source-level audit, not a production penetration test or staging acceptance.

## Executive result

The plugin platform is further along than the 2026-11-19 audit stated. In-place signed package update, retained version snapshots, package rollback, migration rollback, encrypted key/value settings, version history, and per-plugin health checks are already implemented in the current mainline. These must not be described as wholly missing.

The remaining gap is that the lifecycle still needs stronger operational orchestration and broader adoption. A plugin update is staged as an inactive package and its migrations are deferred until explicit activation; package rollback restores the package/registry snapshot, while migration rollback is a separate guarded action. Neither is a substitute for a complete database backup/restore plan or a rehearsed cPanel recovery procedure.

## Verified implementation present on main

Source paths reviewed:

- `app/Http/Controllers/AdminPluginManagerController.php`
- `app/Services/Plugins/PluginUploadInstaller.php`
- `app/Services/Plugins/PluginPackageRecovery.php`
- `app/Services/Plugins/PluginSettingsManager.php`
- `app/Services/Plugins/PluginManifestValidator.php`
- `resources/views/admin/plugins/index.blade.php`
- `tests/Feature/AdminPluginManagerTest.php`
- `docs/PLUGIN_RUNTIME_CONTRACT.md`

### Package lifecycle and recovery

- Signed ZIP installation verifies the manifest, trusted release signature, package archive and installed-file integrity before storing the package. Installation does not activate code or run plugin migrations.
- Updating an existing plugin requires an installed/inactive state, same immutable plugin key/type, a strictly newer semantic version, valid installed signature and integrity, and satisfied dependencies/conflicts.
- Before an update, the current registry/package metadata is snapshotted in version history. The new package is installed to a version-specific path and remains inactive; previous package files are retained.
- A failed update attempts to restore the previous registry snapshot and leaves the plugin inactive. Invalid-signature regression coverage verifies the old package remains usable.
- Package rollback selects a verified prior snapshot, retains the package being rolled back from as a forward-recovery target, restores the prior package/registry metadata, and leaves the plugin inactive. The feature test exercises rollback in both directions.
- Migration rollback is a separate explicit operation and is restricted to installed/inactive packages. It records history and keeps the plugin inactive.
- Uninstall preserves application data and migration history by default; package recovery data is stored under private application storage with path/symlink and integrity checks.

### Settings, health and audit trail

- Plugin settings are stored through encrypted model values. The manager displays setting keys and update times, never decrypted values; values are entered through password fields and can be deleted.
- Settings are currently generic key/value entries. The manager does **not** yet render a signed-manifest-defined settings schema with typed fields, defaults, required/optional validation, select options, or per-field help.
- Per-plugin health checks re-verify the persisted release signature, manifest schema and immutable registry identity, dependencies/conflicts, and installed-file integrity. A failed check marks an active plugin failed so runtime execution is blocked.
- The Admin manager displays plugin status, signature verification, last health-check state/time, and the latest version-history entries. This is per-plugin health visibility, not a fleet-wide operational dashboard with dependency graph, alerting, queue/scheduler/webhook status, or restore readiness.
- Lifecycle actions and settings changes are activity-logged; version history retains actor/action/time metadata.

### Runtime trust boundary and background work

- Signed packages are trusted PHP application code, not sandboxed code. Signature and integrity verification establish provenance/tamper detection; they do not isolate PHP privileges.
- The runtime contract requires package signatures, manifest identity, canonical private paths and complete file integrity before entrypoint execution. Portal routes and hooks are permission-gated.
- The guarded `PluginJob` / `EnsurePluginJobActive` contract is opt-in. Existing plugin jobs have not all migrated to it.
- Arbitrary scheduler callbacks are not automatically registered/wrapped by the plugin runtime. External provider webhooks and already-dispatched work still need explicit active-state, permission, signature and lifecycle checks.
- Dynamically registered plugin routes are not compatible with Laravel route caching in this phase; activation is blocked while routes are cached.

## Remaining gaps and recommended order

### P0 — release safety

1. Rehearse cPanel staging install, update, deactivate, uninstall, package rollback and migration rollback using a disposable database and real backup/restore.
2. Verify signed release-key provisioning and rotation. Keep private signing keys outside the web root and repository; test revoked/replaced keys and recovery.
3. Add an explicit preflight report before update/activation: compatibility, dependencies/conflicts, permissions delta, migrations pending, route-cache state, disk space and backup readiness.
4. Define and test recovery behavior when package restore succeeds but a database migration or filesystem operation fails part-way through.

### P1 — plugin operations

1. Add manifest-defined typed settings schemas and an Admin form renderer (text, URL, email, number, boolean, select, secret), with server-side validation and secret-safe display.
2. Add a fleet-wide plugin health dashboard: active/failed/unknown counts, signature age, last health check, dependency graph, pending migrations, route-cache warnings and actionable diagnostics.
3. Provide explicit scheduler registration contracts and guarded webhook middleware; migrate each first-party plugin's jobs, scheduled work and provider callbacks to lifecycle-aware contracts.
4. Add queue tests proving deactivation, permission revocation, key revocation and package tampering prevent queued plugin handlers from running, while infrastructure failures retry safely.
5. Add automated update/rollback tests for migration-bearing packages, disk-full/permission failures, interrupted extraction and concurrent lifecycle requests.

### P1 — package extraction

The current catalogue includes 8 executable first-party packages under `plugins/aec/`; the other 56 top-level module directories are metadata-only catalogue manifests. Their application code still resides in the Laravel core. They are not independently installable/upgradable/uninstallable packages yet.

Extract low-coupling integrations first (SMS, email, OTP, storage and provider adapters), then content/tools, and only then high-coupling core modules (courses, batches, enrollments, attendance, exams, payments and live classes). Preserve existing routes/data and require explicit migration ownership and cross-plugin contract tests.

### P2 — full product acceptance

- Browser acceptance for Admin, Branch, Teacher, Student and Guardian portals.
- Real-device Android/iOS acceptance for Student and Instructor apps, including offline behavior, permissions and signed release builds.
- Payment provider sandbox checks, including duplicate callbacks, timeout reconciliation and refund edge cases. Do not activate production payments from registry readiness alone.
- Accessibility, responsive layouts, Bangla-English translation completeness and cPanel installer/updater acceptance.

## Release decision

**Not production-ready.** The signed plugin update/rollback foundation exists, but staging recovery rehearsal, typed settings, operational health visibility, scheduler/webhook lifecycle enforcement, migration-bearing update failure tests, 56-module extraction and full portal/device acceptance remain outstanding.

No production deployment, live migration, live payment action or customer-data change was performed as part of this audit.
