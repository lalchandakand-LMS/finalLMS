# Universal Plugin Platform — Architecture and Delivery Contract

Status: approved architecture; implementation is tracked in Issue #241.
Target: Laravel 12, PHP 8.3+, MySQL/MariaDB, shared cPanel hosting.

## 1. Product decision

Alom's LMS will use one universal plugin platform across all five operational panels: unified Admin, Branch Manager/Branch Panel, Teacher, Student and Guardian. The current Add-on Registry is only a fixed allow-list switch; it is not the requested installable plugin system.

All first-party and approved third-party plugin packages live under one application-root `plugins/` directory. This directory must be outside the public document root, denied by web-server rules as defense in depth, and excluded from public media/static serving. Plugin packages are never uploaded into `public/`.

## 2. Admin lifecycle

Admin → Plugin Manager will provide:
- Discover and view installed/available packages.
- Upload/install trusted package, inspect manifest and preflight report.
- Configure settings and credentials without displaying stored secrets.
- Activate, deactivate, update and uninstall.
- View version, author, compatibility, dependencies, conflicts, supported portals, requested permissions, health state, install/update history and audit events.
- Restore/disable a failed plugin using a safe staged lifecycle.

A package lifecycle is:
`discovered → validated → staged → installed → active`
with safe transitions to `inactive`, `update-staged`, `failed`, and `uninstalled`.
The registry state is authoritative; directory presence alone never enables code.

## 3. Package contract

Each plugin has a unique immutable key and a versioned JSON manifest, for example:

```json
{
  "schema_version": 1,
  "key": "aec.analytics.meta-pixel",
  "name": "Meta Pixel",
  "version": "1.0.0",
  "type": "integration",
  "entrypoint": "Plugin.php",
  "requires": {
    "php": "^8.3",
    "laravel": "^12.0",
    "lms": ">=1.0.0"
  },
  "dependencies": [],
  "conflicts": [],
  "portals": ["admin", "public"],
  "permissions": ["analytics.configure", "analytics.emit"],
  "capabilities": ["settings", "consent-aware-tracking"],
  "migrations": "database/migrations"
}
```

The manifest is declarative. It cannot grant itself permissions or bypass platform policy. The Admin must explicitly approve requested capabilities. Unknown manifest fields, unsupported schema versions, duplicate keys, invalid paths, dependency cycles and incompatible requirements fail closed.

## 4. Security and trust model

- Accept only packages signed by a provisioned trusted release key. Verify signature and SHA-256 before extraction; key provisioning and rotation are separate audited operations.
- Upload to a private staging directory, validate archive entry count/expanded size, reject traversal, absolute paths, symlinks, hard links, special files, duplicate paths and executable files in public locations.
- Verify package identity, manifest schema, compatibility, dependencies, conflicts and required permissions before activation.
- Extract atomically into `plugins/<vendor>/<plugin-key>/<version>`; retain the previous known-good version for rollback.
- Never execute an untrusted package during validation. Plugin PHP is loaded only after signature, manifest, compatibility and Admin activation checks pass.
- No plugin may modify protected core files, .env, Composer dependencies, deployment scripts, or another plugin's directory.
- Secrets are encrypted at rest where stored in the database, redacted from HTML/logs/exports and never committed to Git. Prefer server environment variables for high-value credentials.
- Every lifecycle/configuration action is permission-checked, CSRF-protected, rate-limited where relevant and audit logged.
- Deactivation must disable routes, API endpoints, jobs, scheduled tasks, event listeners, hooks and portal navigation—not merely hide the menu.
- No shell/Composer execution at runtime; package must be compatible with cPanel shared hosting and prebuilt release artifacts.

## 5. Runtime extension contract

Plugins integrate through explicit platform interfaces, not arbitrary file inclusion:
- Service provider / boot contract registered only for active, verified packages.
- Typed event and action hooks with documented payloads and bounded execution.
- Namespaced, collision-checked route registration with middleware and permission declarations.
- Admin settings schema with validation, secret fields, defaults and health checks.
- Portal navigation/widgets declared in manifest and filtered by portal + server-side permission.
- Queue jobs and scheduled work namespaced, rate-limited, retry-safe and disabled when plugin inactive.
- Migrations are versioned and tracked per plugin; destructive migrations require backup/preflight and explicit data-retention policy.

## 6. Five-panel support

