# Student Tracking & Monitoring Plugin

Plugin key: `aec.student-tracking`  
Package: `plugins/aec/student-tracking/1.0.0`

This optional first-party plugin presents existing LMS student records to the five supported portals. It does not replace attendance, enrollment, course progress, assessment or guardian-consent source records.

## Portal access

- Admin: all students and branches, with optional filters.
- Branch Manager: students and related records in the manager's authorized branch.
- Teacher: students and records belonging to assigned courses/batches only.
- Student: only their own tracking record.
- Guardian: only students with an approved active guardian relationship; risk notes and staff action history are never exposed.

Every portal route is registered through `PluginContext::routesForPortal()`. Enrollment counts, attendance, lesson/content progress, exam attempts, assignment submissions, risk actions and CSV rows are scoped server-side. Request filters cannot broaden the actor's authorized scope.

## Data shown

- Search and branch, batch, course, Academic Level and enrollment-status filters.
- Activity date range for attendance and completed lesson/content counts; inactivity uses the latest authorized learning activity across all dates.
- Attendance today, attendance percentage, active enrollments, completed learning and last recorded activity.
- Scoped course enrollments, attendance, legacy lesson progress, Course Content Studio progress, exam outcomes and assignment submission status/scores.
- Student-targeted active notice-board announcements and an Admin-only support-ticket timeline. Private support message bodies are not copied or rendered.

Assessment and assignment queries select only outcome fields; answers, teacher feedback, private notes, payment secrets, IP addresses and raw user-agent strings are not exposed.

## Risk rules and follow-up

Admins with the explicitly granted `student-tracking.configure` permission can configure the low-attendance percentage, minimum attendance record count and inactivity days.

Transparent review signals include:
- Low attendance against the configured threshold.
- Learning inactivity against the configured day threshold.
- Published assignments overdue without a submission.
- The latest three recorded attendance sessions all marked absent.
- A decline of at least 20 percentage points across the latest three normalized graded outcomes.

These signals are review prompts only. They do not suspend accounts, alter grades or trigger disciplinary actions and must not be used as the sole basis for sanctions, exclusion or fee decisions.

Admin, Branch Manager and Teacher users with the explicitly granted `student-tracking.manage-risk` permission can save follow-up notes, dismiss a flag or reopen it. Each action is stored in the plugin-owned `student_tracking_risk_actions` table and an ActivityLog event. Dismissal suppresses only the matching risk code; it never changes source academic records. Student and Guardian views do not receive staff notes or action history.

## Export

CSV export applies the same server-side role scope and selected filters, is capped at 5,000 rows and throttled to 5 requests per minute. Export events are audited. Formula-leading student names are prefixed to prevent spreadsheet formula injection. Email, phone, answers, feedback and private notes are not exported.

## Data and privacy boundary

The plugin reads existing LMS attendance, enrollment, lesson/content progress, exam, assignment, guardian-link, notice and support-ticket records. Its only plugin-owned table is `student_tracking_risk_actions`, created by the migration in this package. Restrictive foreign keys preserve the audit history.

The plugin does not collect GPS coordinates, live/background location, microphone/camera data, keystrokes, unrelated browsing data or new analytics cookies. It does not claim to deliver notifications without a connected provider.

Deactivation leaves source academic data and risk-action history intact. Migration rollback/uninstallation must follow the platform's explicit plugin lifecycle and data-retention policy.

## Release requirements

The source package is not active merely by being present under `plugins/`. Exact-head Laravel CI, CodeQL, signed package creation/verification, migration preflight, disposable activation/deactivation, rollback rehearsal, browser acceptance and role-isolation review are required. Upload through Plugin Manager, grant permissions explicitly, and activate only after release gates pass. Never commit a release private key.
