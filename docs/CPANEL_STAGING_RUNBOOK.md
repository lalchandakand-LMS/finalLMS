# cPanel staging acceptance and release runbook

This runbook is for validating an Alom's English Center LMS release on a **separate staging site** before any production deployment. It does not authorize or perform a production deployment.

## 1. Staging prerequisites

- Create a staging subdomain (for example, `staging.example.com`) with HTTPS enabled.
- Set its document root to the release's `public` directory. The Laravel application root, `.env`, `vendor`, and `storage` must remain outside the public document root.
- Create a **new, empty** MySQL/MariaDB database and a dedicated database user with privileges only on that database.
- Confirm PHP 8.3 and the required extensions: `bcmath`, `ctype`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, and `xml`.
- Confirm writable application-root, `storage`, and `bootstrap/cache` paths for the installer.
- Keep staging credentials separate from production credentials. Do not paste passwords, API secrets, private keys, or the contents of `.env` into GitHub, issues, or chat.

If the hosting plan cannot create a staging subdomain or separate database, stop here. Do not use the live database as a substitute.

## 2. Obtain and verify the release artifact

1. Open the successful **Laravel CI** workflow run for the exact commit intended for release.
2. Confirm the test and package jobs both succeeded on that same commit.
3. Download the `AlomsEnglishCenter-cpanel` artifact from that run.
4. Keep the artifact and commit SHA together in the release record.
5. Do not deploy an artifact from an older run or a run whose package job failed.

The CI package job verifies ZIP integrity, required Laravel entry points, production dependencies, and absence of the root `.env`, local SQLite database, logs, and Git metadata. A successful package job is not a substitute for a live staging test.

## 3. Fresh staging installation

1. Back up any existing staging files and database before replacing a staging release.
2. Extract the ZIP so the application root is private and the web document root points only to its `public` directory.
3. Visit `https://staging.example.com/install`.
4. Enter the staging URL, staging database credentials, and a unique administrator account with a strong password.
5. Confirm installation completes and redirects to login.
6. Confirm `/install` now returns 404 and the application can sign in.
7. Confirm the environment file is private and `APP_ENV=production`, `APP_DEBUG=false`, and `APP_INSTALLED=true`.

The installer must be used only with a fresh empty database. It is not an upgrade tool and must not be pointed at the production database or an existing LMS database.

## 4. Staging acceptance tests

Record pass/fail evidence for each item. Use synthetic student data only.

### Access and learning
- [ ] Public home page, course catalog, language switch, and news pages load over HTTPS.
- [ ] Admin, teacher, and student accounts can sign in and see only their permitted areas.
- [ ] A student with an active enrollment can open course lessons and published assignments.
- [ ] A student without active enrollment cannot open protected lessons, assignments, or another student's submission.
- [ ] Teacher access is limited to their own batches, assignments, question bank, and grading actions.
- [ ] Exam start, randomized question order, submission, and result display work.

### Files and operations
- [ ] Assignment PDF upload succeeds; unsupported and oversized files are rejected.
- [ ] Submission attachments are not directly web-accessible and unauthorized users receive 403/404.
- [ ] Admin backup can be created and downloaded through authenticated routes.
- [ ] Restore is tested only on staging, using an approved backup, explicit `RESTORE` confirmation, and the administrator's current password.
- [ ] Restored database records and uploaded media/private assignment files are checked.

### Payments and messaging
- [ ] Manual bKash/Nagad payment records work as configured.
- [ ] bKash automated checkout remains disabled until official merchant credentials, callback registration, and sandbox acceptance are complete.
- [ ] If bKash sandbox is enabled, verify a completed transaction server-side before confirming enrollment; test cancellation, mismatch, duplicate callback, and failed verification.
- [ ] Keep Nagad direct API checkout disabled until the official merchant integration guide, credentials, and sandbox details are available.
- [ ] Do not enable live payment credentials during staging acceptance.

### Production safeguards
- [ ] `APP_DEBUG=false`; no stack traces or secrets are exposed to unauthenticated visitors.
- [ ] HTTPS and secure session behavior are confirmed.
- [ ] Queue/cron behavior is checked if SMS or queued jobs are enabled.
- [ ] A backup is copied to a separate, access-controlled location; a second copy on the same hosting account is not off-site protection.
- [ ] Restore rehearsal completes successfully and the recovery steps are recorded.

## 5. Production release gate

Production deployment is allowed only after all applicable staging checks pass, the release owner approves the exact commit/artifact, and a fresh production backup has been verified.

Before release:
- Record the current production commit/artifact and database backup timestamp.
- Confirm a tested rollback package and database recovery procedure are available.
- Schedule a maintenance window and pause enrollment/payment activity if the release requires it.
- Apply only the documented upgrade/migration procedure; never run the fresh installer against an existing installation.
- Verify login, public pages, student learning, payment records, and application logs immediately after release.

## 6. Rollback

If staging or production smoke tests fail:
1. Stop further release steps; do not repeatedly rerun migrations blindly.
2. Preserve the failing release logs and exact commit/artifact identifier.
3. Restore the previous application files from the verified release backup.
4. Restore the matching database backup only when the schema/data state requires it and the recovery plan has been approved.
5. Recheck login, enrollment, lessons, payment records, and logs before reopening access.

A file-only rollback may be unsafe after incompatible database migrations. The application and database recovery points must be treated as a matched pair.

## 7. Current project limitations

- No staging subdomain or separate staging database has yet been provisioned for this project.
- A real cPanel installation and end-to-end staging acceptance have therefore **not** been verified by this repository documentation or by CI.
- Automated off-site backup scheduling and a disaster-recovery rehearsal remain operational work.
- bKash live merchant activation, Nagad direct API integration, and SSLCommerz integration must not be represented as completed.
