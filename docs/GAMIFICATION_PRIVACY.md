# XP, streaks, badges and privacy-aware leaderboards

## Design rules

- XP is an append-only ledger. Award values are server-owned constants; clients cannot submit an XP amount.
- Every event is idempotent by learner + event key + source type + source record ID. A repeated request cannot award duplicate points.
- Award only after the caller has independently verified the underlying lesson, quiz, course, attendance or SCORM completion record.
- Daily streaks count distinct calendar dates, not page views or multiple activities on one day. The application must configure its intended learner timezone consistently.
- Badges are unique per learner and badge. Award them from verified milestones, not client-side claims.
- Leaderboards are opt-in by default. Public output contains only a learner-selected display name, XP and optionally streak; never email, phone, student ID, branch ID or full profile.
- Branch-scoped leaderboards must be authorized by the caller. Do not accept an arbitrary branch scope from an untrusted student request.
- Provide opt-out and display-name controls in the learner profile. Respect account deletion and data-retention policy.

## Initial XP policy

| Verified activity | XP |
|---|---:|
| Lesson completed | 10 |
| Quiz passed | 20 |
| Course completed | 100 |
| Attendance verified | 5 |
| SCORM lesson completed | 10 |

XP values should be configurable by an authorized administrator in a future settings screen. Do not award points for opening a lesson, refreshing a page, or unverified progress events.

## Integration requirements

Call `GamificationService::award()` only from server-side completion handlers after source ownership, enrollment, status and score checks. The service intentionally does not expose a public award endpoint. Use a transaction and the unique idempotency key for concurrency safety.

Run migrations and add feature tests for replayed events, concurrent awards, streak day boundaries, opt-out privacy, branch scoping, and unauthorized learner access before enabling the UI.
