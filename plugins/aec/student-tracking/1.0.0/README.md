# Student Tracking & Monitoring Plugin

This first-party plugin is a read-only academic overview with audited staff follow-up actions. It reuses existing LMS records; it does not create a second attendance, enrollment, exam or learning-progress source of truth.

## Portal access

- Admin: all students and branches.
- Branch Manager: students and records within the active assigned branch.
- Teacher: students and records in assigned courses/batches only.
- Student: own progress only.
- Guardian: read-only linked-student view only while the guardian link is active and reviewed.

The same access scope is applied to student filters, nested detail queries, dashboard aggregates and CSV exports. Student and guardian views never receive staff risk notes or action history.

## Data shown

- Enrollment and batch/course relationships.
- Attendance and recent learning-progress records.
- Exam attempt outcomes and assignment submission status/score.
- Last recorded LMS learning activity.
- Transparent risk flags for low attendance and learning inactivity.

Assessment and assignment queries select only outcome fields; answers, teacher feedback, private notes, payment secrets, IP addresses and raw user-agent strings are not rendered.

## Risk rules and follow-up

Admins can configure the low-attendance percentage, minimum record count and inactivity days. Flags are review signals only; they do not suspend accounts, alter grades or trigger disciplinary actions.

Admin, Branch Manager and authorized Teacher users can save a follow-up note, dismiss a flag or reopen it. Each action is stored in the plugin's student_tracking_risk_actions history and an ActivityLog event. The ActivityLog description does not copy the note content.

## Export

The CSV export uses the same server-side role scope and selected filters, is capped at 5,000 rows and throttled to 5 requests per minute. Export events are audited. Formula-leading student names are prefixed to prevent spreadsheet formula injection. Email, phone, answers, feedback and private notes are not exported.

## Explicitly not included

- GPS/background location, keystroke capture, device surveillance or unrelated browsing data.
- Automated notification delivery or claims of delivery without a connected provider.
- Overdue-assignment, consecutive-absence and declining-score risk rules (future opt-in work).
- Notice/support engagement records until their permissions and privacy boundaries are verified.

## Release requirements

Run plugin migration 2026_11_17_180000_create_student_tracking_risk_actions.php through the trusted plugin lifecycle. Do not activate an unsigned package. Exact-head CI, CodeQL, cPanel package verification and manual browser QA are required before production activation.
