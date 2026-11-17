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

PluginContext exposes explicit container bindings and routesForPortal().
Web route registration is permitted only for portals declared in the signed
manifest. Authenticated web groups receive web, plugin-active, auth, verified,
role and declared/granted permission middleware. Public web routes still receive
plugin-active middleware.

Mobile API routes use apiRoutesForPortal() and are currently limited to the
student and teacher portals because the LMS mobile-token authenticator accepts
only those roles and requires an approved device. These groups receive api,
plugin-active, api.token, role, declared/granted permission and a default
60-requests-per-minute throttle. Guardian, branch and admin API routes must not
use this mobile-token helper until their own supported API authentication
contract is implemented. The plugin-active middleware rechecks registry state
on each request, so a plugin that is deactivated or marked failed stops serving
its routes immediately.

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
