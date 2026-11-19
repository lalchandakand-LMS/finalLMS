# Admin Plugin Manager

The Admin Plugin Manager is a root-admin-only workspace in the authenticated Admin panel. It manages signed packages stored under the private application-root `plugins/` directory. A plugin is trusted application code, not sandboxed code; install only releases signed by a trusted release authority.

## Current package installation flow

1. The Admin uploads a ZIP (default maximum 50 MiB), the exact manifest JSON, a trusted release key ID and a detached Base64 Ed25519 signature.
2. The server validates the manifest and private staging path, computes SHA-256 over the exact ZIP bytes, and verifies the signature against `PLUGIN_TRUSTED_PUBLIC_KEYS`.
3. The archive installer rechecks the checksum and rejects unsafe paths, links/special files, duplicate/case-colliding paths, excessive entries, oversized files, excessive expansion/compression ratios and unsafe destinations.
4. Files are streamed into private staging and moved atomically to `plugins/<vendor>/<plugin>/<version>`. Installed-file SHA-256 hashes are stored in the registry.
5. The registry becomes `installed` only after the move succeeds. The staged ZIP is removed. Installation does not execute the entrypoint or run plugin migrations.

A failed pre-install record can be retried after correcting the package. An installed version cannot be overwritten in place.

## Activation and deactivation

Activation is available for `installed` or `inactive` packages. Before changing the registry to active, the controller:

- rejects activation while Laravel routes are cached;
- re-verifies the detached release signature and current trusted key;
- validates manifest schema and registry identity;
- checks PHP, Laravel and LMS version constraints;
- checks declared dependencies and conflicts;
- verifies the complete installed-file integrity manifest;
- runs only the signed package's tracked migrations;
- records lifecycle history and an Admin activity event.

The runtime registers verified active plugin routes on the next request. Deactivation transitions the registry to `inactive`; plugin-active middleware blocks its routes on subsequent requests. Installed files and data are retained.

Dependency and conflict declarations are enforced at activation. The current controller preflights these records, but the complete lifecycle still needs stronger concurrent-operation locking and a broader acceptance matrix before production use.

## Health checks, permissions and uninstall

- **Health check:** available for installed, inactive, active and failed packages. It rechecks signature trust, manifest/registry identity, dependencies/conflicts and installed-file integrity. A failed plugin can return to inactive only after a successful health check. A health check does not execute the entrypoint or migrations.
- **Permission grants:** the Admin can grant only permissions declared in the signed manifest. Unchecked permissions are denied immediately. Plugin navigation is filtered by active state, declared portal and granted permission; every route must still enforce server-side authorization.
- **Uninstall:** only installed, inactive or failed plugins can be uninstalled. Active dependants block uninstall. The executable package directory is removed, while LMS data and plugin migration records are retained. Uninstall does not imply destructive data cleanup or migration rollback.

Lifecycle history records plugin key/version, checksum, signing key ID, action, actor and safe details for install, activation, deactivation, health checks and uninstall.

## Not yet delivered

The current Manager is not a public marketplace or remote package catalogue. It supports signed package updates while retaining prior version directories and a manual Admin-triggered package rollback after signature/integrity verification. It also supports a pre-uninstall recovery snapshot and package restore. These actions are not an automatic database/filesystem release rollback: migration `down()` methods may refuse when data exists, and a failed migration requires manual recovery. There is no integrated database backup-before-upgrade orchestration, branch-specific plugin lifecycle control, or dependency-cycle planning UI. Concurrent update/uninstall stress testing and cPanel staging acceptance remain outstanding.

A migration rollback method exists in the migration runner, but it is not a complete Admin-controlled backup/rollback release workflow. Do not use it as a substitute for a verified database/files backup and restore rehearsal.

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

The manifest is declarative and cannot grant itself permissions. The detached signature is generated over the canonical payload from `PluginSignatureVerifier::payloadForSignature($manifest, $sha256)`. See `PLUGIN_SIGNATURE_VERIFICATION.md` for key provisioning and signing rules.

## Portal resource authorization

A plugin route that loads an individual LMS resource must use the resource middleware after declaring its route parameter:

```php
$context->routesForPortal('teacher', 'teacher/plugin-courses', 'teacher.plugin-courses.', function (): void {
    Route::get('/{course:slug}', [CourseController::class, 'show'])
        ->middleware('plugin.resource:course')
        ->name('show');
}, 'course.read');
```

The manifest must declare `course.read`, and a root Admin must grant it. The portal group adds plugin-active, authentication, verification and matching role middleware. The resource middleware adds per-record access checks.

The current resource helper supports User (student records), Course, Batch and Enrollment. Root Admin access is unrestricted for these supported models; Branch Managers are limited to their active branch; Teachers to assigned courses/batches and their enrolled students; Students to their own enrollments; Guardians to students connected through an approved active guardian link. Unknown models and unauthorized records return 404.

For collections, scope the query before pagination, aggregation or export:

```php
$courses = app(PluginResourceAccess::class)
    ->scopeQuery(Course::query(), $request->user())
    ->paginate();
```

Reports, exports and nested relations must scope every underlying query. Never fetch an unrestricted collection and filter it only in the view or after serialization.

## cPanel and release boundary

The package format is designed for shared hosting: runtime installation does not run Composer or shell commands. Dynamic plugin routes are incompatible with Laravel route caching; clear route cache before activation. Exact-head CI and cPanel ZIP packaging do not replace staging installation, browser, provider, backup/restore or real-host acceptance. No production activation or deployment is implied by a successful code merge.
