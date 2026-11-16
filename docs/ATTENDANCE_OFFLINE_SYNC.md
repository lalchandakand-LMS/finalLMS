# Online and Offline Attendance Plugin — Implementation Contract

Status: online web roster, correction audit, scoped reports, authenticated offline sync API, explicit conflict resolution and signed short-lived QR backend are implemented on the feature branch. The encrypted mobile queue/automatic reconnect client, end-to-end device QA and full signed plugin lifecycle remain outstanding.

## Source of truth

The existing `attendances` table and `Attendance` model remain canonical. No parallel attendance table is introduced. Database uniqueness remains one attendance row per batch, student and date.

## Online attendance

- Teacher, admin and an explicitly authorized Branch Manager may mark attendance only within their assigned batch/branch scope.
- Supported statuses: Present, Absent, Late and Excused. Excused is disabled by default and must be enabled by institutional policy (`ATTENDANCE_ALLOW_EXCUSED=true`) before use.
- Bulk roster marking is the normal workflow.
- Any correction must include a reason and create an immutable audit record with actor, old/new status and timestamp.
- Server validates active enrollment and actor scope on every write.

## Mandatory location and privacy

- A foreground location permission prompt is required when attendance is recorded. If the user denies permission, attendance submission is blocked.
- Store latitude, longitude, reported accuracy and capture time with the attendance record; location is used only for attendance integrity.
- Location is captured only at the explicit attendance action. No background collection, continuous GPS, geofencing or covert tracking.
- The mobile client must clearly disclose this requirement and request permission only when the teacher/student performs the attendance action.
- Offline records retain the captured location and capture timestamp until sync; stale/future capture times are rejected by policy.

## Offline-first workflow

- The mobile app downloads only rosters and sessions the signed-in teacher is authorized to manage.
- Pending records are stored locally using encrypted database/storage provided by the platform. Never put bearer tokens in attendance payloads or queue records.
- Each operation includes a random UUID idempotency key, batch/session, student, date, status, location coordinates, accuracy and capture time.
- The authenticated teacher sync endpoint accepts up to 100 operations per request and is rate-limited. Offline records older than the configured retention window (default seven days) are rejected for manual review.
- Server rechecks role, batch assignment, branch, active enrollment and attendance uniqueness at sync time.
- Replaying the same key and same payload returns the original result; reusing a key with a different payload is rejected.
- Results are per operation: synced, duplicate, conflict or failed. Client UI maps these to Pending, Synced, Conflict and Failed.
- Existing server attendance with a different status is returned as conflict and is never silently overwritten. A new explicit resolution operation with reason and audit is required.
- Pending records are provisional until server-accepted. Failed/conflicting rows remain available for safe correction/retry.
- Logout, account switch, device revocation and plugin disable must invalidate local access safely without silently deleting unsynced records.

## QR attendance

- QR check-in must use short-lived (maximum two minutes), signed, session-bound tokens with a unique nonce and replay protection.
- The student must authenticate, have active enrollment in the batch, and provide foreground location at scan time.
- Static reusable QR codes are prohibited. Manual marking remains available.

## Visibility and reports

- Student sees only their own attendance.
- Guardian has read-only access only through an active, student-approved guardian link.
- Reports are scoped server-side by Academic Level, branch, course, batch, date and student. The teacher, Branch Manager and admin report views apply their own server-side scopes.
- Never expose raw device tokens, raw user-agent or unrelated session security metadata.

## Required acceptance tests

- Online bulk marking and correction reason/audit.
- Offline sync, duplicate idempotency, partial success, retry and conflict.
- Stale enrollment, changed teacher assignment, cross-branch and cross-batch IDOR.
- Missing/denied location, invalid coordinates and invalid capture timestamp.
- Invalid/expired/replayed QR token.
- Disabled plugin blocks web/API endpoints while preserving attendance history.
- Student sees only their own records; guardian sees only actively consented linked student records.
- Android/iOS encrypted queue, automatic reconnect sync, Bangla/English UI and cPanel deployment compatibility.
- Exact-head CI and CodeQL plus manual device/browser QA before merge.
