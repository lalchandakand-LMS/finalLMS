# LMS-wide Module Plugin Architecture

## Goal
Move every LMS capability behind independently managed first-party modules with stable identifiers, dependencies, permissions, routes, navigation, settings, service contracts and extension points. The Core Platform is the protected foundation, not a monolithic home for every business feature.

## Platform principles
- Core Platform and Identity & Access are protected foundations.
- Each business capability is represented by a separate module manifest.
- Modules may be enabled only when all declared dependencies are enabled.
- A module cannot be disabled while an enabled module depends on it.
- Disabling never uninstalls code, deletes customer data or runs destructive migrations.
- Third-party extensions must be explicitly allow-listed, versioned, signed and permission-scoped. Never execute uploaded PHP packages.
- Secrets remain in server-side environment/configuration or an encrypted secret store.
- Existing modules remain enabled by default during migration to avoid breaking current production workflows.

## First-party module catalog
The manifest separates organization and people (branches, students, teachers, guardians, staff), academic delivery (courses, lessons, batches, attendance, assignments, exams, live classes, certificates, student and guardian portals), finance (fees, expenses, refunds, payments, coupons and digital store), communications (SMS, email, push, notice board and mobile verification), website and growth (CMS, slider, media, storage, SEO, analytics, marketing, Meta Pixel, Meta Ads and support), reporting, mobile APIs/apps and external integrations.

## Lifecycle foundation
- config/modules.php contains the allow-listed first-party manifests.
- ModuleRegistry persists administrator lifecycle choices in the existing settings store.
- module.enabled:<module-key> middleware is registered for route-by-route runtime gating.
- The admin Module Manager exposes lifecycle state and records changes in the activity log.

## Important implementation boundary
The registry includes route-ownership maps for named web routes (config/module_routes.php) and URI-based API routes (config/module_route_uris.php). A shared web/API middleware guard blocks mapped routes with 404 when any owning module is disabled, while preserving the Core Module Manager. The maps are an incremental migration artifact: routes not yet assigned remain unaffected and must be mapped before a module can be considered fully isolated.

A module shown as enabled is not proof that an external provider is configured or live.

## Migration sequence
1. Registry, manifests, dependency validation, admin lifecycle UI and runtime middleware.
2. Map existing routes and navigation to module ownership; fail closed when a module is disabled without bypassing authorization.
3. Extract module-owned services, events, policies and settings behind contracts.
4. Payment provider adapters and payment orchestration.
5. Communications, Storage, Marketing/SEO, Notice Board, Slider and Website modules.
6. Academic, Finance, Student/Guardian, Teacher, Branch and Mobile modules.
7. Signed package metadata, compatibility checks, permission scopes, install/upgrade/rollback and automated contract tests.

## Signed package lifecycle boundary

The separate package registry stores signed third-party package metadata in `plugin_registry`. Uploads are staged under the private `plugins/.staging` directory, checked against trusted Ed25519 public keys and SHA-256 checksums, then extracted into a canonical private path. Archive inspection rejects traversal, symlink entries, excessive entry counts, oversized files and compression bombs. Installed files receive an integrity manifest; the integrity verifier rejects changed, missing, extra or linked files.

Before staging, the compatibility checker validates the declared PHP, Laravel and LMS version ranges and confirms that required plugins are installed and declared conflicts are absent. The supported constraint syntax is intentionally limited to exact versions, comparison operators, caret, tilde and `*`; unsupported expressions fail closed.

The Admin Plugin Manager lists the first-party module catalogue and signed third-party registry. It supports signed installation, encrypted server-side setting storage, package health/integrity checks, audited deactivation and a confirmed uninstall that first verifies installed-file integrity, checks dependent plugins and moves package files to a private backup. Setting values are encrypted at rest, omitted from HTML and audit logs, and excluded from flashed validation input. The registry row, settings and customer data are retained. A plugin must be inactive before uninstall. A package update flow, backup restore UI and third-party activation/runtime are not enabled yet.

**Important:** uploaded PHP entrypoints are never included, autoloaded or executed. Activation remains unavailable until a reviewed, allow-listed runtime can guarantee route/API/job/event/UI gating and permission enforcement. This is an intentional security gate, not a claim that third-party plugin execution is complete.

## Security boundary
The registry accepts only known first-party keys. It does not accept package paths, class names, arbitrary service providers, shell commands or executable uploads. An enabled catalog entry is not proof of external provider activation. Route gating supplements, and never replaces, authentication, role authorization or permission checks.
