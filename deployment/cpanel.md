# cPanel Deployment

This guide covers a fresh Laravel 12 installation on PHP 8.3+ with MySQL/MariaDB. The secure web installer creates the private environment file, runs migrations, seeds safe default website settings, and creates the first administrator. It never drops existing database tables.

## Requirements

- PHP 8.3 with `bcmath`, `ctype`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, and `zip` enabled
- A new, empty MySQL/MariaDB database and database user with full privileges on that database
- HTTPS enabled for the domain
- Writable `storage`, `bootstrap/cache`, and application-root directories during installation
- Composer 2 only if installing from source; the CI release ZIP already includes production `vendor` dependencies and built assets

## Staging before production

Use the [cPanel staging acceptance and release runbook](../docs/CPANEL_STAGING_RUNBOOK.md) to validate a separate staging subdomain, empty test database, role-based access, file privacy, backups, and rollback before any production release. Do not use the live database as a staging substitute.

## Fresh installation

1. Create a new database and database user in cPanel. Do not use a database containing existing tables or application data.
2. Download the `AlomsEnglishCenter-cpanel` ZIP from a successful GitHub Actions package run and extract the application outside `public_html` where possible.
3. Set the domain document root to the application's `public` directory. Never expose the application root, `.env`, `vendor`, or `storage` through the web server.
4. Ensure the application root and Laravel writable directories are writable by the PHP account for the installation. Restrict permissions again after setup where your host permits.
5. Visit `https://your-domain.example/install`. Enter the site URL, database credentials, and a unique administrator email/password (minimum 12 characters).
6. The installer tests the database connection, refuses a database that already has a `migrations` or `users` table, creates a random application key, writes a private `.env` file with restrictive permissions, runs migrations and safe production seeders, creates the administrator, and locks installation.
7. Sign in at `/login`. The installer URL returns 404 after successful setup. Keep `.env` outside the public document root and never share its contents.

If installation fails, inspect `storage/logs/laravel.log`, verify database privileges and PHP extensions, and retry. If the installer reports an existing manually-created `.env`, it will not overwrite it; remove that file only when you have confirmed this is a new installation and backed up any configuration you need.

## Existing/manual installation

For an existing deployment, back up files and database first, follow the migration procedure below, and preserve its existing `.env`. The installer must not be used to overwrite an existing application. Set `APP_INSTALLED=true` in an existing production `.env` if you are upgrading from a release that predates the installation gate.

## Runtime configuration

- Keep `APP_ENV=production` and `APP_DEBUG=false`.
- Configure SMTP and test email verification. Configure Twilio only if phone verification or SMS campaigns are enabled; secrets belong in `.env`, not admin settings.
- Use a cPanel cron job to drain queued SMS jobs, for example once each minute:

  ```bash
  * * * * * cd /home/ACCOUNT/aloms_lms && php artisan queue:work --stop-when-empty --tries=3
  ```

- Run route checks and smoke tests before opening enrollment. Set up database backups and monitor application logs.
- Keep the packaged `VERSION` file with the deployed code. Leave `LMS_VERSION` empty to read the installed version from this marker; the signed release workflow stamps it from the immutable version tag.
- Signed update checks remain disabled by default. The read-only Admin Update Center requires an independently verified RSA public key stored outside the public web root before `LMS_UPDATES_ENABLED=true` is set. It checks signed release metadata only; it does not install code or run migrations.

## Release Artifact

The `Laravel CI` workflow creates the `AlomsEnglishCenter-cpanel.zip` artifact only after the clean MySQL migrations, automated tests, route check, and asset build pass. Download the artifact from the successful Actions run. The ZIP includes `vendor` and built assets, and excludes `.env`, local SQLite files, logs, tests, Git metadata, and Node modules.

## Known Deployment Gaps

The web installer and installation gate are implemented in code, but still require a successful GitHub CI run and a real cPanel staging installation. Automated scheduled off-site backups and disaster recovery are not yet implemented. The bKash Tokenized Checkout adapter now performs server-side token, execute, and payment-status verification. It remains disabled until the academy supplies official merchant credentials, configures the registered callback URL, and completes sandbox and production verification. Nagad and SSLCommerz direct API integrations are not implemented; Nagad remains configuration-only until official merchant documentation and credentials are supplied. Do not advertise automatic Nagad or SSLCommerz payment processing. The cPanel guide has not been exercised on an actual cPanel account.

## Post-launch application updates

The long-term update design and acceptance criteria are documented in [Admin Update Center and Safe Release System](../docs/ADMIN_UPDATE_SYSTEM.md). This is an approved requirement, not a claim that the updater is already implemented. Until the signed release/update workflow is implemented and accepted on cPanel staging, deploy reviewed release ZIPs using the manual backup-and-release procedure; do not install arbitrary packages or overwrite the live application in place.
