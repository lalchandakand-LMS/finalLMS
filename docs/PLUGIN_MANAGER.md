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

The Admin Manager currently supports signed-package install, permission grants,
activate, deactivate and uninstall. Activation rechecks the trusted key,
manifest identity, dependencies and installed-file hashes, skips previously completed migrations and transitions the registry to active. The runtime loads
only active verified packages. Deactivation blocks plugin routes and preserves
installed files and application data. Uninstall removes executable package files
while retaining application data and migration history.

In-place update and rollback controls are not implemented yet. Do not overwrite
an installed version or manually replace plugin files. Prepare a new signed
version and complete a reviewed staging migration/rollback process before
enabling update support. A plugin being installed does not mean its PHP
entrypoint is loaded or executed. Manifest permissions are stored for review
and are not automatically granted.

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
