# Admin Plugin Manager

The Admin Plugin Manager is the guarded web entry point for signed plugin
installation. It is available only to the root admin role under the authenticated
Admin workspace.

## Install flow

1. Admin uploads a ZIP archive (default maximum 50 MiB).
2. Admin supplies the exact manifest JSON, trusted release key ID and detached
   Base64 Ed25519 signature.
3. The server validates the manifest, checks the private staging directory,
   creates a registry record and advances it through discovered, validated and
   staged.
4. The signature verifier checks the configured trusted public key and binds the
   manifest to the SHA-256 checksum of the exact uploaded ZIP bytes.
5. The secure archive installer rechecks the checksum and safely extracts the
   package into plugins/vendor/plugin/version.
6. The registry is marked installed only after the atomic filesystem move.
7. The staged ZIP is deleted. The action is written to the admin activity log. No plugin entrypoint is executed. After extraction and installed-file integrity metadata are recorded, signed migrations are run while the plugin remains inactive.

A failed, not-yet-installed registry entry can be retried after correcting the
package or signature. An existing installed version cannot be overwritten.
In-place updates are not enabled yet. Signed migrations run after package extraction and installed-file integrity verification, before installation reports success; the plugin remains inactive until explicit Admin activation. If a migration fails, the verified package and failed migration record are retained because MySQL DDL may have partially committed and deleting the source would prevent safe recovery.

## Example manifest

    {
      "schema_version": 1,
      "key": "aec.example-plugin",
      "name": "Example Plugin",
      "version": "1.0.0",
      "type": "module",
      "entrypoint": "src/Plugin.php",
      "requires": {
        "php": "^8.3",
        "laravel": "^12.0",
        "lms": "^1.0"
      },
      "dependencies": [],
      "conflicts": [],
      "portals": ["admin", "teacher", "student"],
      "permissions": ["example.manage"],
      "capabilities": ["example"]
    }

The detached signature must be generated over the exact canonical payload from
PluginSignatureVerifier::payloadForSignature($manifest, $sha256). See
PLUGIN_SIGNATURE_VERIFICATION.md for the trusted public key configuration and
canonical signing rules.

## Available lifecycle controls and remaining gaps

The Admin Manager supports signed-package install, permission grants, activate,
deactivate, health checks, migration rollback and uninstall. Activation rechecks
the trusted key, manifest identity, dependencies and installed-file hashes, skips
previously completed migrations and transitions the registry to active. The
runtime loads only active verified packages. Deactivation blocks plugin routes
and preserves installed files and application data. Uninstall removes executable
package files while retaining application data and migration history.

An Admin can request rollback of all completed migrations for one installed,
inactive plugin version. The action re-verifies the installed signature, manifest
identity and package file hashes, requires an explicit confirmation, and records
the result in version history and the activity log. Migration rollback may remove
or alter plugin data: take and verify a database backup first, and use the control
only after reviewing every migration's down() implementation. It does not restore
a previous plugin package or reverse arbitrary external side effects.

In-place package update and package-version rollback controls are not implemented
yet. Do not overwrite an installed version or manually replace plugin files.
Prepare a new signed version and complete a reviewed staging migration/rollback
process before enabling update support. A plugin being installed does not mean
its PHP entrypoint is loaded or executed. Manifest permissions are stored for
review and are not automatically granted.

## Portal resource authorization

Plugin controllers must protect every route that loads an individual LMS resource through route model binding. Add the resource middleware to the route after declaring the route parameter:

```php
$context->routesForPortal('teacher', 'teacher/plugin-courses', 'teacher.plugin-courses.', function (): void {
    Route::get('/{course:slug}', [CourseController::class, 'show'])
        ->middleware('plugin.resource:course')
        ->name('show');
}, 'course.read');
```

The plugin manifest must declare `course.read` in its `permissions` list, and a root Admin must grant it in Plugin Manager. The portal group still applies the plugin-active, authenticated, verified and role checks; the resource middleware adds the per-record scope.

The middleware accepts only bound Eloquent models and currently supports `User` (student records), `Course`, `Batch` and `Enrollment`. Unknown model types and unauthorized records return 404. Root Admin can access supported resources; Branch Managers are restricted to their active branch; Teachers to assigned courses/batches and their enrolled students; Students to their own enrollments; Guardians to students connected by an approved active guardian link.

For collection endpoints, apply the same policy before pagination or aggregation:

```php
$courses = app(PluginResourceAccess::class)
    ->scopeQuery(Course::query(), $request->user())
    ->paginate();
```

`scopeQuery()` supports User (student records), Course, Batch and Enrollment queries. It returns only records visible to the current actor and denies unsupported roles by returning an empty result. Branch Managers see direct course enrollments only when the learner belongs to their active branch; batch enrollments must also belong to that branch. Admin access is unrestricted for these supported models.

The middleware protects individual bound resources; the query helper protects the collection query itself. Reports, exports and nested relations must scope every underlying query as well. Never fetch an unrestricted collection and filter it in the view or after serialization.

## Plugin health and version history

Plugin Manager provides an explicit health check for installed, active, inactive and failed packages. It validates the manifest and registry identity, checks the current trusted signing key, re-verifies the persisted detached release signature when available, checks dependencies/conflicts, and verifies every installed file hash before reporting healthy.

If an active plugin fails runtime signature or integrity verification, the runtime transitions it to `failed` so its routes are blocked. A failed package can return to `inactive` only after an administrator runs a successful health check. A health check does not execute plugin entrypoints or migrations.

Lifecycle history records the plugin key/version, package checksum, signing key ID, action, resulting status, actor and safe details for install, activate, deactivate, migration rollback, uninstall and health-check events. It does not yet implement package upgrades, multiple installed versions or package-version rollback.


## Migration failure and manual recovery

Plugin migrations are recorded by the unique identity (plugin key, package version,
migration filename). A migration is atomically claimed before execution. A
`completed` record is skipped; a `failed` record or a stale `running` record
blocks automatic re-execution. This is intentional: MySQL DDL may commit even when
a later statement in the same migration fails, so retrying blindly can duplicate
or corrupt schema/data.

When installation reports a migration failure:

1. Keep the plugin inactive. Do not delete its retained, signature-verified package
   or edit its files; the installed-file integrity check must remain valid.
2. Take a database backup and preserve the relevant application/server logs.
3. Inspect the exact migration file from the verified package and compare every
   intended table, index, column and data change with the live database.
4. Determine whether the migration made no changes, made a partial change, or
   completed its effects before the process failed. Review dependent migrations
   and application data before deciding on a recovery.
5. Have a qualified database operator reconcile the schema/data using a reviewed,
   reversible SQL plan. Do not simply delete the migration ledger row or change
   `failed`/`running` to `rolled_back`.
6. Only after the database is confirmed to match the migration's fully rolled-back
   state may an authorized, audited recovery procedure mark the attempt
   `rolled_back`, allowing the normal runner to claim it again. If the migration
   was actually completed, recovery must instead reconcile the ledger as
   completed through a separately reviewed procedure; never re-run it.
7. Re-run the migration only after backup, integrity verification and recovery
   review, then run plugin health checks and relevant application tests before
   activation.

There is currently no self-service Admin button to clear or retry a failed/stale
migration. This is deliberate until an audited recovery workflow can inspect
database state and require explicit operator approval. Contact the deployment
maintainer rather than editing migration ledger rows directly.
