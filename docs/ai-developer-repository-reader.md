# AI Developer: read-only repository context

Repository context is disabled by default. When enabled, the AI Developer diagnostic workflow can read only exact source-file paths configured by the server administrator. It cannot write files, create branches, commit, merge, execute shell commands, or deploy.

Configure these values in the private hosting environment (never commit real credentials):

- `AI_DEVELOPER_REPOSITORY_CONTEXT_ENABLED=true`
- `AI_DEVELOPER_GITHUB_TOKEN` — a fine-grained token limited to this repository with **Contents: read-only** and Metadata: read-only.
- `AI_DEVELOPER_GITHUB_REPOSITORY=lalchandakand-LMS/finalLMS`
- `AI_DEVELOPER_GITHUB_READ_REF=main`
- `AI_DEVELOPER_ALLOWED_REPOSITORY_PATHS=app/Http/Controllers/AuthController.php,routes/web.php,config/auth.php` — comma-separated exact paths; keep this list minimal.
- Optional limits: `AI_DEVELOPER_REPOSITORY_MAX_FILE_BYTES=12000` and `AI_DEVELOPER_REPOSITORY_MAX_TOTAL_BYTES=40000`.

The reader rejects traversal and sensitive directories/files, limits the number and size of files, redacts common credentials, inline password/payment settings, email addresses, and Bangladesh mobile numbers, uses GitHub's fixed HTTPS API endpoint, and disables redirects. Repository content is passed to the model as untrusted reference data, never as instructions.

Do not allow-list `.env`, private keys, payment credentials, student uploads, database exports, logs, backups, `vendor`, `storage`, or secrets. Pattern redaction is not a guarantee; review the allow-list and source files before enabling this feature. The GitHub token is never sent to the AI provider.

AI task instructions and provider responses are encrypted at rest using the Laravel application key. Inputs and outputs are also redacted before persistence; encrypted storage is an additional safeguard, not a substitute for keeping secrets out of prompts.\n\nThis is a read-only milestone. Any future patch-writing feature must use a separate short-lived, least-privilege GitHub App installation token, create a feature branch only, enforce a strict file allow-list and patch-size limits, run CI, present the full diff, and require explicit human approval. No AI auto-merge or deployment is permitted.
