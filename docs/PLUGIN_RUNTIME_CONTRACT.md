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

The plugin-active middleware checks registry state on requests. Deactivation or a failed runtime health state therefore blocks plugin routes rather than merely hiding navigation.

The hook registry supports namespaced action hooks, declared capabilities and portal navigation. Dispatch and navigation are restricted to currently active, verified plugins. Non-public navigation also requires the corresponding permission to have been granted by the Admin. Navigation visibility is not authorization: each controller, API action, resource query, export and download must independently enforce permissions and data scope.

## Resource access

Use `plugin.resource:<model>` for individual route-bound resources and `PluginResourceAccess::scopeQuery()` for collections. The currently supported resource types are User (student records), Course, Batch and Enrollment. Enforce branch ownership, teacher course/batch assignment, student enrollment and verified guardian-child relationships on the server for every request.

## Lifecycle and failures

The Admin activation path checks signature, manifest, compatibility, dependencies/conflicts and installed-file integrity before running tracked plugin migrations and transitioning the package to active. Runtime registration occurs on the next request. If signature, integrity, manifest identity, entrypoint contract or plugin boot fails, the plugin is marked failed and its routes are blocked; other plugins and the LMS core continue loading.

A plugin migration failure leaves the package inactive and records a safe failure status; inspect protected server logs for diagnostics. Health checks do not execute plugin code or migrations. Uninstall removes the executable package but preserves LMS data and migration history by default.

## Route cache and background work

Dynamically registered plugin routes are not compatible with Laravel route caching in this phase. Activation is blocked while routes are cached; clear route cache before activating plugins.

The current hook/route runtime is not a general sandbox for arbitrary background workers. Do not assume that disabling a plugin automatically cancels arbitrary external workers, provider webhooks or jobs that were created outside the registered runtime. Any queue, scheduled task, event listener or webhook extension must use explicit plugin-active and permission checks and be covered by dedicated lifecycle tests before being enabled.

## Remaining release gates

Signed package updates, multi-version rollback, backup/restore orchestration, remote marketplace discovery, complete background-job isolation and full five-portal browser acceptance are not yet a finished end-to-end release workflow. A successful CI run or package ZIP is not proof of cPanel staging acceptance. No production deployment is authorized by this document.
