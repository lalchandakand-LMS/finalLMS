# Learn & Earn plugin — implementation status

This is a first-party, independently installable module built on the signed Alom's LMS plugin runtime. The package contains no external AI-builder branding or third-party developer branding.

## Delivered in this implementation slice

- Signed plugin manifest for Admin, Student and public portals, with separately declared Admin and student permissions.
- Plugin-owned question categories and reusable MCQ bank with exactly four distinct options, positive/negative marks, difficulty, topic and explanations.
- Admin exam builder with schedule, duration, pass marks, question count, attempt limit, free/paid classification, BDT price, randomization and result visibility.
- Immutable exam question snapshots, so later MCQ edits do not change an existing exam.
- Public exam catalogue and details that do not expose answer keys.
- Student exam catalogue, free exam attempts, paid exam checkout through the configured bKash gateway, attempt history and result page.
- Paid access is granted only after server-side bKash status verification, matching payment ID, amount, BDT currency and merchant invoice reference.
- Payment approval and entitlement creation happen in one database transaction; duplicate callbacks are idempotent and an existing active entitlement blocks duplicate purchases.
- Wrong answers deduct configured negative marks, unanswered questions score zero, and the final total cannot fall below zero.
- Student ownership checks, exam-window checks, attempt limits, transactional attempt creation/submission and server-side deadlines.
- Migration rollback refuses to drop existing questions, exams, snapshots, attempts, answers, payment records or entitlements.
- Admin activity audit events and permission-gated routes/navigation.

## Not delivered yet — do not mark complete

- Manual bKash/Nagad/bank-transfer exam payments and Admin review workflow. Only the configured online bKash gateway is supported for paid exams in this slice.
- Automatic answer autosave and recovery after browser/device interruption.
- Student-facing real-device/browser acceptance, accessibility and Bangla/English copy QA.
- Signed distributable ZIP and trusted release-key provisioning.
- Flash Sale extraction into its own independent installable plugin.
- Full lifecycle backup/restore and cPanel staging rehearsal.

Paid exam access remains denied unless a matching server-verified bKash callback creates an active entitlement. Browser query parameters and client-side status are never treated as proof of payment.
