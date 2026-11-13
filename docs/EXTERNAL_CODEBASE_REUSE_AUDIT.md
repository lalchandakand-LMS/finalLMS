# External codebase discovery and reuse audit

Last reviewed: 2026-11-14. Canonical destination: `lalchandakand-LMS/finalLMS` (Laravel 12).

| Repository | Findings | Reuse decision |
|---|---|---|
| `mdlalchandakand-cell/AEC-LMS` | PHP 8.3 PDO API + React/Vite; guardian consent, commissions/payouts, batches/routines, private e-book/media. | Port business rules/tests only; schema/backend differs. |
| `lalchandakand-LMS/aloms_lms` | Laravel 12 source under `laravel/`; Media Library, Page Builder, homepage sliders, Storage Manager, Google Drive, backups, assignments. | Best direct Laravel reference; compare schema and authorization before selective port. README says not production-ready. |
| `lalchandakand-LMS/Final` | Phase notes and package metadata only; no app source tree. | Documentation only. |
| `lalchandakand-LMS/lms` | Empty. | No source. |
| `lalchandakand-LMS/demo-of-Google-AI` | README-only demo. | No LMS source. |
| `lalchandakand-LMS/finalLMS` | Canonical Laravel app/issues. | Destination for accepted ports. |

## High-value reuse map
- AEC-LMS: student-consent guardian links, branch matching, throttling, revocation, audit; teacher commissions; batch capacity/routines; private media/e-books.
- aloms_lms: Media Library, CMS Page Builder, homepage slider/customization, local storage quota, Google Drive OAuth, audited storage migration, backup/restore, assignment review, hosting preflight.
- Do not copy whole repositories. Preserve finalLMS as canonical, add new migrations, reconcile dependencies/schema/roles, and require exact-head Laravel CI, package checks and CodeQL before merge.
- Production deployment and irreversible data changes remain separate release gates.
