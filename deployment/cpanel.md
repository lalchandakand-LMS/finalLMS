# cPanel Deployment

This guide covers a fresh Laravel 12 installation on PHP 8.3+ with MySQL/MariaDB. The secure web installer creates the private environment file, runs migrations, seeds safe default website settings, and creates the first administrator. It never drops existing database tables.

## Requirements

- PHP 8.3 with `bcmath`, `ctype`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, and `xml` enabled
- A new, empty MySQL/MariaDB database and database user with full privileges on that database
- HTTPS enabled for the domain
- Writable `storage`, `bootstrap/cache`, and application-root directories during installation
- Composer 2 only if installing from source; the CI release ZIP already includes production `vendor` dependencies and built assets

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

## Release Artifact

The `Laravel CI` workflow creates the `AlomsEnglishCenter-cpanel.zip` artifact only after the clean MySQL migrations, automated tests, route check, and asset build pass. Download the artifact from the successful Actions run. The ZIP includes `vendor` and built assets, and excludes `.env`, local SQLite files, logs, tests, Git metadata, and Node modules.

## Known Deployment Gaps

There is not yet a web installer, installation lock, or automated backup manager. Payment methods currently use manual verification; official bKash, Nagad, or SSLCommerz API callbacks are not implemented. Do not advertise automatic payment processing until callback signatures and provider transactions are implemented and tested. The cPanel guide has not been exercised on an actual cPanel account.