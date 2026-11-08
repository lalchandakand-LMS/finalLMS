# Community, Communication & AI Platform — delivery plan

This feature set is developed as a separate, reviewable branch. It is not enabled on the live site by this change.

## Product modules

1. **For You / Community feed**
   - Posts from teachers and learners, chronological fallback, followed-teacher boost, likes, comments, optional attachments, report/moderation queue.
   - Feed visibility is enforced server-side; private/branch/course-only content must never leak into public results.
2. **Teacher follow**
   - Students may follow active teacher accounts; self-follow and non-teacher targets are rejected.
   - Follow/unfollow must be idempotent and protected by authentication and throttling.
3. **Discussion club rooms**
   - Public, branch-only, course/enrollment-only, and invite-only room policies.
   - Membership checks on every room view, history request, and message write. Admin moderation and owner controls.
4. **Live messaging**
   - Authenticated one-to-one conversations and room chat.
   - First deploy may use short polling; production real-time transport (Laravel Reverb/WebSockets) is a separate infrastructure decision. Do not label polling as WebSocket live chat.
   - Rate limits, block/report controls, read receipts, pagination, and retention controls.
5. **AI Support & auto-diagnostics**
   - Ticket intake, category/priority classification, sanitized error-log assistance, suggested troubleshooting, escalation and human hand-off.
   - Read-only by default. AI may not run shell/SQL, edit code, change roles, access payment secrets, or mutate production data.
   - Any future repair action must create a bounded patch, run tests, show a diff, and require explicit administrator approval.
6. **Universal plugin system**
   - Manifest registry, version/dependency checks, capability permissions, admin approval, enable/disable and audit log.
   - Plugins are metadata-only until a signed package installer and sandbox/security review exist. Never eval/require arbitrary uploaded PHP.
7. **Custom fonts**
   - Separate Bangla and English font family settings; admin upload of WOFF2/WOFF/TTF only, MIME verification, size limits, safe generated CSS, and fallback stack.
   - Only same-origin uploaded font files are used; no remote font URL injection.

## Initial database foundation

The companion migration adds normalized tables for posts, likes, comments, teacher follows, discussion rooms/members/messages, direct messages, support tickets, and a metadata-only plugin registry. It deliberately does not enable routes, public uploads, AI provider calls, or plugin execution.

## Required staged delivery

- Phase A: schema + policies + automated authorization tests.
- Phase B: feed, teacher follow, comments/likes, moderation.
- Phase C: discussion rooms and direct messaging with access-control tests.
- Phase D: support tickets + read-only AI diagnosis with secret redaction and SSRF-safe provider allow-list.
- Phase E: plugin manifest/permissions/approval UI; no arbitrary code execution.
- Phase F: font upload/settings/preview for Bangla and English.
- Phase G: load, abuse, privacy, backup/restore, mobile, and cPanel acceptance testing.

## Release gates

No production migration until staging backup/restore has been proven, the migration has passed CI, authorization tests cover every role/branch boundary, and the admin has reviewed the release. No production deployment is part of this PR.
