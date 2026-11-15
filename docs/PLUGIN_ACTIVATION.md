# Plugin activation and deactivation

Activation is a separate, root-admin-only action after installation. Before changing
the registry state to active, PluginActivationService verifies:

- the signed manifest schema and immutable registry identity;
- the recorded signature verification and currently trusted release key;
- the complete installed-file integrity manifest;
- PHP, Laravel and LMS version constraints;
- that every declared dependency is already active;
- that no declared conflicting plugin is active.

The lifecycle change is row-locked and transactional. Runtime entrypoint loading
occurs on the next web request and repeats signature trust, manifest and file
integrity checks before requiring the entrypoint. The entrypoint must return a
PluginContract implementation. A runtime failure marks the plugin failed and
blocks all plugin routes.

Deactivation is also row-locked. It is rejected while another active plugin
declares the target plugin as a dependency. Once deactivated, the plugin-active
route middleware returns 404 for all of its routes on subsequent requests.

This phase intentionally does not offer updates or uninstall. An update must
preserve the currently active version until the new signed package passes
verification and health checks. Uninstall must preserve plugin data by default
and requires a reviewed backup/retention workflow.
