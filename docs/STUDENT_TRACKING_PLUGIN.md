# Student Tracking & Monitoring Plugin

Plugin key: `aec.student-tracking`  
Package: `plugins/aec/student-tracking/1.0.0`

This optional plugin presents existing LMS student records to Admin, Branch Manager, Teacher, Student and Guardian portals. It does not replace attendance, enrollment, course progress, assessment or guardian-consent source records.

## Features

- Student directory with search and branch, batch, course, Academic Level and enrollment-status filters.
- Activity date range for attendance and completed lesson/content counts; the inactivity rule always uses the latest authorized learning activity across all dates.
- Active enrollment counts, attendance today, attendance percentage, completed lesson/content progress and last learning activity.
- Per-student detail view with scoped enrollments, attendance, legacy lesson progress, Course Content Studio progress, exam attempts and assignment submission outcomes.
- CSV export with the same role/resource scope, selected filters, a 5,000-row cap, 5 requests/minute throttle, private/no-store response headers and an audit event. Spreadsheet formula-leading student names are escaped.
- Admin-configurable risk thresholds: attendance percentage, minimum record count and learning inactivity days. Changes are validated and committed atomically with their audit event.
- Staff follow-up notes, dismissal and reopening for each risk type. Risk actions are recorded in a dedicated plugin-owned table and mirrored in the LMS activity audit log. Dismissal suppresses only the matching risk code; it never alters source academic records.
- Explicit reasons accompany active risk indicators. These are review signals only and must not be used as the sole basis for sanctions, exclusion or fee decisions.

## Access isolation

Every authenticated route is registered through `PluginContext::routesForPortal()` and requires the plugin's declared permission, active plugin status, authentication, verified email and role.

- Admin: all students, subject to optional filters.
- Branch Manager: students and related records in the manager's authorized branch.
- Teacher: students and records belonging to courses/batches assigned to that teacher.
- Guardian: only students with an approved active guardian relationship; risk action notes/history are not exposed.
- Student: only their own tracking record; no staff risk actions.

Enrollment counts, attendance, lesson/content progress, exam attempts, assignment submissions, risk actions and CSV rows are scoped server-side. Request filters cannot broaden a user's authorized scope.

## Data sources and owned data

The plugin reads existing `attendances`, `enrollments`, `lesson_progress`, `course_content_progress`, `exam_attempts`, `assignment_submissions`, guardian links and core user/course/batch records.

The only plugin-owned table is `student_tracking_risk_actions`, created from the signed package migration directory declared in `plugin.json`. It stores staff follow-up notes and dismiss/reopen decisions, not copied academic records. The migration uses restrictive foreign keys to prevent silently deleting the audit trail.

Deactivating the plugin leaves all source academic data and its risk-action audit history intact. Migration rollback/uninstallation must follow the platform's explicit plugin lifecycle and data-retention policy.

## Privacy boundary

The plugin does **not** collect GPS coordinates, live location, microphone/camera data, keystrokes, background surveillance signals or new analytics cookies. It reports only LMS attendance and learning records already held by the platform.

## Release requirements

The source package is not active merely by being present under `plugins/`. It must pass exact-head Laravel CI, CodeQL, signed package creation and verification, migration preflight, browser acceptance and role-isolation review. It must then be uploaded through Plugin Manager, receive explicit permission grants and be explicitly activated. The repository does not contain a release private key; never commit one.
