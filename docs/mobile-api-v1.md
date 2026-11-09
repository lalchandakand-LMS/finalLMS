# Mobile API v1 — initial contract

Base path: `/api/v1`

All responses are JSON. Protected endpoints require `Authorization: Bearer <access_token>`. Tokens are opaque random values; only SHA-256 hashes are stored in the database. A device token expires after 30 days and can be revoked independently or all at once.

## Authentication

### POST `/auth/login`
Body:
- `email` (required)
- `password` (required)
- `device_name` (required, 2–100 characters)

Returns `access_token`, `token_type`, `expires_at`, and a minimal user profile. Only `student` and `teacher` roles can sign in to these apps.

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
- `GET /courses/{course_slug}/lessons/{lesson_id}`: lesson content only for an enrolled student or a preview lesson.
- `POST /courses/{course_slug}/lessons/{lesson_id}/complete`: mark an owned lesson complete; requires an active enrollment and is idempotent.

## Instructor endpoints

- `GET /instructor/courses`: courses assigned to the authenticated instructor.
- `GET /instructor/courses/{course_slug}/students`: active/completed students in that instructor's own course only.

Course ownership is checked server-side. The API does not accept a client-supplied instructor or student ID to select another user's records.

## Security and implementation notes

- Login is rate-limited to 5 requests/minute per Laravel throttle key.
- Catalog routes are rate-limited to 60 requests/minute.
- Authenticated API routes are rate-limited to 120 requests/minute.
- Token hashes, not bearer values, are persisted.
- Expired and revoked tokens are rejected.
- Student and instructor role boundaries are enforced on the server.
- This is the initial mobile API foundation. Enrollment purchase, cart/coupons, reviews, quizzes, assignments, live-class operations, notifications, and the complete Flutter user interfaces are separate remaining deliverables.
