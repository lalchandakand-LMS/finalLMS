# Customer Installation and Institution Branding

## Product delivery model

Alom's LMS is a self-hosted, independently installable product for schools, coaching centers, academies and other education providers. Each customer institution receives its own clean application installation, database, storage area, domain/subdomain and administrator account. One customer's records, files, credentials, users, branding and settings must never be visible to another customer's installation.

This is the default commercial delivery model. It is not a shared multi-tenant SaaS deployment. Do not add cross-customer data sharing or require a central Alom-operated server for normal LMS operation.

## Customer installation journey

1. Customer receives a versioned, verified installation package and matching release notes/checksum.
2. Customer prepares a supported hosting account (PHP 8.3+, required PHP extensions, MySQL/MariaDB, HTTPS, writable Laravel storage/cache paths and a supported document-root arrangement).
3. Customer creates a new empty database and a dedicated database user. Existing databases must never be reused or dropped by the installer.
4. Customer uploads/extracts the package outside the public document root where hosting permits and points the domain document root to the package's `public` directory.
5. Customer opens the secure first-run installer and enters institution name, public URL, database connection, initial administrator name/email/password and locale/timezone defaults.
6. Installer validates requirements and database connectivity, refuses a non-empty database, generates a unique application key, writes private environment configuration, runs migrations and safe production seeders, creates the first administrator, then permanently locks first-run installation.
7. Administrator completes the Institution Setup Wizard: logo/favicon, brand colors, contact information, address, currency, timezone, academic year, language, admission settings, payment methods, email/SMS provider configuration and public website details.
8. Administrator invites staff and creates branches, courses, batches, fees and student admission workflows.

The setup wizard must be resumable, display validation errors safely, avoid exposing secrets, and show a completion checklist. It must not silently overwrite an existing installation or migrate/drop customer data without an explicit, backed-up upgrade flow.

## Institution-owned identity (white-label configuration)

Every installation must be able to set and independently maintain:

- Institution display name and short name.
- Logo, compact logo, favicon and login-screen identity.
- Primary, secondary and accent colors with accessible contrast.
- Public website title, description, contact email/phone, address and social links.
- Default language, timezone, date format and currency.
- Email sender name/address and notification templates.
- Optional custom domain and HTTPS URL.
- Portal labels and institution-specific admission/contact instructions.

The customer must be able to replace all Alom demo/default institution details with their own. Customer-facing pages, emails, invoices, receipts, certificates, login screens and portal headers must render the current installation's saved identity. No other customer's identity or the product developer's personal details may appear as a forced footer, watermark or promotional badge. Product legal notices and license attribution, where legally required, belong in the customer contract and must not be confused with third-party CMS branding.

## Independent installation and upgrades

- Each customer installation uses a unique APP_KEY, database credentials, administrator account and private storage.
- Provide a repeatable cPanel/shared-hosting installation package; document the public document-root arrangement, required extensions, permissions, cron and storage setup.
- Include a preflight check, version manifest, package checksum, migrations, safe defaults and a post-install health check.
- Updates must be versioned and signed, verify compatibility, create and verify a restorable backup, apply reversible migrations where possible, and support rollback. Never overwrite customer uploads, environment secrets or institution settings.
- Provide a documented export/backup process so the customer can migrate hosting without losing records or private files.
- No runtime dependency on a developer workstation, Google AI Studio, external CMS, or central licensing API for core LMS operation.
- If a commercial license/activation mechanism is introduced, its policy (per installation/domain, term, transfer, renewal, offline grace and support entitlement) must be explicitly approved and documented before enforcement. Do not invent a license price or unexpectedly disable a customer's running LMS.

## Security and privacy boundaries

- Treat each independently installed customer environment as a separate security boundary.
- Enforce role and branch authorization inside each installation; menu hiding is not authorization.
- Never bundle real customer/student data, credentials, API keys, private files, database dumps or production APP_KEY in distribution packages.
- Use synthetic demo data only when explicitly enabled and easy to remove.
- Installer and update endpoints must be locked after use and protected against CSRF, brute force, path traversal, archive traversal and unauthorized package execution.
- Support customer-owned backups and storage providers without exposing private lesson, exam or student files publicly.

## Acceptance criteria before commercial distribution

- A fresh install succeeds on a clean supported cPanel account and a clean MySQL/MariaDB database.
- A second clean installation can be completed independently with a different institution name, domain, database and APP_KEY.
- Both installations can change logo, favicon, colors, contact details, locale, timezone and currency without code edits.
- Public site, admin, teacher/student/guardian portals, email templates, invoices, receipts and certificates consistently show the correct institution identity.
- No data, files, users, credentials or branding cross between the two installations.
- Installer refuses non-empty databases and cannot be reopened after successful installation.
- Package contains no developer machine configuration, secrets, real data or unapproved third-party branding.
- Upgrade, backup, restore and rollback pass on staging before any production/customer rollout.

## Current implementation status

The existing secure first-run installer already collects site name, HTTPS URL, database details and first administrator credentials; checks database connectivity; refuses a non-empty database; creates a random application key; runs migrations/seeders; and locks after installation. This is a useful foundation, not proof that the complete customer onboarding journey is finished.

The full institution identity wizard, consistent logo/color rendering across all portals and generated documents, two-installation isolation acceptance, signed customer release package and tested upgrade/rollback must be implemented and verified before claiming commercial-ready self-service installation.

The current branding work adds institution identity fields, Media Library image selection, brand colors, installer seeding of the institution name, and shared-layout branding. The resumable first-run setup wizard now guides administrators through institution identity and logo URLs, English/Bangla language, timezone preference, academic-year dates and payment-method setup; progress is saved per step, optional steps can be skipped, and the wizard can be revisited from the dashboard and institution settings. A follow-up adds institution default locale and timezone metadata, with the user's session language taking precedence. Payment receipts and completion certificates use the saved institution name. Currency remains BDT for now: invoices and payments do not yet store currency per record, so a global currency switch is deliberately withheld to avoid relabelling historical financial data. Direct logo upload, email sender/template branding, complete portal coverage, per-record currency support, signed packaging and two-installation/upgrade/backup/restore staging acceptance remain open.
