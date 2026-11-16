# Online and Offline Attendance Plugin — Implementation Contract

Status: design/implementation scope only. The current Attendance module remains metadata-only until the complete signed plugin lifecycle and acceptance tests are implemented.

## Existing source of truth

The current `attendances` records and `Attendance` model are the canonical attendance source. Online marking already supports present, absent and late for active batch enrollments. Do not create a second attendance table or a client-owned source of truth.

## Online workflow

1. An authorized teacher opens an assigned batch and selects a class date/session.
2. The server returns the active roster within the caller's branch and batch scope.
3. The teacher submits attendance in bulk.
4. The server validates each student is currently enrolled and the teacher is assigned to the batch (or caller has an explicit administrative permission).
5. Updates are audited with actor, timestamp, previous value, new value and a required reason for corrections.

## Offline-first workflow

- Client downloads only the roster and sessions the signed-in teacher is authorized to manage.
- Pending attendance is stored locally by the mobile client; use platform secure storage/encrypted local database where supported. Never persist bearer tokens in queued attendance records.
- Each queued operation carries a random idempotency key, batch/session identifier, student identifier, intended status, client-created timestamp and schema version.
- On reconnect, the client submits a bounded batch to an authenticated, throttled API endpoint.
- The server rechecks current role, batch assignment, branch, enrollment, date policy and plugin-active status at sync time. Client timestamps are informational only.
- The server returns a result for every operation: accepted, duplicate/already-applied, conflict, rejected or retryable failure.
- Replaying the same idempotency key must not create another attendance record.
- A conflicting newer server record is never silently overwritten. An authorized user resolves it with a reason; the resolution is audited.
- Pending local records remain visibly provisional until accepted by the server. Failed/conflicting rows remain available for correction and safe retry.
- Queue data is cleared or invalidated on logout, account switch, device revocation, expiry or plugin disable. Expiry must not silently delete unsynced records without warning/export/recovery UX.

## Optional QR check-in

If implemented, use short-lived, server-signed, session-bound tokens with nonce/replay protection, rate limits and server-side enrollment checks. Static reusable QR codes are not acceptable. Manual attendance remains available.

## Privacy and safety

- Geofencing is disabled by default and is not required for offline attendance.
- No covert GPS, background location tracking, keystroke collection or unrelated device surveillance.
- Guardian access is read-only and requires an active student-approved guardian link.
- Do not expose device tokens, raw user-agent, IP address or unrelated session security metadata in attendance screens.

## Required acceptance tests

- Online batch marking and correction reason/audit.
- Offline queue sync, duplicate idempotency, partial batch success and retry.
- Server/client conflict and explicit resolution.
- Stale enrollment, changed teacher assignment, cross-branch and cross-batch IDOR.
- Invalid/expired/replayed QR token.
- Disabled plugin blocks web and API endpoints while preserving attendance history.
- Student sees only their own records; guardian sees only actively consented linked student records.
- Android/iOS client contract, cPanel deployment compatibility, Bangla/English UI and full CI/CodeQL.
