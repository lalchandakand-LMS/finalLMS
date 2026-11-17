# Learn & Earn plugin — implementation status

This is a first-party, independently installable module built on the signed Alom's LMS plugin runtime. The package contains no external AI-builder branding or third-party developer branding.

## Delivered in this implementation slice

- Signed-runtime manifest for admin, student and public portals.
- Isolated plugin-owned categories, reusable MCQs, exam snapshots, attempts and answer tables.
- Admin MCQ authoring with four distinct options, categories, difficulty, subject, positive marks, negative marks and explanations.
- Admin exam builder with schedule, duration, pass marks, question count, attempt limit, free/paid classification, BDT price, randomization and result visibility.
- Immutable exam question snapshots; later question edits do not change existing exams.
- Public exam catalogue and details without exposing question keys.
- Student exam catalogue, free-exam attempt creation, active-attempt resume, attempt history and result page.
- Server-side answer validation and scoring; wrong answers deduct configured negative marks, unanswered questions score zero, and the final total cannot fall below zero.
- Student ownership checks, exam-window checks, attempt limits, transactional attempt creation/submission and server-side deadlines.
- Migration rollback refuses to drop existing questions, exams, snapshots, attempts or answers.
- Admin audit events for question/category/exam changes.
- Permission-gated routes and admin/student/public navigation.

## Not delivered yet — do not mark complete

- Paid exam checkout and verified payment entitlement. Paid attempts are intentionally blocked; the browser cannot grant access.
- Automatic answer autosave and recovery after browser/device interruption.
- Student-facing real-device/browser acceptance, accessibility and Bangla/English copy QA.
- Signed distributable ZIP and trusted release-key provisioning.
- Flash Sale extraction into its own independent plugin.
- Full lifecycle backup/restore and cPanel staging rehearsal.

Paid exam access must remain disabled until a provider callback/webhook is verified server-side, payment amount/currency/exam/student are matched, duplicate callbacks are idempotently rejected, and an entitlement is created transactionally.