Each plugin declares supported portals: `admin`, `branch`, `teacher`, `student`, `guardian`, and/or `public`.
- Admin controls installation, permissions and configuration centrally.
- Branch modules always enforce assigned-branch scope server-side.
- Teacher modules enforce assigned courses/batches and ownership.
- Student modules enforce enrollment and personal-record access.
- Guardian modules require a verified guardian-child relationship for every read, write, export, download and notification.
A plugin cannot widen a user's role or bypass existing policies. Disabled plugins return unavailable/404 for all endpoints and reject queued work.

## 7. First-party plugin families

### Integrations
- Google Analytics 4, Google Tag Manager, Meta Pixel and Google Search Console site verification/API where supported.
- Google Drive and S3-compatible storage.
- SMS/email providers.
- Payment gateways (bKash, Nagad, SSLCommerz and other supported providers).
Provider configuration is not proof of live integration. Each provider requires official docs, merchant approval, secret setup, signed callback verification, idempotency, sandbox tests and reconciliation before activation.

Analytics and marketing tags must respect consent choices, regional privacy requirements, and public/student portal data minimization. Never send student identity, grades, phone, payment or guardian data to advertising pixels.

### Builders
- Slider / hero carousel builder.
- Post / blog / news builder.
- Course / curriculum / lesson builder.
- Page / landing-page builder.
- Menu, header/footer, popup, banner and form builders.
Existing Website Builder, Course Builder, Course Content Studio and Blog/News workflows must be integrated or wrapped by plugins without duplicating tables or breaking current content. Existing builder data remains core-owned until a reviewed migration proves otherwise.

### Portal modules
- Branch management extensions, teacher tools, student learning widgets and guardian family views.
- Each module must declare routes, navigation, permissions, supported portals and data ownership.

### AI Builder
The existing AI Developer and AI-assisted builder functionality become first-party AI plugins using the same lifecycle and permissions. AI may generate draft page sections, sliders, posts, course outlines, lesson drafts and translations. Generated output is preview-only until an authorized human reviews and publishes it. Provider API keys stay server-side; apply role-based access, prompt/output audit, rate and spend limits, sensitive-data minimization, output validation and explicit human approval. AI must not install plugins, change permissions, delete data, publish content or deploy code autonomously.

## 8. Install, update and uninstall safety

Install/update:
1. Admin uploads a signed package.
2. Verify signature/checksum and package limits before extraction.
3. Validate manifest, compatibility, dependencies, conflicts and permission requests.
4. Stage privately; run read-only preflight and migration compatibility checks.
5. Create and verify backup before data-changing migration.
6. Admin confirms activation; register plugin atomically and run approved migrations.
7. Run health checks; on failure disable plugin and preserve prior version for recovery.

Uninstall:
1. Deactivate first and verify all entry points are disabled.
2. Check dependent plugins and data references.
3. Create verified backup and show the Admin a data-retention report.
4. Default to retaining plugin-owned records/files in a private archive; destructive purge is a separate explicit action with a retention window.
5. Run only reviewed reversible cleanup migrations; never silently delete courses, posts, student records, payments, guardian links or uploaded media.

## 9. Compatibility with current code

Before runtime implementation, inventory:
- `AddonRegistryController` and `resources/views/admin/addons` (current allow-list).
- GA4/GTM/Meta Pixel consent handling and service configuration.
- Existing Google Drive/S3 storage connections.
- Website Builder, homepage presets, slider, Website Pages and Blog/News.
- Admin Course Builder and Course Content Studio.
- AI Developer controller, models, UI and any provider abstraction.
- All five panel routes, middleware, policies, navigation and API routes.

Migrate one module at a time. Preserve existing route names, database records, settings and URLs. Keep a compatibility bridge until each module is proven under the plugin runtime.

## 10. Delivery phases and acceptance

Tracked by Issue #241:
1. Source inventory and contract tests.
2. Plugin manifest/catalogue, registry schema, discovery, lifecycle state machine and Admin UI.
3. Signed package staging/verification/install/update/deactivate/uninstall.
4. Portal extension APIs and first-party builder plugins.
5. Integration plugins and consent/secret controls.
6. AI Builder/AI Developer integration.
7. Full tests, cPanel packaging, docs and staging acceptance.

Required tests include lifecycle transitions, tampered/unsigned package, traversal/zip-bomb/symlink, incompatible version, missing dependency/cycle/conflict, secret redaction, unauthorized admin action, disabled plugin routes/jobs, branch isolation, guardian-child isolation, migration rollback, retained data, bilingual UI and cPanel package build.

No production plugin installation or activation until exact-head CI/CodeQL pass and isolated cPanel staging install/upgrade/backup/restore/browser acceptance is complete.
