# Universal AI Assistant — Issue #257 implementation notes

The Universal AI Assistant is an optional first-party plugin. Its canonical source is
`plugins/aec/universal-ai-assistant/1.0.0`; it is not a Core dependency. No Google AI Studio
branding, generated dashboard, or `.aistudio` directory belongs in the LMS repository.

## Provider boundary

The application-level `App\Contracts\Ai\AiProvider` contract separates provider
capabilities, model discovery, standard completion and streaming from assistant routes.
The plugin currently registers one OpenAI-compatible adapter. Model discovery exposes only
model IDs with an explicit capability profile. Unknown models fail closed. The adapter chooses
the output-token parameter by model family and omits temperature for reasoning models that
do not support it. The current Admin allow-list remains restricted to the configured HTTPS
provider hosts; this is not yet a multi-vendor catalogue.

## Streaming and usage controls

Chat uses server-sent events. Provider deltas are forwarded as text events, UTF-8 chunk
boundaries are preserved, output size is bounded, and the browser appends text nodes rather
than rendering provider HTML. A token reservation is acquired before the provider request;
prompt persistence and reservation state are transactional; response persistence and
settlement are atomic. Provider failures release the reservation. Provider-reported usage is
preferred, with a conservative estimate when usage metadata is absent.

The prompt estimator is deliberately conservative but is not a provider tokenizer. Actual
usage may differ from estimates. Institution-wide budgets and per-user daily request limits
are enforced against reservations and stored usage.

## Role-scoped read-only context

General Guidance Only remains the default. Admin must separately enable the role assistant
and its read-only context grant. Retrieval uses the existing `PluginResourceAccess::scopeQuery`
before fetching records:

- Admin: aggregate student, course, batch and enrollment counts only.
- Branch Manager: courses, batches, enrollments, and published assignment/exam counts scoped to the assigned active branch.
- Teacher: assigned courses, batches, enrollment summaries, and published assignment/exam submission/attempt counts.
- Student: own active/completed enrollments, lesson titles, published assignments/exams, own submission/attempt status, and lesson completion counts.
- Guardian: only students connected by an approved, active, reviewed guardian-student link, with those linked students' courses, batches, published assignments/exams, submission/attempt status and lesson completion counts.
- Support Staff: no private LMS context.

The context is bounded and read-only. User-authored course and lesson titles are treated as
untrusted data. Retrieval events record the role, scope and source identifiers; completed
responses and provider failures are recorded without storing prompt text or provider secrets.

The provider adapter retries transient connection/5xx/429 failures before any stream bytes are emitted; it does not retry after a stream has begun. Successful model discovery is the Admin's provider availability check.

The context exposes no grades, marks, answer content or feedback. It intentionally does **not** expose attendance, payments,
private staff records, full lesson bodies, assignment submission answers/attachments, exam questions/answers, or mutation
tools. Those sources require separate policy review, minimized DTOs, audit and isolation tests
before they can be added. There is no autonomous action execution.

## Lifecycle and release

The existing Plugin Manager runtime requires a trusted signature, verified package checksum,
manifest identity and installed-file integrity before loading an active plugin. Deactivation
and uninstall are registry-controlled; the AI conversation/settings tables are separate from
the plugin code and are retained by default. An offline Ed25519 release signer is included, but no signed archive has been created. Signing requires the institution's configured release private key on a trusted build workstation and a matching trusted public-key configuration in the LMS. Do not activate or distribute an unsigned package.

## Remaining release gates

- Run exact-head Laravel CI, CodeQL and package checks after the final commit.
- Produce the signed plugin archive using the configured release signing process and test
  install, activate, deactivate, update, rollback and uninstall on a disposable staging copy.
- Verify cPanel-compatible installation and rollback with the target host's PHP extensions,
  trusted release key and database configuration.
- Add separately reviewed, scoped assignment/exam/progress and any grade/attendance/fee
  retrieval only after their data models and policy checks are tested.
- Add bounded retry/backoff and visible provider-health diagnostics; do not retry after any
  stream content has been emitted.
