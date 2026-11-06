# Alom's English Center LMS

Laravel 12 LMS application targeting PHP 8.3+ and MySQL/MariaDB. The repository was missing its runtime directories; they have been restored and the app boots on Laravel 12.

## Local development

```bash
composer install
cp deployment/.env.example .env
```

For local use, set `APP_ENV=local`, `APP_DEBUG=true`, `DB_CONNECTION=sqlite`, and `MAIL_MAILER=log` in `.env`, then run:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The local/testing seeder creates `admin@lms.test`, `teacher@lms.test`, and `student@lms.test` with password `password`. These accounts are local-only; production seeding does not create them. See [cPanel deployment](deployment/cpanel.md) for production setup.

## Implemented

- Student registration, email verification, profile updates, and Twilio phone OTP integration
- Admin, teacher, and student roles; role middleware and protected routes
- Courses, lessons, course enrollment, certificates, and manual payment review
- Branch and batch create/read/update/delete controls; teacher-scoped batch views
- Batch enrollment associations, teacher attendance entry, and student-private attendance history
- Batch assignments, one submission per enrolled student, and teacher grading/feedback
- Batch live-class scheduling with teacher ownership checks, student enrollment filtering, and time-windowed meeting-link visibility
- Batch multiple-choice examinations with one-attempt enforcement, timed attempts, automatic scoring, and student result views
- Public published-course catalog and slug URLs; lesson bodies require enrollment except preview lessons
- Lesson progress tracking; certificates require every course lesson completed
- Batch seat reservation for pending payments, unique transaction references, and last-admin protection
- SEO metadata, consent-gated GA4/GTM/Meta Pixel tags, and consent-based queued bulk SMS
- Admin homepage, payment, user, course, and settings controls
- Student invoices, partial/installment receipts, manual payment ledger, and outstanding balance tracking
- Admin activity log for successful authenticated changes, with user/action/route/IP filters
- Public About, Contact, and FAQ pages with admin-managed, safely escaped text content

## Not yet complete

This is not yet the complete commercial LMS described in the acceptance specification. Super-admin, branch-manager, and accountant permission matrices; discounts and refunds; media library, blog/news publishing, custom landing-page sections, and Bangla translations; automated backup management; and a secure web installer are not implemented. Activity logging covers successful authenticated write requests; it is not yet a full audit trail for reads, login attempts, failed actions, or field-level changes. Payments are manual-review only; official bKash, Nagad, and SSLCommerz APIs and callback verification are not implemented. SMS/email delivery requires real provider credentials.

## First production administrator

Student registration only creates student accounts. After configuring the production database, create and verify the first admin through Tinker using a unique password:

```bash
php artisan tinker
```

```php
$admin = App\Models\User::create([
	'name' => 'Site Administrator',
	'email' => 'your-admin@your-domain.example',
	'password' => 'replace-with-a-unique-strong-password',
	'role' => 'admin',
]);
$admin->markEmailAsVerified();
```

Replace the example values before running the commands. Passwords are hashed by the user model.

## Tests and assets

```bash
vendor/bin/phpunit
npm ci
npm run build
```

The GitHub Actions workflow runs tests on PHP 8.3 with MySQL and builds Vite assets. Production does not require Node after `public/build` is generated.
