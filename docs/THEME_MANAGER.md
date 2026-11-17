# Theme Manager — Security and Product Contract

**Product:** Alom's LMS  
**Roadmap:** Issue #306, step 1 of 8  
**Status:** Initial runtime implementation is in PR #307. Admin install/preview/activate/reset, signed ZIP verification, declarative CSS assets, activation history and feature tests are implemented. ZIP installation remains disabled until the institution public key is configured; cPanel staging acceptance is still required.

## Goals

Provide an Admin-only Theme Manager with four explicit actions: install a Theme ZIP, preview an installed theme, activate it, and reset to the built-in safe theme. Theme changes affect presentation only. They must never change LMS permissions, routes, business logic, private storage rules, plugin lifecycle, or student data.

## Existing capability to reuse

The current Website Builder already provides 15 homepage presets, draft preview/publish, revisions, logo selection, and partial public-site typography/color controls. The Theme Manager must complement those content/layout tools, not replace them or silently overwrite homepage content. Existing settings remain the source of truth until a theme is explicitly activated.

## Package format (declarative only)

A Theme ZIP must contain exactly one top-level theme directory with:
- `theme.json`: product identifier `aloms-lms-theme`, unique slug, semantic version, display name, author label, description, minimum/maximum supported LMS versions, supported locales, asset entrypoints, and SHA-256 digest for every packaged asset.
- `preview.png`: optional preview image, MIME-verified and size-limited.
- `assets/`: CSS, fonts and image assets only.

Reject PHP, Blade, JavaScript, HTML entrypoints, shell scripts, SQL, symlinks, hard links, nested archives, duplicate normalized paths, absolute paths, traversal segments, hidden runtime/config files, and files outside the allowlist. Themes are not plugins and may not register routes, listeners, jobs, migrations, or executable hooks.

## Trust and ZIP validation

- Accept packages only when a detached signature validates against a configured institution/release public key. The private signing key must remain outside the repository and production host.
- No unsigned, self-signed-by-upload, URL-fetched, or admin-supplied arbitrary package is trusted.
- Verify signature, product identifier, slug/version, compatibility, every asset digest, MIME, file count, expanded size, compression ratio, and total upload size before extraction.
- Extract to a private non-public staging directory using normalized paths; never extract directly into the active theme directory or public web root.
- Reject archive entries whose resolved destination escapes the staging root. Do not follow links.
- If the trusted public key is not provisioned, the UI must explain that signed installation is unavailable; do not weaken verification to make the button work.

## Lifecycle

1. Upload ZIP to a private temporary location with strict size/time limits.
2. Inspect and verify without changing the active theme.
3. Extract into an immutable versioned theme directory and record package digest, manifest, install actor and timestamp.
4. Preview through a short-lived, signed, Admin-only preview token. Preview must not persist activation or mutate global settings.
5. Activate in a database transaction after compatibility and asset checks; preserve the prior active theme and an immutable settings snapshot for rollback.
6. Verify public rendering and required assets. If activation fails, restore the prior active theme automatically.
7. Reset selects the built-in safe theme and restores its baseline design tokens; it does not delete installed themes, homepage content, media, pages, menus, or user data.
8. Uninstalling a theme is a separate action and is blocked while it is active or referenced by a retained rollback snapshot.

## Admin UX

- Admin-only navigation entry: Appearance → Themes.
- Theme cards show name, version, compatibility, install state, active state and preview thumbnail.
- Actions: Upload/Install ZIP, Preview, Activate, Reset to safe theme, View details.
- Preview is isolated from public visitors and clearly marked as unpublished.
- Activation/reset require explicit confirmation and create an audit record.
- Show progress, validation errors, empty state, unavailable signing-key state and recovery status.
- Bangla/English, keyboard operation, mobile-friendly layout, and no third-party CMS/developer branding.

## Current implementation

The initial implementation adds:
- Admin-only Appearance → Theme Manager routes and responsive install/list/preview/activate/reset screens.
- Dedicated `themes` and `theme_activations` tables; installed package ZIPs are retained on the private local disk, while validated CSS and optional PNG preview assets are stored under the public `themes/` path.
- Public CSS is served only for an asset path registered to an installed theme, with a fixed CSS content type, immutable caching and `nosniff`. The public layout loads active theme CSS only for non-admin layouts.
- The ZIP manifest and detached signature are `theme.json` and base64 `theme.sig`; `THEME_PUBLIC_KEY` must contain the PEM public key. The exact manifest bytes are verified with OpenSSL SHA-256. Every CSS asset is SHA-256 checked. Optional `preview.png` must declare `preview_sha256`.
- The package currently supports a deliberately narrow CSS-only asset allowlist. External CSS URLs, imports, JavaScript, templates, PHP, symlinks, duplicate paths, unexpected files and oversized archives are rejected.
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

## Data model and implementation guidance

Prefer dedicated `themes`, `theme_activations` (or immutable activation history), and `theme_previews` records over overloading generic website settings. Store package metadata and file paths, never executable source. Store theme assets on a non-executable public asset disk only after validation; keep the original ZIP and temporary extraction private. Use database transactions and an application lock to serialize activation/reset.

All routes must be protected by the unified Admin authorization middleware and CSRF. Do not trust a hidden form field or UI visibility for authorization. Validate every theme slug against a strict allowlist pattern and resolve assets only through the recorded installed-theme registry.

## Required tests

- Admin can view the manager; branch manager, teacher, student and guardian receive 403.
- Reject unsigned/tampered packages, wrong product ID, incompatible versions, invalid digests, path traversal, symlinks, executable files, duplicate paths, oversized archives and ZIP bombs.
- Valid signed package installs without changing active theme.
- Preview is admin-only, expires, and does not activate or mutate content.
- Activation persists one active theme and writes an audit/history snapshot.
- Failed activation restores previous active theme.
- Reset returns to safe theme and preserves pages, homepage content, media, menus and installed package records.
- Active or rollback-referenced theme cannot be removed.
- Fresh install and upgrade migrations are safe on supported MySQL/MariaDB; cPanel staging validates filesystem permissions and atomicity.

## Release gates

Runtime signed ZIP installation must remain disabled until the institution's trusted public key is provisioned and a matching signed test package is available. Before production use, test on a separate cPanel staging site with backup/restore and rollback. No production theme activation is authorized by this specification.
