# Learn & Earn plugin — implementation status

This package is the first implementation slice for the owner-approved aec.learn-earn module.

## Delivered in this slice

- First-party plugin manifest following the signed plugin runtime contract.
- Isolated plugin-owned learn_earn_categories and learn_earn_questions tables.
- Admin MCQ create/edit/archive workflow with exactly four distinct options.
- Independent question categories with create, edit, activate and archive controls.
- Positive marks, negative marks, difficulty, subject and optional explanation.
- Search and filters by category/status, pagination and admin activity audit entries.
- Dedicated routes and navigation guarded by the declared learn-earn.manage permission.
- Migration rollback refuses to drop existing question/category data.

## Not delivered yet

- Signed distributable ZIP and trusted release-key provisioning.
- Exam builder using selected saved MCQs and immutable exam/attempt snapshots.
- Public/student exam catalogue, free exam attempts and result history.
- Paid exam checkout, payment verification and entitlement.
- Attempt timing, concurrency controls, attempt limits and answer-key confidentiality.
- Flash Sale extraction into its own independent plugin.
- Full lifecycle backup/restore, cPanel staging and five-portal browser acceptance.

Do not activate in production or describe Learn & Earn as complete until the remaining items are implemented and accepted.
