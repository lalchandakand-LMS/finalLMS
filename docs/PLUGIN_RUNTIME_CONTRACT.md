# Verified plugin runtime contract

A signed plugin package is trusted application code, not sandboxed code. Only
root-admin-approved, active registry records may be loaded. Before the entrypoint
is required, the runtime checks the registry identity, signed manifest schema,
signature verification record, currently trusted release key and the complete
installed-file SHA-256 manifest.

The package entrypoint must return an object implementing
App\Contracts\Plugins\PluginContract. The contract has register() and boot()
methods. The runtime never loads a package from a public upload or arbitrary
filesystem path; it resolves only the canonical private
plugins/vendor/plugin/version directory.

PluginContext exposes explicit container bindings and routesForPortal(). Route
registration is permitted only for portals declared in the signed manifest.
The route group automatically receives web and plugin-active middleware, plus
auth, verified and the matching role middleware for non-public portals. The
plugin-active middleware rechecks registry state on each request, so a plugin
that is deactivated or marked failed stops serving its routes immediately.

If integrity, signature trust, manifest identity, entrypoint contract or plugin
boot fails, the plugin is marked failed and its routes are blocked. The LMS core
continues loading other plugins; exceptions are reported to server logs.

Signed plugin PHP is not a PHP sandbox. It runs with the application's PHP
process privileges and must be reviewed and signed by a trusted release
authority. Do not install untrusted vendor packages.

The first runtime phase intentionally does not add activation buttons or
marketplace features. Activation, dependency preflight, portal-specific
permission grants, health dashboards, migrations and rollback will be exposed
only after their separate safeguards and tests are complete.

Route caching is not compatible with dynamically registered plugin routes in
this phase. Do not run route:cache while active plugins exist. The provider
fails closed if it detects cached routes with active plugins; use route:clear
before enabling plugin runtime.
