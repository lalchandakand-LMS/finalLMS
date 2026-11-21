# Verified plugin runtime contract

## Trust boundary

A signed plugin package is trusted application code, not a PHP sandbox. Its PHP runs with the LMS application's process privileges. Only root-admin-approved registry records with active status may be loaded. Never install untrusted vendor packages.

The runtime resolves packages only from the canonical private application-root `plugins/<vendor>/<plugin>/<version>` directory. It does not load arbitrary public uploads or paths.

Before requiring an entrypoint, the runtime checks:

- verified release metadata and the currently trusted signing key;
- detached signature against the installed manifest and original package checksum;
- manifest schema and immutable registry identity;
- complete installed-file SHA-256 integrity manifest;
- canonical private package directory and safe entrypoint path;
- that the entrypoint returns an `App\Contracts\Plugins\PluginContract` implementation.

The contract exposes `register(PluginContext $context)` and `boot(PluginContext $context)`.

## Route and portal registration

`PluginContext::routesForPortal()` registers routes only for portals declared in the signed manifest. Route names and prefixes are namespaced; portal routes receive web and plugin-active middleware, and non-public portals also receive authentication, verification and matching role middleware.

The plugin-active middleware checks registry state on requests and re-verifies the detached release signature against the current trusted key plus every installed-file hash before allowing the request to reach plugin controllers. This is important because controller classes may be autoloaded after application boot. Confirmed trust/integrity failures return 404; transient infrastructure failures surface rather than silently authorizing execution. Deactivation or a failed runtime health state therefore blocks plugin routes rather than merely hiding navigation.

The hook registry supports namespaced action hooks, declared capabilities and portal navigation. Each action hook must name a permission declared in the signed manifest, and dispatch rechecks that the Admin has currently granted that permission. Revoked grants suppress the callback. Dispatch and navigation are restricted to currently active, signature-verified and file-integrity-verified plugins. Non-public navigation also requires the corresponding permission to have been granted by the Admin. Navigation visibility is not authorization: each controller, API action, resource query, export and download must independently enforce permissions and data scope.

## Resource access

Use `plugin.resource:<model>` for individual route-bound resources and `PluginResourceAccess::scopeQuery()` for collections. The currently supported resource types are User (student records), Course, Batch and Enrollment. Enforce branch ownership, teacher course/batch assignment, student enrollment and verified guardian-child relationships on the server for every request.

## Lifecycle and failures

The Admin activation path checks signature, manifest, compatibility, dependencies/conflicts and installed-file integrity before running tracked plugin migrations and transitioning the package to active. Runtime registration occurs on the next request. If signature, integrity, manifest identity, entrypoint contract or plugin boot fails, the plugin is marked failed and its routes are blocked; other plugins and the LMS core continue loading.

A plugin migration failure leaves the package inactive and records a safe failure status; inspect protected server logs for diagnostics. Health checks do not execute plugin code or migrations. Uninstall removes the executable package but preserves LMS data and migration history by default.

Signed package updates are supported for installed/inactive plugins. Updates require the same plugin identity and type, a strictly newer semantic version, valid installed signature/integrity, and active dependencies with no conflicts. The current package/registry snapshot and files are retained before the new version is installed to its immutable version path. Updated packages remain inactive and their migrations are deferred until explicit activation. Package rollback restores a verified prior package snapshot and leaves the plugin inactive; migration rollback is a separate explicit action. These controls do not replace full database backups or a rehearsed cPanel restore procedure.

## Manifest-defined settings

A signed manifest may declare a `settings_schema` object keyed by stable setting identifiers. Each definition supports a label, type (`text`, `url`, `email`, `integer`, `boolean`, `select`, or `secret`), optional `required`, and optional scalar `default`. Select settings must declare a bounded, unique list of options. Defaults are validated against their declared type, and secret defaults are prohibited so credentials cannot be embedded in a signed manifest. Unknown schema fields and invalid definitions are rejected during manifest validation.

The Admin Plugin Manager renders the corresponding typed control. Server-side validation is authoritative: URLs must be HTTPS, email addresses must validate, integers must be whole numbers, booleans are stored as `0`/`1`, and select values must match the signed option list. Secrets are encrypted at rest and are never re-rendered; the UI shows only whether a value is configured. Optional blank values clear the setting. Legacy manifests without a settings schema retain the generic encrypted key/value form for backward compatibility.

## Queue jobs, route cache and background work

Plugin-owned queued work must extend `App\Jobs\Plugins\PluginJob` and pass its immutable plugin key plus a manifest-declared permission that an Admin has granted. The base job attaches `EnsurePluginJobActive` middleware. At execution time it rechecks that the registry exists, the plugin remains active, the required permission remains both declared and Admin-granted, the persisted Ed25519 release signature verifies against the currently trusted public key, and every installed package file matches its integrity manifest. If a confirmed trust/integrity check fails, the job is acknowledged without running its handler; stale work is not retried. A dedicated `PluginTrustFailure` distinguishes permanent signature/manifest/file-tampering failures from transient filesystem, sodium-extension, database or configuration failures; transient failures bubble to the queue worker for retry. If the registry/database infrastructure is unavailable, the middleware throws so the queue worker can retry instead of silently losing work. Legacy records without a persisted release signature, revoked/replaced keys and tampered/missing/extra package files are rejected before the handler runs. Registry/database outages throw for queue retry; confirmed authorization/integrity failures drop stale work. Add regression tests for these cases for every package that adopts this contract.

This is an opt-in queue contract, not a general sandbox. Existing plugin jobs have not all been migrated, and the framework does not yet register or wrap arbitrary scheduler callbacks, external provider webhooks or already-dispatched work. Scheduled tasks must dispatch a guarded `PluginJob`; do not execute plugin callbacks directly from the scheduler. Event listeners/webhooks still need explicit active-state and permission checks plus lifecycle tests. Dynamically registered plugin routes are not compatible with Laravel route caching in this phase. Activation is blocked while routes are cached; clear route cache before activating plugins.

## Remaining release gates

Signed package update and multi-version package rollback foundations exist, but backup/restore orchestration and cPanel staging rehearsal remain unverified. Remote marketplace discovery, complete background-job isolation and full five-portal browser acceptance are not yet a finished end-to-end release workflow. A successful CI run or package ZIP is not proof of cPanel staging acceptance. No production deployment is authorized by this document.
