# Theme Manager — Security and Product Contract

**Product:** Alom's LMS  
**Roadmap:** Issue #306, step 1 of 8  
**Status:** Initial runtime implementation is in PR #307. Admin install/preview/activate/reset, detached signature verification, CSS asset checksums, activation history and safe public asset serving are implemented. ZIP installation remains disabled until the institution public key is configured; cPanel staging acceptance is still required.

## Goals

Provide an Admin-only Theme Manager with four explicit actions: install a Theme ZIP, preview an installed theme, activate it, and reset to the built-in safe theme. Theme changes affect presentation only. They must never change LMS permissions, routes, business logic, private storage rules, plugin lifecycle, or student data.

## Existing capability to reuse

The current Website Builder already provides homepage presets, draft preview/publish, revisions, logo selection, and partial public-site typography/color controls. The Theme Manager complements those content/layout tools; it does not replace them or overwrite homepage content.

## Package format (declarative CSS only)

The current package is a ZIP with root-level:
- `theme.json`: product identifier `aloms-lms-theme`, unique slug, semantic version, display name, optional author/description, minimum LMS version, and a map of CSS asset paths to SHA-256 digests.
- `theme.sig`: base64 detached OpenSSL SHA-256 signature over the exact UTF-8 bytes of `theme.json`.
- `assets/*.css`: up to the configured asset count/size limits.
- `preview.png`: optional PNG preview whose digest is declared as `preview_sha256`.

Only CSS assets and the optional PNG preview are supported. PHP, Blade, JavaScript, HTML entrypoints, shell scripts, SQL, fonts, arbitrary images, nested archives, symlinks, duplicate paths, traversal segments, hidden runtime/config files and unexpected files are rejected. Themes are not plugins and may not register routes, listeners, jobs, migrations or executable hooks.

## Trust and ZIP validation

- Accept packages only when a detached signature validates against the configured institution/release public key. The private signing key must remain outside the repository and production host.
- No unsigned, self-signed-by-upload, URL-fetched, or admin-supplied arbitrary package is trusted.
- Verify signature, product identifier, slug/version, minimum LMS compatibility, every CSS asset digest, optional preview MIME/digest, file count, expanded byte limits and total upload size before storing assets.
- The application reads declared entries from the ZIP and writes only validated CSS/PNG bytes to their versioned storage paths; it does not extract arbitrary archive contents.
- If the trusted public key is not provisioned, the UI explains that signed installation is unavailable; verification is never weakened to make the button work.

## Lifecycle

1. Upload ZIP to a private temporary location with strict size/time limits.
2. Inspect and verify the detached signature, manifest, archive entries and every declared asset.
3. Store the original ZIP on the private local disk and validated CSS/optional PNG under a versioned public asset path; record package digest, manifest, installer and timestamp.
4. Preview through an Admin-only route using sample content. Preview does not change activation or public settings; no public preview token is currently used.
5. Activate in a database transaction after checking LMS compatibility, asset presence and SHA-256 digests. A dedicated lifecycle-lock row serializes activation/reset; the previous active theme is appended to activation history.
6. Reset selects the built-in safe theme and records the previous active state. It does not delete installed themes or change homepage content, media, pages, menus, courses or user data.
7. A previous installed theme can be reactivated from its theme card. There is no separate one-click rollback or theme uninstall action in this initial phase; those remain follow-up scope.

## Admin UX

- Admin-only navigation entry: Appearance → Themes.
- Theme cards show name, version, active state and preview thumbnail.
- Actions: Upload/Install ZIP, Preview, Activate, Reset to safe theme.
- Preview is Admin-only and clearly marked as unpublished.
- Activation/reset require explicit confirmation and create an audit/history record.
- Bangla/English labels and validation/error messages; keyboard-accessible forms and responsive layout.
- No third-party CMS/developer branding.

## Current implementation

The initial implementation adds:
- Admin-only Appearance → Theme Manager routes and responsive install/list/preview/activate/reset screens.
- Dedicated `themes`, `theme_activations` and `theme_manager_locks` tables.
- Original ZIP packages retained on the private local disk; validated CSS and optional PNG preview assets stored under the public `themes/` path.
- Public CSS served only for an asset path registered to an installed theme, with a fixed CSS content type, immutable caching and `nosniff`. The public layout loads active theme CSS only for non-admin layouts.
- Theme signature, product/manifest, CSS asset digests, file count, archive path allowlist, optional preview digest/MIME and expanded size validation.
- Activation checks compatibility and re-verifies every stored asset digest before changing the active theme pointer.
- Reset clears the active theme pointer and records the prior state; it does not remove installed themes or alter pages, menus, media, course records or homepage content.

### Theme ZIP layout

```text
theme.json
theme.sig
preview.png              # optional; PNG with preview_sha256 in manifest
assets/
  theme.css
```

Example manifest shape (the `assets` values are SHA-256 hex digests):

```json
{
  "product_id": "aloms-lms-theme",
  "slug": "blue-academy",
  "version": "1.0.0",
  "name": "Blue Academy",
  "author": "Alom's English Center",
  "min_lms_version": "1.0.0",
  "assets": { "assets/theme.css": "<64-character-sha256>" },
  "preview_sha256": "<64-character-sha256-if-preview.png-is-present>"
}
```

Set `THEME_PUBLIC_KEY` in the hosting environment to the PEM public key (escape newlines as `\\n` where the hosting panel requires a single-line value). Keep the private key in a separate offline signing environment. Sign the exact UTF-8 bytes of `theme.json` with SHA-256 and place the base64 detached signature in `theme.sig`.

## Data model and safety

- Theme metadata and paths are stored in dedicated tables; package files are never executed.
- A dedicated lock row is held with `SELECT ... FOR UPDATE` while activating/resetting so concurrent changes cannot overwrite each other's previous-state history.
- Every activation re-checks compatibility and the SHA-256 digest of each stored CSS asset.
- All routes use Admin authorization and CSRF. Theme slugs and asset names are strictly allowlisted; public assets resolve only through the installed-theme registry.

## Required tests

- Admin can view/manage themes; student and other non-admin roles are denied.
- Reject unsigned/tampered packages, wrong product ID, incompatible versions, invalid digests, path traversal, symlinks, executable files, duplicate paths, oversized archives and ZIP bombs.
- Valid signed package installs without changing active theme.
- Preview is Admin-only and does not activate or mutate content.
- Activation persists one active theme, writes history and refuses tampered/missing assets.
- Concurrent activation/reset is serialized by the lifecycle lock.
- Reset returns to safe theme and preserves pages, homepage content, media, menus and installed package records.
- Fresh install/upgrade migrations are safe on supported MySQL/MariaDB; cPanel staging validates filesystem permissions and backup/restore.

## Release gates

Runtime signed ZIP installation remains disabled until the institution's trusted public key is provisioned and a matching signed test package is available. Before production use, test on a separate cPanel staging site with backup/restore and rollback. No production theme activation is authorized by this specification.
