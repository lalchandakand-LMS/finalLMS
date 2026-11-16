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
7. The staged ZIP is deleted. The action is written to the admin activity log. Plugin entrypoints are never executed during extraction. After installed-file hashes are recorded, the upload workflow runs only the signed migrations declared by the manifest; the plugin remains inactive until an Admin activates it.

A failed, not-yet-installed registry entry can be retried after correcting the
package or signature. An existing installed version cannot be overwritten. Package updates are available only for an installed or inactive plugin; active plugins must be deactivated first. The update must retain the plugin key and type and use a strictly newer version. The current package signature and installed-file hashes are verified before update. The new ZIP is signature-verified and extracted to a new version directory; neither the previous version directory nor customer/plugin data is overwritten or deleted. Existing permission grants are intersected with the new manifest permissions so an update cannot silently grant new capabilities. A snapshot of the previous signed manifest, package path, checksum, signing key, and integrity metadata is recorded in version history before the registry switches to the new version. A migration failure leaves the new package failed/inactive and retains the previous package for recovery; database migrations may partially apply on MySQL/MariaDB and must be reviewed before retry or rollback. Package migration rollback verifies the current and retained packages, moves the registry into a non-activatable staging state before running down() migrations, and marks the plugin failed if rollback is interrupted.

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

## Available lifecycle controls

The Admin Plugin Manager supports signed package installation, permission grants,
activation, deactivation, health checks and uninstall. Activation verifies the
trusted signature, manifest identity, declared dependencies/conflicts and every
installed-file hash before running the signed migrations. The plugin remains
inactive when installation or migration fails. Third-party entrypoints are not
loaded directly by the core runtime; registered plugin routes remain subject to
plugin-active, permission and resource-scope middleware.

Deactivation blocks plugin routes immediately and retains package files and LMS
data. Uninstall requires an explicit server-validated confirmation and removes
only the executable package after the plugin is inactive; database records,
encrypted settings, customer/LMS data and migration history are retained. The
uninstalled registry state is written before filesystem cleanup. If cleanup is
interrupted, the Admin Manager exposes an idempotent "Retry package cleanup"
action; it never reactivates the plugin or deletes retained data. This is a
non-destructive package removal policy, not an automated database snapshot:
take and verify the normal database/files backup before any production
uninstall. Admins can run health checks to revalidate signatures, dependencies
and installed-file integrity. Package updates retain immutable version
directories and a signed registry snapshot.
Package-version rollback is available for installed/inactive plugins only; it
verifies both package signatures and installed-file hashes, reverses the current
version's recorded migrations, restores the retained package, and leaves the
plugin inactive. A failed or interrupted migration rollback marks the plugin
failed so it cannot be activated until an administrator performs recovery.
Take a database backup first: migration down() methods may be destructive or
only partially reversible on MySQL/MariaDB.

## Encrypted plugin settings

Plugin Manager lets an Admin save a plugin-specific key/value setting. Values are
encrypted using Laravel's application encryption key before persistence. The
manager displays setting names and update timestamps only; it never renders or
returns decrypted values, writes them to audit descriptions, or flashes them
back into the session after validation errors. Plugin code retrieves a value through
PluginSettingsManager::get($plugin, $key). Settings remain stored when a plugin
is deactivated or uninstalled so reinstall/recovery does not silently discard
credentials. Protect APP_KEY and include it in encrypted-backup recovery
procedures; changing it without re-encrypting settings makes stored values
unreadable. Migration rollback refuses to drop a non-empty settings table.

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

Lifecycle history records the plugin key/version, package checksum, signing key ID, action, resulting status, actor and safe details for install, update, activate, deactivate, uninstall, health checks and package rollback. Package updates retain the previous signed manifest, package path, checksum, signing key, signature timestamp and installed-file hashes before switching the registry. The previous immutable package directory is retained. Package rollback is available only while the current plugin is installed or inactive; it verifies both package signatures and installed-file hashes, reverses only the current version's recorded migrations, restores the previous registry snapshot, and leaves the plugin inactive. Take a database backup first: migration down() methods can be destructive or only partially reversible on MySQL/MariaDB. A failed rollback does not reactivate the plugin.
