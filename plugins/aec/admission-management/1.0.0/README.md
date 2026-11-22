# Admission Management

Installable Alom's English Center module for the LMS plugin runtime.

## Applicant experience
- Branded online application form for applicant, guardian, contact, education, course, batch, branch and address information.
- Upload up to five supporting documents with typed slots (photo, birth certificate, NID, previous certificate and other).
- PDF/JPG/PNG files are validated (10 MB each) and stored on the private local disk, outside the public web root.
- Application tracking by application code plus email; applicants can see decision status, interview schedule and document verification status without seeing internal reviewer notes.
- Intake can be opened or closed through plugin settings; public submission and tracking are throttled.

## Admission office workflow
- Walk-in/offline application registration by Admin and Branch Manager.
- Search and filter by applicant, application ID, course, branch, date range, source, decision and document verification state.
- Branch-scoped queues and reports; branch managers cannot view or act on another branch's applicants.
- Screening, shortlist, waitlist, interview scheduling, approval and rejection with validated status transitions.
- Assign a reviewer from authorized staff. Rejection requires a reason.
- Private document review: verify or reject each document, record correction notes and reviewer/time. Applications with submitted documents cannot be approved or converted until every document is verified.
- Full internal audit timeline for submissions, staff registration, decisions, document uploads/reviews and student conversion.
- Approved applicant conversion to a student account, optional BDT admission-fee invoice, duplicate-email protection and password setup email.
- Admission analytics: pipeline counts, online vs walk-in source, document-verification breakdown, monthly intake trend, conversion rate and filtered CSV export.

## Security and operations
- Applicant tracking requires both the application code and matching email.
- Public tracking returns only a minimal safe projection and never exposes private documents, internal reviewer notes or audit events.
- Uploaded documents use generated storage names, content validation, SHA-256 checksums and private download routes with branch/application ownership checks.
- Reviewer assignment and application/document access are branch-scoped.
- CSV reports are restricted to authorized admission staff and branch-scoped; exports include applicant contact details and should be handled as confidential data.
- Plugin rollback intentionally retains applicant records, private documents and audit history.

This package remains source code until uploaded with a trusted release signature through the Admin Plugin Manager. Do not bypass signature verification or activate directly in production.
