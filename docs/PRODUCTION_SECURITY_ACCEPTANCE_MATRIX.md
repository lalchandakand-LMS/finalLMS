# Production Security & Release Acceptance Matrix

This document is an evidence checklist, not a declaration that production acceptance has already passed.

## Automated checks in the repository

| Area | Automated evidence | Release gate |
|---|---|---|
| Portal authorization | `PortalPermissionMatrixTest` covers Admin, Branch Manager, Teacher, Student, Guardian and Accountant: own portal allowed, all other portal entry routes denied | Required CI pass |
| Branch isolation | `BranchManagerDataScopeTest`, `BranchScopedStaffTest`, `PluginGuardianCurrentBranchScopeTest`, and branch-scoped invoice tests | Required CI pass; include cross-branch read and write denial |
| API security | Mobile API foundation, device approval, instructor/student API feature tests; token hashing, expiry/revocation, role restrictions, throttling and ownership checks | Required CI pass; verify every protected API group and object-level authorization |
| Plugin trust/lifecycle | Signature verification, installed-file integrity, archive installer, lifecycle, migration runner, runtime and queued-job lifecycle tests | Required CI pass; invalid signatures, tampering, traversal and unsafe transitions must fail closed |
| Backup/restore | Backup/restore tests cover private backup access, password + explicit confirmation, tamper rejection, database/media restoration and automatic compensation after failed restore | Required CI pass; verify retained safety snapshot and recovery logs |
| Migrations/rollback | MySQL full migration in CI, MariaDB negative-marking rollback safety, SQLite plugin-route checks | Required CI pass; every release migration must be reviewed for reversible and data-safe behavior |
| Static security analysis | CodeQL `security-extended` for JavaScript/TypeScript; Semgrep `p/php` + `p/security-audit` for PHP application/plugin code | Required scan pass on the exact release SHA; review findings and suppressions |\n| Dependencies/build | Composer strict validation/audit, production npm audit, PHP syntax lint, route:list, frontend build, cPanel package integrity checks | Required CI pass on the exact release SHA |

## Manual acceptance required before production release

### cPanel clean installation
- Use a brand-new empty database and dedicated database user.
- Confirm PHP 8.3+ and required extensions, HTTPS, private Laravel root, and public document root pointing only to `public/`.
- Install once; verify installer lock, admin creation, migrations, storage permissions, and no public access to `.env`, `vendor`, `storage`, SQL dumps or backup archives.
- Confirm installation refuses a non-empty database without dropping or truncating existing tables.

### Upgrade and rollback
- **Current updater limitation:** the in-app update center currently supports signed release check, package staging/extraction, pre-activation backup and preflight only. It has no live activation/switch route yet; do not treat it as an automatic production updater or claim end-to-end upgrade completion.
- Start from a production-like copy of the previous supported release and a verified backup.
- Upgrade using the exact packaged release artifact and documented maintenance mode/cache steps.
- Verify migrations, existing users/enrollments/payments/files, plugin compatibility, queue/cron behavior and login.
- Exercise the documented rollback on a disposable clone; verify application code, database and media are restored as a consistent set.
- Never test destructive upgrade/restore operations on the live academy database.

### Mobile and desktop browsers
- Automated Playwright smoke coverage runs the public home, course catalogue and login pages at 360px, 390px, 768px, 1024px and 1440px, asserting successful HTTP responses and no horizontal overflow.
- This automated Chromium smoke test is not a substitute for Safari/Firefox/Edge, real Android/iOS devices, authenticated role workflows or accessibility review.
- Test current Chrome, Edge, Firefox and Safari where available; test Android Chrome and iOS Safari on real devices.
- Check login/MFA/device approval, navigation, forms, attendance, exam flows, invoices/receipts, file/video access, keyboard focus, validation and error states.
- Check representative widths: 360px, 390px, 768px, 1024px and 1440px; verify no horizontal overflow, clipped dialogs, inaccessible menus or broken tables.
- Record browser/version, device, viewport, test account role, result and screenshot for each critical workflow.

## Evidence and status recording

A check is PASS only when its exact commit SHA, environment, test command or manual scenario, result and timestamp are recorded. A skipped test is not a pass. CI does not prove cPanel or real-device acceptance.

Production status remains NOT READY until all automated checks pass on the release SHA and the manual cPanel, upgrade/rollback and real browser/device checks are signed off by the owner or authorized hosting operator.