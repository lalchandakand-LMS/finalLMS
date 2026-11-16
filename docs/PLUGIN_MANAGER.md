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
7. The staged ZIP is deleted. The action is written to the admin activity log. No plugin entrypoint or migration is executed during installation.

A failed, not-yet-installed registry entry can be retried after correcting the
package or signature. An existing installed version cannot be overwritten.
In-place updates are not enabled yet. Signed plugin migrations are executed only during explicit activation preflight, after installed-file integrity verification; installing a package alone does not mutate application data.

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
data. Uninstall removes the executable package only after the plugin is inactive;
database records and migration history are retained. Admins can run health checks
to revalidate signatures, dependencies and installed-file integrity. Package
upgrade, multiple installed package versions and package-version rollback are
not enabled. Migration rollback is separate from package rollback.

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

Lifecycle history records the plugin key/version, package checksum, signing key ID, action, resulting status, actor and safe details for install, activate, deactivate, uninstall and health-check events. It does not yet implement package upgrades, multiple installed versions or rollback.
