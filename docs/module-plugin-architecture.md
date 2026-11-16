# LMS-wide Module and Plugin Architecture

## Goal

Keep the Core Platform stable and move business capabilities behind independently
managed first-party modules. Every module has a stable key, dependencies, route
ownership, permissions and a migration plan. The signed Plugin Manager is a
separate package lifecycle for reviewed, signed plugin archives.

## Current architecture

- `config/modules.php` is the allow-listed first-party module catalogue.
- `ModuleRegistry` persists Admin enable/disable choices and enforces dependency
  rules. Protected foundations cannot be disabled while enabled modules depend
  on them.
- `config/module_routes.php` maps named web routes to every owning module.
- `config/module_route_uris.php` maps URI-based API routes to owning modules.
- `EnsureRouteModuleEnabled` is appended to the web and API middleware groups.
  If any mapped owner is disabled, the route returns 404. Authentication,
  role/permission checks and resource scoping still run independently.
- `plugins/<module>/plugin.json` contains declarative metadata for the
  first-party migration tranche. These manifests are currently
  `metadata_only`; they do not load PHP code or make the implementation
  independently installable.
- `plugins/aec/*` contains versioned, signed-package source for actual plugin
  packages such as Universal AI Assistant. Signed third-party package install
  and integrity checks are governed by the Plugin Manager, not by the
  first-party Module Registry.

## Route ownership boundary

Route ownership is deliberately fail-closed for mapped routes: if a route has
multiple owners, every owner must be enabled. This supports shared endpoints
such as batch assignment and exam workflows. Routes not yet represented in
either ownership map remain available; they must be audited and mapped before
the corresponding module can be described as fully isolated.

The maps are not authorization. A route still requires the appropriate
authentication, role, permission and resource-scope middleware.

The current module-catalog audit maps every HTTP-facing module to web and/or API
route ownership. The only intentionally unmapped keys are service/foundation
modules with no direct HTTP endpoints: core, communications, ai_builder,
otp_verification, sms_otp, email_otp and gmail. The route-ownership feature test
fails if another module is introduced without an explicit route-ownership
decision.

## Academic Level relationships

Academic Levels are an independently switchable foundation module. Courses and
website pages may be assigned to a level; batches inherit their level through
their course, and exams inherit it through their batch. The Admin can create,
edit, deactivate and filter levels, and navigate from a level to its courses,
pages, batches and exams. Existing course/page assignments remain editable if a
level is deactivated; new assignments are restricted to active levels.

## First-party manifest boundary

The first-party manifests added in this tranche are metadata only. They declare
module identity, dependencies, capabilities, routes and safety expectations.
The business code remains in the existing Laravel application and is gated by
the Module Registry. Do not claim a module is independently installable,
uninstallable or physically extracted until its services, controllers, views,
migrations, jobs, events, policies and data ownership have been migrated behind
a tested module contract.

## Plugin settings and secrets

Plugin-specific settings are persisted in `plugin_settings` and encrypted using
Laravel's application encryption key. The Admin Manager displays setting keys
and timestamps only. It does not render or audit decrypted values. Backups must
protect `APP_KEY`; changing it without re-encrypting stored settings makes those
values unreadable.

## Migration roadmap

1. Complete the route ownership inventory and add regression tests for every
   mapped web/API route family.
2. Extract one business capability at a time into its own first-party plugin
   package under `plugins/`, beginning with low-coupling modules.
3. Define stable service contracts and permission/resource scopes before
   moving controllers, jobs, events or data writes.
4. Move module-owned settings, jobs, notifications, provider adapters and
   migrations into the module package with compatibility and rollback tests.
5. Keep Core limited to identity, authorization, module/plugin registry,
   shared contracts, audit, storage abstractions and platform services.
6. Require signed releases, compatibility checks, integrity verification,
   health checks and a disposable staging rehearsal before enabling packages.

## Safety principles

- Core Platform and Identity & Access are protected foundations.
- Dependencies must be enabled before dependent modules can be enabled.
- Disabling a module blocks mapped routes but never uninstalls code or deletes
  customer data.
- Uploaded PHP is never executed merely because a file or manifest exists.
- Third-party packages must be allow-listed, signed, integrity-verified and
  explicitly activated.
- External provider features are not live until credentials and provider-side
  acceptance tests are configured.
