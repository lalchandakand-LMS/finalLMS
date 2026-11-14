# Mobile API v1 — contract

Base path: `/api/v1`

All responses are JSON. Protected endpoints require `Authorization: Bearer <access_token>`. Tokens are opaque random values; only SHA-256 hashes are stored. A token expires after 30 days and can be revoked independently or all at once.

## Authentication and student device approval

Mobile clients must generate a cryptographically random 32-byte installation identifier, encode it as 64 lowercase hexadecimal characters, and keep it in the platform secure store (Android Keystore / iOS Keychain). Send the same identifier on later logins from that installation. Do not use an advertising ID, IMEI, phone number, or a user-editable device label as the identifier.

### POST `/auth/login`

Body:
- `email`, `password`
- `device_name` (2–100 characters)
- `device_id` (required for students; 64 lowercase hexadecimal characters; omitted for teachers)

Teachers with valid credentials receive a bearer token as before and do not need a device ID. Student accounts must send the secure installation ID and have a registered mobile number:
- A previously approved installation receives a device-bound token.
- A new, unknown, or revoked installation receives HTTP 202 with `status=phone_verification_required`, a one-time `challenge_token`, and `expires_in=600`. No API bearer token is issued at this stage. A six-digit OTP is sent to the phone number registered by the academy.
- If SMS is not configured or sending fails, the endpoint returns HTTP 503 and issues no token.

### POST `/auth/verify-device`

Body: `challenge_token`, `code` (six digits).

- First-ever student installation: successful OTP verification approves the device and returns the bearer token.
- Additional or previously approved/revoked installation: successful OTP verification returns HTTP 202 with `status=pending_admin_approval`. It does not issue a token. The request must be approved in Admin → Student Devices.
- Invalid/expired challenge: HTTP 410. Invalid OTP: HTTP 422. Too many attempts: HTTP 429.

### POST `/auth/device-status`

Body: `challenge_token`.

After an additional device has passed OTP, poll this endpoint (suggested interval: 10–20 seconds) until Admin approval:
- Pending: HTTP 202 and `status=pending_admin_approval`.
- Approved: HTTP 200 with the device-bound bearer token.
- Rejected/revoked: HTTP 403.
- Expired challenge: HTTP 410.

The challenge token expires after 24 hours and is single-use once a bearer token is issued. Never log or expose it in analytics or crash reports.

### GET `/auth/me`

Returns the authenticated user profile.

### POST `/auth/logout`

Revokes the current device token.

### POST `/auth/logout-all`

Revokes every active mobile token for the authenticated user.

## Student endpoints

- `GET /courses?q=&sort=&per_page=`: published course catalog with pagination. Sort values: `newest`, `oldest`, `price_low`, `price_high`, `title`.
- `GET /courses/{course_slug}`: course details and curriculum; non-enrolled users only receive preview lessons.
- `GET /my/courses`: authenticated student's active/completed enrollments with lesson completion progress.
- `GET /my/assignments?status=&per_page=`: published assignments from the authenticated student's active batch enrollments, including only that student's own submission. Optional status filters: `upcoming`, `overdue`, `submitted`, `graded`; page size is limited to 50.
- `GET /assignments/{assignment_id}`: details for a published assignment in one of the authenticated student's active batches, with only that student's submission.
- `POST /assignments/{assignment_id}/submit`: submit a text answer (`answer`, maximum 50,000 characters). Requires active enrollment and a published assignment; late submissions are accepted only when enabled. Pending submissions cannot be duplicated. A graded submission may be revised, which clears its previous score and feedback. Closed assignments return HTTP 409. File attachments are not yet supported by this mobile endpoint.
- `GET /my/wishlist`: authenticated student's own saved, published courses with pagination.
- `POST /courses/{course_slug}/wishlist`: add a published course to the student's wishlist; idempotent.
- `DELETE /courses/{course_slug}/wishlist`: remove a course from the student's own wishlist; idempotent.
- `GET /courses/{course_slug}/lessons/{lesson_id}`: lesson content only for an enrolled student or a preview lesson. When a private uploaded video exists, the response includes a relative `video_stream_path`; it never exposes the storage path.
- `GET /courses/{course_slug}/lessons/{lesson_id}/video`: authenticated, entitlement-checked private video stream. Preview lessons are available to signed-in students; full lessons require an active/completed enrollment. The owning instructor may stream their own course lessons. Supports HTTP byte ranges for seeking, uses private/no-store and nosniff headers, and reads only from the private `lesson_videos` disk.
- `POST /courses/{course_slug}/lessons/{lesson_id}/complete`: mark an owned lesson complete; requires an active enrollment and is idempotent.

Wishlist routes are student-only. Course visibility and user ownership are enforced server-side; the API never accepts a client-supplied user ID for wishlist operations.

## Instructor endpoints

- `GET /instructor/courses`: courses assigned to the authenticated instructor.
- `GET /instructor/courses/{course_slug}/students`: active/completed students in that instructor's own course only.

Course ownership is checked server-side. The API does not accept a client-supplied instructor or student ID to select another user's records.

