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
- `GET /my/courses`: authenticated student's active/completed enrollments with lesson completion progress.\n- `GET /my/assignments?status=&per_page=`: published assignments for the authenticated student's active batch enrollments, including only that student's own submission and optional `upcoming`, `overdue`, `submitted`, or `graded` filters.\n- `POST /assignments/{assignment_id}/submission`: submit a text answer (`answer`, max 50,000 characters). Requires an active enrollment and a published, in-deadline assignment (unless late submissions are enabled). First submission returns HTTP 201; duplicate pending submissions and closed assignments return HTTP 409. A graded submission may be revised; the revision clears the prior grade and feedback. File attachment upload is not yet supported by this mobile endpoint.
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
