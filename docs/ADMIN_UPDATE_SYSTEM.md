# Admin Update Center and Safe Release System

**Status:** Partially implemented; signed package verification and isolated private extraction are available, but code activation and rollback are not implemented.
**Target:** Laravel 12 / PHP 8.3+ on cPanel shared hosting (Web Host BD).
**Owner decision:** The live LMS must be maintainable and updatable after launch without manually replacing the whole website for every routine improvement.

## 1. Product requirement

Build an Admin-only **System Updates** area inside the existing unified Admin Panel. After production launch, an authorized administrator must be able to safely install a verified LMS release from the admin interface, inspect what will change, see progress and results, and recover from a failed update.

This is separate from normal content management. Admins must also be able to update courses, lessons, homepage content, banners, pricing, flash sales, categories, eBooks, notices, translations, themes and settings through their existing admin modules without changing application code.

## 2. Two kinds of updates

### A. Routine content/configuration updates (no code deployment)

These must be manageable through the Admin Panel and must take effect without a developer or file upload:
- Course and lesson content, video links/assets, thumbnails, SEO metadata, categories and tags.
- Homepage preset, sections, sliders, header/footer, logo, favicon, brand colors and typography.
- Course pricing, coupons, flash sales, admission campaigns and enrollment rules.
- Batch/branch schedules, notices, FAQs, blog/news, downloadable resources and eBooks.
- Admin-controlled system settings and supported Bangla/English text.
- User/role assignments within the permission model.

Content changes must be validated, previewable where appropriate, logged, and reversible where feasible. Do not expose arbitrary PHP, SQL, shell commands or unrestricted HTML/JavaScript editing as a content feature.

### B. Application/code updates (System Update Center)

The update center must support versioned, signed release packages produced by the project's trusted CI/release pipeline. It must never download and execute an arbitrary URL or trust a package solely because it is hosted on GitHub.

## 3. Admin experience

Admin navigation: **System → Updates**.

The screen must show:
1. Installed LMS version, release channel and last successful update.
2. Latest available stable version and release date.
3. Release notes, security notices, compatibility requirements and changed modules.
4. Preflight results: PHP version/extensions, disk space, writable directories, database connectivity, current schema version, backup destination, package integrity and maintenance readiness.
5. A clear impact summary: database migrations, file changes, downtime estimate (only if measured), and any manual action required.
6. Buttons: Check for updates, View release notes, Create backup, Start update, View logs, Verify installation and Roll back (only when safe and supported).
7. A persistent progress/status record; refresh/navigation must not lose update state.
8. Bangla and English UI, responsive on mobile/desktop.
9. A clear “already up to date” state and a safe error/retry path.

Only the unified Admin role can access the update center. Branch managers, teachers, students and guardians must receive 403 for all update routes, APIs, logs, package downloads and actions.

## 4. Trusted release and package security

- CI creates immutable, versioned release artifacts from a reviewed commit/tag.
- A release manifest includes product identifier, semantic version, release channel, Git commit SHA, minimum/maximum supported PHP and Laravel versions, required extensions, package SHA-256, migration/schema version, release notes, and artifact URL.
- Sign the manifest (and preferably artifact metadata) with an offline/private release key. The private signing key must never be stored in the LMS repository or production hosting. The production app contains only the public verification key.
- Verify signature, SHA-256, product identifier, version ordering, compatibility, package structure and allowed paths before extraction.
- Reject path traversal, symlinks, unexpected executable payloads, oversized archives, duplicate paths, downgrade attempts and unsigned/invalid packages.
- Allow only configured trusted release channels. No admin-supplied arbitrary package URL.
- Never include .env, APP_KEY, database credentials, uploaded student files, private media, logs, sessions or backups in a release package.
- Never overwrite user uploads, private files, .env, or runtime storage with code packages.
- Protect every state-changing endpoint with authentication, Admin authorization, CSRF, validation, rate limiting and audit logging. Require password re-authentication for applying updates and rollback.
- Prevent concurrent updates with a lock and an expiring recovery strategy. Do not rely on a browser tab remaining open.