- `GET /instructor/live-classes?batch_id=&status=&per_page=`: paginated live classes belonging only to the authenticated instructor's batches. Optional status values: `scheduled`, `live`, `completed`, `cancelled`; page size is limited to 50.
- `POST /instructor/batches/{batch_id}/live-classes`: schedule a session in an owned batch. Requires title, HTTPS meeting URL, future start time and an end time after the start.
- `PATCH /instructor/live-classes/{live_class_id}`: update an owned session's details/status or publish/remove a recording. Recording links are restricted to YouTube/Vimeo HTTPS URLs; adding a recording publishes it immediately to the existing authorized recording experience.

## Security and implementation notes

- Login and OTP verification are rate-limited to 5 requests/minute; device-status polling is limited to 30 requests/minute.
- Catalog routes are rate-limited to 60 requests/minute; authenticated API routes are rate-limited to 120 requests/minute.
- Student API tokens must reference a currently approved `student_devices` record owned by that student. Legacy student tokens without an approved device binding are rejected and revoked on use.
- Admin approval of a new device revokes the student's previous approved device and all existing mobile bearer tokens. Admin revocation also invalidates tokens bound to that device.
- Student web sessions and mobile API tokens share the same approved-device registry, enforcing one active student device across the web and app. A revoked device's web session is ended on its next protected web request; its mobile token is rejected on its next API request.
- Teacher API login remains password-based and is not subject to the student SMS device policy.
- Token hashes, not bearer values, are persisted. Expired and revoked tokens are rejected.
- The Student Flutter app now implements secure installation-ID and bearer-token storage, OTP entry, Admin-approval waiting UI, 15-second approval polling, session restoration and server-side logout. Flutter analyze/tests must pass for each release; real SMS and end-to-end approval still require isolated staging acceptance.
- Enrollment purchase/checkout, cart/coupons, reviews, course quizzes, live-class operations, notices, support and instructor teaching workflows remain separate deliverables. Mobile exam and text-assignment screens are implemented; assignment attachment upload remains pending.


## Student assignment endpoints

- `GET /my/assignments`: paginated assignments for the authenticated student's active batch enrollments, including that student's own submission summary.
- `GET /assignments/{assignment}`: assignment details and the authenticated student's own submission only.
- `POST /assignments/{assignment}/submit`: submit or revise a text answer under the assignment's publication, enrollment, due-date, late-policy and grading rules. A pending submission cannot be duplicated; a graded submission may be revised according to the server policy.

The mobile assignment screen supports text answers and private file attachments. Files are stored on the private local disk, filenames are sanitized, rejected/duplicate uploads are cleaned up, and download access is checked against submission ownership and active enrollment.

## Student exam endpoints

- `GET /my/exams`: paginated published exams in the authenticated student's active batch enrollments.
- `GET /exams/{exam}`: exam details, the student's own attempt, server deadline and submitted result.
- `POST /exams/{exam}/start`: start or resume the single attempt and return the frozen randomized question set.
- `PUT /exam-attempts/{attempt}/answers`: save selected option indexes for questions assigned to the attempt.
- `POST /exam-attempts/{attempt}/submit`: submit answers and receive the server-calculated score and pass status.

The mobile exam UI displays the server deadline, saves selected answers, and submits the attempt. Correct option indexes are never returned before submission. Student role, active enrollment, attempt ownership, frozen question membership, attempt deadline and scoring are enforced by the server.


## Instructor endpoints

- `GET /instructor/batches`: paginated batches assigned to the authenticated instructor, including course title and assignment count.
- `GET /instructor/assignments`: paginated assignments created for the authenticated instructor, with batch/course labels and submission counts.
- `POST /instructor/batches/{batch_id}/assignments`: publish an assignment to an owned batch. Requires title, instructions, future `due_at`, and `max_marks`; optional `allow_late`.
- `GET /instructor/assignments/{assignment_id}/submissions`: list submissions and student identity for an assignment owned by the authenticated instructor.
- `PATCH /instructor/assignments/{assignment_id}/submissions/{submission_id}`: grade or re-grade a submission with a score bounded by the assignment's maximum marks and optional feedback.
- `GET /instructor/courses`: courses assigned to the authenticated instructor.
- `GET /instructor/courses/{course_slug}/students`: active/completed students in that instructor's own course only.

Course ownership is checked server-side. The API does not accept a client-supplied instructor or student ID to select another user's records.

## Security and implementation notes

- Login and OTP verification are rate-limited to 5 requests/minute; device-status polling is limited to 30 requests/minute.
- Catalog routes are rate-limited to 60 requests/minute; authenticated API routes are rate-limited to 120 requests/minute.
- Student API tokens must reference a currently approved `student_devices` record owned by that student. Legacy student tokens without an approved device binding are rejected and revoked on use.
- Admin approval of a new device revokes the student's previous approved device and all existing mobile bearer tokens. Admin revocation also invalidates tokens bound to that device.
- Student web sessions and mobile API tokens share the same approved-device registry, enforcing one active student device across the web and app. A revoked device's web session is ended on its next protected web request; its mobile token is rejected on its next API request.
- Teacher API login remains password-based and is not subject to the student SMS device policy.
- Token hashes, not bearer values, are persisted. Expired and revoked tokens are rejected.
- The Student Flutter app now implements secure installation-ID and bearer-token storage, OTP entry, Admin-approval waiting UI, 15-second approval polling, session restoration and server-side logout. Flutter analyze/tests must pass for each release; real SMS and end-to-end approval still require isolated staging acceptance.
- Enrollment purchase, cart/coupons, reviews, quizzes, assignments, live-class operations, notifications, and complete Flutter user interfaces are separate remaining deliverables.
