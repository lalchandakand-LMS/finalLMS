# Clean cPanel Installation Runbook — Alom's English Center

**Owner-selected plan:** fresh LMS installation on `https://alomsenglishcenter.com`, a brand-new MySQL database, and a retained backup of the existing website/database.
**Application:** Laravel 12, PHP 8.3+, MySQL/MariaDB.
**Status:** preparation checklist only. This document does not mean a backup has been taken, the old site has been removed, or the new LMS has been deployed.

## Non-negotiable safety rules

1. Do not delete, overwrite, rename, or empty the existing `public_html`, old database, or uploaded files until a complete backup has been created and independently verified.
2. Keep the old website archive and SQL export outside the public document root. Prefer a second copy downloaded to the owner's computer or another storage provider. A backup stored only in the same hosting account is not disaster recovery.
3. Never use the old database for the new installation. Create a new database and a dedicated database user with privileges only on that database.
4. Never run the web installer against an existing database. The installer must refuse a non-empty database; it must never drop or truncate tables.
5. Do not put `.env`, `vendor/`, `storage/`, backups, SQL dumps, or the Laravel application root inside a publicly served directory.
6. If a step cannot be verified, stop and preserve the old site. Do not improvise destructive cleanup.

## Phase A — record the current hosting state

Before making changes, record:
- cPanel account/home directory and the current domain document root.
- Existing `public_html` file/folder listing and approximate size.
- Existing database names and which one the old website uses (do not expose credentials in notes or chat).
- PHP version (must be 8.3+) and required extensions: `bcmath`, `ctype`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.
- SSL/HTTPS status, current DNS, email accounts and any cron jobs.
- Current site screenshots/URLs and any custom redirects or email configuration that must be retained.

Do not publish passwords, API keys, `.env` contents, database credentials, or private backup links.

## Phase B — back up the old site before touching it

1. In cPanel, create a full account backup if the host supports it. Download it to a separate computer/storage location.
2. Create a compressed archive of the complete existing website files, including hidden files such as `.htaccess`, but exclude no application uploads or configuration needed for recovery. Save it outside `public_html`, then download a second copy.
3. In phpMyAdmin, identify the exact database used by the old site and export it as SQL (prefer gzip compression). Do not export a different database by guesswork.
4. Back up any separately stored uploads, private files, email settings, DNS records and cron definitions that are relevant.
5. Record archive names, file sizes, timestamps and SHA-256 hashes where possible.
6. Verify the compressed archive can be listed/opened and the SQL dump is non-empty and contains the expected table definitions and records. Do not restore over the old database merely to test it.
7. Confirm the second copy is readable from the owner's computer or other independent storage.

**Backup gate:** do not continue until both the old website archive and matching SQL export are verified and a second copy exists.

## Phase C — create an isolated new database

1. cPanel → MySQL Database Wizard: create a clearly named new database, e.g. the account-prefixed equivalent of `aloms_lms_prod`.
2. Create a new, unique database user and a strong password. Do not reuse the old site's database user/password.
3. Grant that user the required privileges on the new database only.
4. Record database name, username, host (usually `localhost`) and port (usually `3306`) in a private password manager.
5. Confirm the database is completely empty. Do not import the old SQL dump.
6. Keep the old database intact and unchanged for rollback/reference.

## Phase D — prepare the new LMS release

1. Use only the exact `AlomsEnglishCenter-cpanel.zip` artifact from a successful GitHub Actions package workflow for the reviewed release commit.
2. Confirm the workflow's test, MySQL migration, route-check and package jobs all passed for that exact commit. A passing test run on another commit is not sufficient.
3. Record the release commit SHA and artifact checksum. Do not use a random branch ZIP or a developer's local archive as a production release.
4. Extract the application into a new versioned directory outside `public_html`, e.g. `/home/ACCOUNT/aloms_lms/releases/initial-install`.
5. Point the domain document root to that release's `public` directory if the hosting plan permits it. Verify the document root resolves to Laravel's public directory and not the application root.
6. If cPanel cannot change the document root safely, stop and prepare a host-specific public-directory deployment plan; do not copy the entire Laravel project into `public_html` or expose private directories.
7. Ensure only the necessary Laravel runtime directories are writable by the PHP account. Do not use world-writable permissions.

## Phase E — fresh install

1. Verify HTTPS works for `https://alomsenglishcenter.com`.
2. Open `https://alomsenglishcenter.com/install`.
3. Enter site name `Alom's English Center`, HTTPS site URL, the new database connection details, and a unique admin account.
4. Use a unique administrator password of at least 12 characters; do not reuse an old website password.
5. Submit once and wait for the installer response. Do not repeatedly submit while migrations are running.
6. The installer should test DB connectivity, refuse a non-empty DB, create a random APP_KEY, write a private `.env`, run migrations/production seeders, create the first admin, and lock itself.
7. If installation fails, stop. Inspect the private Laravel log and database state. Do not manually delete tables or rerun migrations blindly. If a partial migration has occurred, create another new empty database and retry only after diagnosing the cause.
8. After success, verify `/install` returns 404 and `.env` is not web-accessible.

## Phase F — acceptance checks before public launch

- Public homepage loads on HTTPS with no debug output.
- Admin login works; administrator can change password and center branding.
- Student registration and login work; email/phone verification status is explicit.
- Admin can create a category, course, module/lesson, publish it, and view it publicly.
- Student enrollment, payment-record workflow, protected lesson access and progress behave correctly.
- Teacher access is limited to assigned classes/batches.
- Guardian routes do not reveal unrelated students' data; if guardian workflows are incomplete, do not advertise them as ready.
- Manual bKash/Nagad payment records are clearly distinguished from automated checkout. Do not enable live gateway credentials until provider sandbox and callback verification are accepted.
- Upload, private file access, backup creation/download and logs are tested.
- `APP_ENV=production`, `APP_DEBUG=false`, correct HTTPS URL, storage link, cache and permissions are verified.
- Create a fresh backup of the newly installed LMS before opening admission.

Record each check as pass/fail with timestamp and evidence. A repository CI pass is not a substitute for these cPanel acceptance checks.

## Phase G — launch and rollback

1. Only after acceptance, update the domain document root / release pointer to the verified LMS release and clear caches as documented.
2. Keep the old website archive, old SQL dump, and old database unchanged during the observation period.
3. Monitor Laravel logs, server errors, disk space and failed login/payment events.
4. If the new LMS fails, put the site into a maintenance page and restore the old document root/files and matching old database configuration from the verified recovery set. Do not combine old code with the new database or vice versa.
5. Do not drop the old database or delete the old archive until the owner explicitly confirms the new LMS is stable and the retention period has passed.

## Owner-specific readiness facts

- Domain: `alomsenglishcenter.com`.
- Host: Web Host BD cPanel.
- PHP 8.3 and SSL were reported available by the owner.
- Owner selected a **new database** and **backup of the old site**.
- Staging subdomain and separate staging database were not available in the last confirmed hosting update.
- No verified cPanel login/session or host-side file/database access is available to this runbook author. The actual backup, new database creation, file placement, install and DNS/document-root changes must be performed in the owner's cPanel session or by an authorized hosting operator.

## Explicitly not authorized by this runbook

This plan is not permission to delete the old site, drop its database, overwrite live files, change DNS, or claim production deployment. Those actions require the verified backup gate and a separately confirmed release/maintenance window.
