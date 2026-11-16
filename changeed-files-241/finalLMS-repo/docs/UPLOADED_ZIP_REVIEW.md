# Uploaded ZIP Review — 2026-11-10

## Scope and identity check

Reviewed the user-supplied `finallms.zip` archive (70 entries, 435,694 bytes). The archive is **not the same application source tree** as this repository's default branch.

Evidence:
- Archive root contains `server.js`, `package.json`, EJS templates, `lang/*.json`, and a Node/Express startup script.
- Repository default branch contains Laravel 12 source: `composer.json`, `artisan`, `app/`, `routes/web.php`, and PHP 8.3 requirements.
- The archive README claims Laravel 12, but its `package.json` starts `node server.js`; its CI workflow expects Composer, Artisan, Laravel directories and PHPUnit. This is an internal packaging/source mismatch.
- The archive has no `composer.json`, `artisan`, Laravel `app/`, `routes/`, migrations, or PHPUnit suite.

## Checks performed

- `node --check server.js`: passed (syntax only; not functional or security validation).
- Archive inventory: 70 entries.
- Static review of authentication, session configuration, data persistence, routes, and deployment notes.

## Critical blockers — do not deploy or merge this archive

1. **Authentication bypass:** the login handler accepts the literal password `password` for any existing user, regardless of the stored password.
2. **Hard-coded session secret:** Express session signing uses a committed literal secret rather than a required environment variable.
3. **Non-production session store:** `express-session` uses its default in-memory store. Sessions are lost on restart and do not scale across instances.
4. **In-memory application database:** users, courses, enrollments, payments and other records are held in a JavaScript object. Data is not durable and is not shared across workers.
5. **Plaintext demo credentials:** seeded users store the password `password` directly; registration also stores submitted passwords directly in the in-memory user record. The declared `bcryptjs` dependency is not used for this authentication path.
6. **Token weaknesses:** iframe tokens are generated with `Math.random()`, held in process memory, and login places the token in the redirect URL. Tokens in URLs can leak through browser history, logs and referrer flows.
7. **CSRF protection not evidenced:** state-changing form routes do not show a CSRF middleware/token implementation in the reviewed server setup.
8. **No durable database integration:** no MySQL/SQLite/PostgreSQL client or ORM dependency is declared in the archive package manifest.
9. **Install/deployment contradiction:** EJS installer copy refers to MySQL/cPanel, while the actual server has no database driver or durable installation flow.

## Required remediation before considering any migration

- Do not copy this Node prototype over the Laravel application or production files.
- Treat the archive as an isolated UI/prototype reference only.
- If retaining Node, replace the in-memory store with a reviewed relational schema and migrations; use bcrypt/Argon2 password hashes; remove the universal password bypass; load a high-entropy session secret from environment; use a persistent session store; use cryptographically secure, server-side revocable tokens and never place them in query strings; add CSRF protection, security headers, login throttling, validation, and authorization tests.
- Prefer consolidating approved features into this repository's Laravel 12 architecture rather than maintaining two competing runtimes.
- Add automated regression tests for login bypass, session persistence, role/branch authorization, lesson access, payment verification and data durability.
- Validate the final cPanel package on an isolated staging domain and database before any production release.

## Current disposition

- No archive code was imported.
- No production files, protected `main`, branch protection, or deployment were changed.
- This audit note is committed on the isolated branch `audit/uploaded-zip-review-2026-11-10` for review.
