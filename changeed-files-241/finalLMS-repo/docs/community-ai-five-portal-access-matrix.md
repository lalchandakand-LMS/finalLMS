# Community, Messaging & AI Support — Five-Portal Access Contract

This document is the security contract for integrating the Community, Messaging and Support foundations into the current `main` branch. It is intentionally stricter than the legacy PR #36 prototype. The feature must not be exposed until the implementation and feature tests satisfy every rule below.

## Required portals

The LMS has exactly five primary user portals:

- **Admin** — institution-wide moderation, support assignment, configuration and audit.
- **Branch** — branch-scoped operational access only; a branch user must never see another branch's learners, staff, posts, rooms, conversations or tickets.
- **Teacher** — teaching workspace and approved class/community participation; access to learner information is limited to assigned batches/courses.
- **Student** — personal learning and approved community participation.
- **Guardian** — read-only access to explicitly linked learner summaries; no impersonation, private messages, posting, following or room participation unless a separate explicit policy is approved.

Existing internal roles such as accountant must retain their current capabilities but must not be silently promoted into one of these five portals.

## Authorization invariants

1. **Default deny:** every controller action checks authorization on the server. Hiding a link in Blade is not authorization.
2. **Branch isolation:** derive the actor's branch from the authenticated account. Never accept a branch identifier from request input as authority. Query and mutation scopes must be applied before retrieving records.
3. **Guardian relationship:** Guardian access is limited to a verified guardian-to-student relationship stored in the LMS. Do not infer a relationship from matching names, phone numbers or email addresses. If the relationship model/table is absent, guardian data access stays disabled.
4. **Student privacy:** teachers may see only students enrolled in their assigned batches/courses; guardians only their linked students; branch users only their own branch.
5. **Direct messages:** both sender and recipient must be authorized portal users. Branch-scoped participants must share a branch unless an institution-wide admin policy explicitly permits otherwise. Guardians cannot read a child's direct messages.
6. **Discussion rooms:** every index, show, join and message action checks room visibility, branch, course/batch membership and explicit invitation membership. Private rooms are never joinable by guessing a URL.
7. **Community feed:** branch-only and course-only posts must be filtered in the database query before pagination. Public posts may be visible across branches; private content must not leak through counts, comments, search or related-user lists.
8. **Support tickets:** a requester sees only their own tickets. Branch staff see only tickets from their branch. Admin sees institution-wide tickets. Guardian tickets must be associated with a linked student only when submitted explicitly as a guardian support request.
9. **AI support:** initial diagnosis is deterministic/read-only. Do not send passwords, OTPs, payment secrets, full raw logs, session tokens or personal student data to an external model. No SQL, shell, deployment, payment, role or data mutation tools.
10. **Plugins:** registry metadata only. No arbitrary PHP execution, `eval`, dynamic `require`, or uploaded executable code.
11. **Abuse controls:** validate and trim content, rate-limit write endpoints, escape rendered user content, and log moderation/security actions without logging secrets.
12. **Accessibility and language:** all portal navigation and user-facing Community/Support labels must work in Bangla and English and remain keyboard/mobile accessible.

## Required role/action matrix

| Capability | Admin | Branch | Teacher | Student | Guardian |
|---|---|---|---|---|---|
| View public community posts | Yes | Yes | Yes | Yes | Read-only |
| Publish/comment/like | Moderate + publish | Own branch policy | Yes | Yes | No |
| Follow teachers | Manage | No | No | Yes | No |
| Create discussion rooms | Yes | Branch-only, if granted | Assigned groups | No | No |
| Join public room | Yes | Yes | Yes | Yes | No |
| Join branch/course/private room | Moderation override audited | Same branch + grant | Assigned course/batch | Enrolled course/batch | No |
| Direct messages | Moderation only with audit | Same-branch staff/learners per policy | Assigned learners/staff | Teachers/students per policy | No |
| View support tickets | Institution-wide | Own branch | Own tickets | Own tickets | Own tickets |
| Assign/resolve support tickets | Yes | Own branch if permission granted | No | No | No |
| View linked learner summary | Yes | Own branch | Assigned learner only | Own account | Linked learner only |

## Delivery and release gates

- Port only the Community/Message/Support scope from PR #36; do not reintroduce stale homepage, update-center, installer or user-management changes already superseded by merged mainline work.
- Add dedicated policies/scopes and feature tests for all five portals, cross-branch denial, unrelated guardian denial, private-room URL guessing, direct-message participant isolation and support-ticket isolation.
- Run Laravel CI, package workflow and CodeQL on the exact PR head. Fix failures before marking ready.
- Keep the work in a feature branch and draft PR until automated checks and review are complete.
- No production migration, deployment, live payment activation or production data changes as part of this work.
