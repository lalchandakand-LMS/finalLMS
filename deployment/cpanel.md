# cPanel Deployment

This guide deploys the current Laravel 12 application. It is not a one-click installer; create the database and administrator before opening the site.

## Requirements

- PHP 8.3 with `bcmath`, `ctype`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, and `xml` enabled
- MySQL or MariaDB database and database user
- Composer 2 available through cPanel Terminal
- A persistent writable `storage` and `bootstrap/cache` directory

## Deploy

1. Build frontend assets on a development machine or CI with `npm ci && npm run build`. Upload `public/build` with the application; Node is not required on the production server.
2. Keep the application outside `public_html` where possible. Set the domain document root to the application's `public` directory. Never expose the repository root, `.env`, `vendor`, or `storage` through the web server.
3. Upload the source and install production dependencies from the application directory:

   ```bash
   composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
   ```

4. Copy `deployment/.env.example` to `.env`, then set the real domain, MySQL credentials, SMTP credentials, and other secrets in cPanel Terminal or its environment editor. Generate the application key:

   ```bash
   php artisan key:generate --force
   ```

5. Ensure `APP_ENV=production` and `APP_DEBUG=false`. Make `storage` and `bootstrap/cache` writable by the PHP account, then migrate:

   ```bash
   php artisan migrate --force
   ```

6. Create the first admin using the procedure in the project README. Do not run local demo seeders in production.
7. Configure SMTP and test email verification. Configure Twilio only if phone verification or SMS campaigns are enabled; secrets belong in `.env`, not in admin settings.
8. Use a cPanel cron job to drain queued SMS jobs, for example once each minute:

   ```bash
   * * * * * cd /home/ACCOUNT/aloms_lms && php artisan queue:work --stop-when-empty --tries=3
   ```

9. Run route checks and smoke tests before opening enrollment. Set up database backups and monitor application logs.

## Release Artifact

The `Laravel CI` workflow creates the `AlomsEnglishCenter-cpanel.zip` artifact only after the clean MySQL migrations, automated tests, route check, and asset build pass. Download the artifact from the successful Actions run. The ZIP includes `vendor` and built assets, and excludes `.env`, local SQLite files, logs, tests, Git metadata, and Node modules.

## Known Deployment Gaps

There is not yet a web installer, installation lock, or automated backup manager. Payment methods currently use manual verification; official bKash, Nagad, or SSLCommerz API callbacks are not implemented. Do not advertise automatic payment processing until callback signatures and provider transactions are implemented and tested. The cPanel guide has not been exercised on an actual cPanel account.