## 5. Safe update lifecycle

1. Check for updates and fetch only the signed manifest.
2. Show release notes and compatibility/preflight results.
3. Admin explicitly confirms the selected release and impact.
4. Create and verify a pre-update backup of database, uploaded media, private files and current release metadata. Store backups outside the public web root; never send them to the release server.
5. Download package to a non-public temporary directory and verify signature/hash/structure.
6. Put the site into maintenance mode with a user-friendly page; preserve an authenticated recovery route or documented CLI recovery path.
7. Extract into a versioned staging/release directory. Never unpack directly over the currently running code.
8. Validate required files and permissions, then activate the new release using an atomic directory/symlink switch where hosting supports it. For cPanel environments without reliable symlink/rename semantics, use a tested staged file-swap strategy with a complete file-level rollback snapshot.
9. Run only reviewed, additive, reversible Laravel migrations. Never run destructive migrations automatically; flag them for manual intervention and a separately reviewed migration plan.
10. Clear/rebuild Laravel caches and run health checks, route checks and a minimal application smoke test.
11. Mark release successful only after every required step passes; write an immutable audit record with actor, old/new versions, commit SHA, package digest, timestamps, migration list, backup reference and outcome.
12. If any step fails, stop, restore the prior code release and use the documented database rollback/restore strategy. Do not blindly restore an old database over new user activity. If migration rollback is not safe, keep maintenance mode enabled and provide a recovery procedure requiring operator intervention.
13. Exit maintenance mode only after the health check passes. Notify Admin of success/failure.

## 6. cPanel-compatible operations

The first supported deployment target is Web Host BD cPanel with PHP 8.3+ and MySQL/MariaDB.

- Prefer an application layout where the public document root points to a stable current/public release and immutable releases live outside public_html.
- Confirm whether the host supports symlinks, atomic rename, background processes, CLI PHP, cron and sufficient disk space before selecting activation mechanics.
- Provide an Admin-triggered update flow with bounded jobs plus a documented cPanel cron/CLI recovery option. Never assume a long HTTP request can safely complete a large package extraction or migration.
- Keep .env, uploads, private lesson media, assignment submissions, backups and logs in persistent shared storage outside release directories.
- Require a separate staging subdomain and empty test database before first production update. Production must not be used as a test environment.
- Maintain a documented manual recovery procedure for cases where the admin UI becomes unavailable.

## 7. Data and migration rules

- All schema changes must be versioned Laravel migrations.
- Migrations must be additive and backward-compatible where practical (expand → migrate data → switch code → contract in a later release).
- Never drop/rename populated columns or tables in the same release that stops using them.
- Back up and verify before migration; test both fresh install and upgrade-from-previous-release paths in CI.
- Preserve all student, guardian, teacher, branch, course, enrollment, attendance, exam, payment, invoice, eBook and media records.
- Keep application version and schema version separately observable.
- Provide migration preflight and a clear blocked state when a required manual data operation is missing.

## 8. Test and release acceptance

Required automated tests:
- Only Admin can view/check/download/apply/rollback; all other roles receive 403.
- Invalid signature, hash mismatch, wrong product, downgrade, incompatible PHP/Laravel, malicious ZIP paths, symlink entries and oversized package are rejected before extraction.
- Failed download, partial extraction, failed migration, failed health check, timeout and concurrent update never report success.
- Backup is created and verified before activation.
- Rollback restores the previous code release and leaves persistent user files untouched.
- No package can overwrite .env, runtime storage, uploads, backups or private media.
- Migrations preserve seeded representative records for every critical module.
- Update state and audit trail survive page refresh and interrupted browser sessions.
- Fresh install and previous-release upgrade both pass on clean CI databases.
- A real cPanel staging test validates the exact production-like file layout, PHP CLI version, permissions, disk capacity, activation, backup and recovery.

Release gates: CI green on exact release SHA; CodeQL/security review; package signature verified; staging update and rollback accepted; owner-visible release notes. A green unit-test run alone is not production acceptance.

## 9. Non-goals / safety boundaries

