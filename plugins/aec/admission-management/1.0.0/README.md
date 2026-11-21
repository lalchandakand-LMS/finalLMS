# Admission Management

Installable Alom's English Center module for the LMS plugin runtime.

## Included
- Public admission intake and tracking using application code plus email.
- Admin and branch-manager review queues with branch-scoped queries and locked status transitions.
- Screening, shortlist, interview, waitlist, approval and rejection workflow.
- Private local-disk PDF/JPG/PNG document uploads and authorized downloads.
- Approved-applicant conversion to a student User account, with a password-reset flow.

## Security and operations
- Public endpoints are throttled and validate input.
- Tracking returns only a minimal application projection; documents and internal fields are never public.
- Uploaded files are stored on the private local disk, not the public web root.
- Branch managers must have a branch assigned and cannot review another branch's records.
- Existing email accounts are never silently attached to an application during conversion.
- Plugin rollback intentionally retains applicant records and documents.

This package is source code only until uploaded with a trusted release signature through the Admin Plugin Manager. Do not bypass signature verification or activate directly in production.
