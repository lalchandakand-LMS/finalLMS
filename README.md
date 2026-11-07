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
- Admin, branch manager, accountant, teacher, and student role foundations; role middleware and protected routes
- Branch managers are assigned one branch and can create/update only that branch's batches; accountants can access the invoice ledger without admin payment-review or user-management access
- Courses, lessons, course enrollment, certificates, and manual payment review
- Branch and batch create/read/update/delete controls; teacher-scoped batch views
- Batch enrollment associations, teacher attendance entry, and student-private attendance history
- Batch assignments, one submission per enrolled student, teacher grading/feedback, and private PDF/DOC/DOCX/JPG/PNG attachment submissions (10 MB limit)
- Batch live-class scheduling with teacher ownership checks, student enrollment filtering, and time-windowed meeting-link visibility
- Batch multiple-choice examinations with one-attempt enforcement, timed attempts, automatic scoring, student result views, teacher-only paginated result reports, reusable teacher-owned question banks, and randomized question selection
- Public published-course catalog and slug URLs; lesson bodies require enrollment except preview lessons
- Lesson progress tracking; certificates require every course lesson completed
- Batch seat reservation for pending payments, unique transaction references, and last-admin protection
- SEO metadata, consent-gated GA4/GTM/Meta Pixel tags, and consent-based queued bulk SMS
- Admin homepage, payment, user, course, and settings controls
- Student invoices, partial/installment receipts, manual payment ledger, and outstanding balance tracking
- Expense management with create/edit/delete, category and date filters, branch-scoped accountant access, and head-office expenses
- Finance reports with payment-method and expense-category summaries, date filters, current outstanding balance, printable ledger, and net cash movement (collections minus expenses)
- Admin activity log for successful authenticated changes, with user/action/route/IP filters
- Public About, Contact, FAQ, and published Blog/News pages; admin-managed article drafts, publishing, SEO metadata, and cover images
- Section-based homepage Website Builder with up to 12 editable text/image/button sections; user content is escaped and does not accept arbitrary scripts
- Admin Media Library for validated image, document, and video uploads with protected deletion of assets referenced by articles
- Signed, application-key-protected database + uploaded-media + private assignment-attachment backup archives; administrator restore requires current password and explicit confirmation, with schema compatibility checks
- English/Bangla session-based language selector and expanded JSON translation catalogue for core navigation, authentication, course, profile, exam, live-class, and CMS screens

## Not yet complete

This remains an actively developed LMS and is not yet a fully validated commercial release. Remaining work includes complete Bangla translation coverage across every screen, richer drag-and-drop page design and reusable page templates, additional branch-scoped student/enrollment workflows, discounts and refunds, official bKash/Nagad/SSLCommerz API integration with verified callbacks, automated scheduled off-site backups and tested disaster recovery, and a secure web installer. The current backup/restore feature is administrator-triggered and should be tested on a staging database before production use. Activity logging covers successful authenticated write requests; it is not yet a full audit trail for reads, login attempts, failed actions, or field-level changes. SMS/email delivery requires real provider credentials.

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