- Do not expose “run SQL”, “run PHP”, “run shell command”, arbitrary plugin upload or arbitrary URL install controls in the Admin Panel.
- Do not promise one-click rollback of irreversible data migrations.
- Do not run updates automatically without an Admin confirmation for each release.
- Do not silently alter payment credentials, payment provider mode, branch access or user permissions during updates.
- Do not update production from an unreviewed branch or draft PR.

## 10. Current implementation assessment

The repository now includes a signed release-manifest pipeline and Admin-only release checks. The Admin Update Center can verify signed metadata, record check history, download a newer release to private local storage, verify the package SHA-256 and signed migration-set digest, reject unsafe archive paths, secret/runtime files, symlinks, special file types, oversized entries and untrusted redirects, and extract a verified package into an isolated private release directory with restrictive file permissions. Prepared and extracted packages have a 24-hour expiry; expired files are marked and removed from private staging storage when the next package-preparation or extraction action runs. The implementation was delivered through PRs #86–#90; PR #91 adds a regression test for the staging schema.

The following are still **not implemented**: automated database/media backup creation from the updater, complete impact review, maintenance mode, atomic or cPanel-safe code activation, controlled migration execution, post-activation health checks, durable progress logs for installation, tested code restore/rollback and cPanel staging acceptance. A database-backed exclusive lease now serializes package preparation, private extraction, manual and pre-activation backup creation, backup restore and activation preflight; its 90-minute expiry allows recovery after a terminated PHP request. Restore verifies the signed archive and paths before acquiring the lease, then performs database replacement and media writes while the lease is held; successful and failed restore attempts are recorded in the durable Admin Update Center audit timeline. The Admin screen shows the active operation and disables conflicting controls, while the server rejects concurrent requests independently of the browser UI. Admin can manually create a fresh database/media backup in Admin → Backups; the backup now includes all files on the public upload disk (including course images, lesson thumbnails and flash-sale banners), plus all files on the private local upload disk (including payment proofs, teacher documents, assignment submissions, course lesson attachments and protected lesson videos). Backup and updater staging directories are excluded to prevent recursive snapshots. The updater verifies the HMAC, current table coverage and that every referenced course/lesson asset is present, creates a private code snapshot excluding `.env`, runtime storage, uploads and development files, and ties both hashes to the staged release. The read-only activation preflight revalidates these backup files along with the current server, filesystem, database driver and pending migration names. It still blocks activation because the activation runner and tested recovery path do not exist. Package extraction is isolated under private storage only; it does not install, execute or activate code, run migrations, or modify the live application.

The requirement is therefore **not complete**. Do not enable code installation or advertise one-click updates until the remaining phases and real cPanel staging/recovery rehearsal have passed.

## 11. Delivery phases

- **Phase 1 — Release foundation:** implemented in code (immutable CI release artifacts, signed manifest, version marker, package allow-list and trusted manifest verification). Still requires owner-managed signing-key provisioning and a published signed release.
- **Phase 2 — Admin Update Center:** partially implemented (Admin-only status/check history, private package download/verification and a durable read-only activation-readiness preflight). Automated database/media backup creation, complete impact review, persistent install progress/logs and complete audit details remain; manual DB/media backup verification and tied private code snapshots are implemented.
- **Phase 3 — Safe installer/activation:** partially implemented (verified packages can be extracted into isolated private storage with restrictive permissions and disk-space checks). Persistent shared-storage wiring, maintenance mode, code activation and health checks remain unimplemented. A database-backed expiring operation lock now protects staging, extraction, backup and preflight actions, but it is not a substitute for an activation/recovery lock until those phases are implemented.
- **Phase 4 — Recovery:** not implemented (tested code rollback, migration compatibility strategy, failure recovery and cPanel CLI/cron fallback).
- **Phase 5 — Acceptance:** not complete (upgrade-from-previous-version CI matrix, security tests, cPanel staging rehearsal, restore/rollback drill and documented production runbook).

Do not mark the system complete until all five phases and the cPanel staging acceptance have passed.