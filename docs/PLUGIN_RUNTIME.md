# Plugin runtime and route registration

Only registry records in active state are eligible for runtime registration. At
application boot, the runtime verifies:

- the manifest still matches the registry identity;
- the signing key ID remains configured as trusted;
- the signature-verification timestamp and package checksum are present;
- the package path is the canonical plugins/vendor/plugin/version location;
- no package path or entrypoint resolves through a symbolic link;
- the installed file-tree SHA-256 digest matches the digest recorded at install.

The installer records installed_tree_sha256 after safe extraction. Any change to
an installed plugin file causes runtime registration to fail before its entrypoint
is loaded.

## Plugin entrypoint contract

The manifest entrypoint must return an object implementing
App\Contracts\Plugins\PluginInterface. Its register method receives a
PluginContext.

The context can register:
- web routes from a package-relative routes/web.php file;
- API routes from a package-relative routes/api.php file.

Web routes are automatically grouped under plugins/vendor/plugin and require
web, auth, verified and plugin.active middleware. API routes are grouped under
api/plugins/vendor/plugin and require api, api.token and plugin.active middleware.
Route names are namespaced by vendor and plugin key.

The plugin.active middleware checks the live registry state on every request.
This blocks already-registered plugin routes immediately after the plugin is
deactivated, even if the current process still has the route definition loaded.

A runtime registration failure is reported and the plugin is automatically
moved to inactive with failed health status. The application continues booting.

## Trust boundary

A signed plugin is trusted publisher code, not sandboxed code. Once activated,
its PHP entrypoint runs inside the LMS PHP process with the process's privileges.
The route context and file-integrity checks protect against accidental exposure
and post-install file tampering; they do not sandbox malicious PHP. Only configure
release public keys belonging to publishers whose code you trust.

The Admin Plugin Manager does not yet expose activation controls. Permission
review, dependency/conflict preflight, activation/deactivation UX, uninstall,
update and job/schedule/event registration remain separate stages.
