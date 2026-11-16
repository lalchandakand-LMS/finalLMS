# Admin Plugin Manager

The Admin Plugin Manager is the guarded web entry point for signed plugin
lifecycle operations across all LMS portals (`admin`, `branch`, `teacher`,
`student`, `guardian`, `support_staff`, `public`). It is available only to the
root `admin` role under the authenticated Admin workspace.

## Capabilities

1. **Private directory discovery (`POST /admin/plugins/discover`)**: Safely
   scans `plugins/<vendor>/<slug>/<version>/plugin.json` without executing any
   PHP code or activating packages. Discovered entries require a trusted signed
   package before activation.
2. **Signed upload & install (`POST /admin/plugins/install`)**: Verifies JSON
   manifest, PHP/Laravel/LMS runtime compatibility, detached Ed25519 signature,
   SHA-256 checksum, and safe ZIP contents inside private `plugins/.staging`.
3. **Staged signed upgrades (`POST /admin/plugins/{key}/update`)**: Validates
   monotonic semantic version (`>` current version), compatibility, and signed
   archive; stages `update_staged`; records prior version metadata in
   `metadata.version_history`; runs new migrations; and rolls back safely on error.
4. **Activation & Deactivation (`activate` / `deactivate`)**: Enforces
   dependencies, conflicts, trusted release keys, integrity hashes, and route-cache
   checks. Deactivation immediately blocks plugin routes (`404`), hooks, and portal
   navigation links while retaining installed files and database data.
5. **Health checks (`POST /admin/plugins/{key}/health`)**: Checks release key trust,
   manifest validity, runtime compatibility, dependencies/conflicts, installed-file
   hashes, and migration status, persisting `health_status` and `health_message`.
6. **Uninstall preflight & sanitized JSON export (`GET /admin/plugins/{key}/export`)**:
   Shows active dependants and migration retention counts; exports secret-free
   manifest, version history, and migration records.
