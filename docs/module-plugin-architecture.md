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
The first-stage registry now includes a route-ownership map and a global route guard for mapped web and API route names. This allows a disabled module's mapped routes to return 404 while preserving the Core Module Manager. The map is an incremental migration artifact: routes not yet assigned in config/module_routes.php remain unaffected and must be mapped before a module can be considered fully isolated.

A module shown as enabled is not proof that an external provider is configured or live.

## Migration sequence
1. Registry, manifests, dependency validation, admin lifecycle UI and runtime middleware.
2. Map existing routes and navigation to module ownership; fail closed when a module is disabled without bypassing authorization.
3. Extract module-owned services, events, policies and settings behind contracts.
4. Payment provider adapters and payment orchestration.
5. Communications, Storage, Marketing/SEO, Notice Board, Slider and Website modules.
6. Academic, Finance, Student/Guardian, Teacher, Branch and Mobile modules.
7. Signed package metadata, compatibility checks, permission scopes, install/upgrade/rollback and automated contract tests.

## Security boundary
The registry accepts only known first-party keys. It does not accept package paths, class names, arbitrary service providers, shell commands or executable uploads. An enabled catalog entry is not proof of external provider activation. Route gating supplements, and never replaces, authentication, role authorization or permission checks.
