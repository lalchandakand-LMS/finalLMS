# Universal AI Assistant and Agent System

The Universal AI Assistant is an optional first-party plugin. Its canonical source is
`plugins/aec/universal-ai-assistant/1.0.0`; it is not a Core dependency. The LMS interface,
assets and documentation use Alom’s English Center branding only.

## Agent roles — Phase 1 foundation

The existing role-scoped assistant now uses specialist instructions for the planned agents:

| Agent | Phase 1 supported guidance | Explicit limitation |
| --- | --- | --- |
| AI Student Agent | Vocabulary, grammar, explanations, practice quizzes and exam revision | Does not access answer sheets or marks |
| AI Teacher Agent | Draft questions, answer keys, rubrics, lesson plans and feedback templates | Does not grade or publish student work |
| AI Admin Agent | Admission/fee process guidance and aggregate report interpretation | Cannot approve admissions, change fees or execute financial actions |
| AI Support Agent | FAQ and procedural help for course access, admission, password recovery and schedules | No private account lookup or ticket mutation |
| AI Learning Agent | Study plans using the student's own authorized lesson-progress/completion context | Cannot infer academic weaknesses from marks or submissions not in scope |

These are role-specific assistant behaviors within the existing chat interface, not five
independent autonomous services. Admin must enable each role. Read-only context remains a
separate, explicit per-role grant and defaults to off. Specialist prompts do not add database
permissions, expose new records, or bypass portal authorization.

## Mandatory safety boundary

Phase 1 is advice and draft generation only. There are no autonomous action tools. The
assistant must not claim to have enrolled a learner, approved an admission, changed a fee,
issued a refund, graded/published an assignment, sent a message, or modified an LMS record.

Every future action-capable agent must use a proposal-and-approval lifecycle:

1. Agent creates a typed, bounded action proposal; it does not execute it.
2. Server validates the actor, role permission, resource ownership, branch scope, current
   record version, allowed action schema and business invariants.
3. Proposal is stored with requester, agent, action type, target IDs, minimal before/after
   summary, expiry, idempotency key and a cryptographic/request integrity hash.
4. An authorized human reviews the exact proposed change and approves or rejects it.
   The proposer cannot approve their own high-impact action; financial, grading, admission
   and bulk actions require a suitably privileged reviewer.
5. On approval, the server re-checks permissions and record version, executes inside a
   transaction, enforces idempotency, and writes an immutable audit event containing the
   approver and outcome. Stale proposals must be rejected and regenerated.
6. Denials, expiry, execution failures and retries are audited. Secrets and unnecessary
   student content must never be written to audit metadata.

No model-generated instruction, tool call, or user message can substitute for human approval.
Provider outages or uncertain results must not trigger duplicate or blind retries.

## Provider boundary

The application-level `App\\Contracts\\Ai\\AiProvider` contract separates provider
capabilities, model discovery, standard completion and streaming from assistant routes.
The plugin currently registers one OpenAI-compatible adapter. Model discovery exposes only
model IDs with an explicit capability profile. Unknown models fail closed. The adapter chooses
the output-token parameter by model family and omits temperature for reasoning models that
do not support it. The current Admin allow-list remains restricted to the configured HTTPS
provider hosts; this is not yet a multi-vendor catalogue.

## Streaming and usage controls

Chat uses server-sent events. Provider output is fully buffered within a strict size limit,
then sensitive credentials are redacted from the complete response before any assistant text
is emitted to the browser. The browser appends text nodes rather than rendering provider HTML.
A token reservation is acquired before the provider request; prompt persistence and reservation
state are transactional; response persistence and settlement are atomic. An ambiguous provider
failure marks the reservation uncertain and retains its reserved budget rather than silently
releasing it. Institution-wide budgets and per-user daily request limits are enforced.

## Role-scoped read-only context

General Guidance Only remains the default. Admin must separately enable the role assistant
and its read-only context grant. Retrieval uses the existing
`PluginResourceAccess::scopeQuery` before fetching records:

- Admin: aggregate student, course, batch and enrollment counts only.
- Branch Manager: courses, batches, enrollments, and published assignment/exam counts scoped
  to the assigned active branch.
- Teacher: assigned courses/batches and their scoped students' published assignment/exam
  status and lesson completion summaries.
- Student: own active/completed enrollments, lesson titles, published assignments/exams,
  own submission/attempt status, and lesson completion counts.
- Guardian: only students connected by an approved, active, reviewed guardian-student link.
- Support Staff: no private LMS context.

The context is bounded and read-only. It exposes no grades, marks, answer content or feedback.
It intentionally does not expose attendance, payments, private staff records, full lesson
bodies, assignment submission answers/attachments, exam questions/answers, or mutation tools.
Learning recommendations must not imply performance analysis from data that is not available.

## Phased delivery plan

- **Phase 1 — role-specialist assistants:** specialist instructions, current role/branch
  authorization, optional read-only context, encrypted credentials, usage budgets and audit.
- **Phase 2 — agent workspace and safe drafts:** dedicated agent entry points, task templates,
  structured output validation, teacher-reviewed question/lesson/feedback drafts, support FAQ
  knowledge base with source citations, and learning-plan history with student controls.
- **Phase 3 — approval workflow:** typed action proposals, approval inbox, separation of duties,
  expiry, idempotency, record-version checks and immutable audit trail. No action is enabled
  before permission and threat-model review.
- **Phase 4 — scoped integrations:** admission/fee/reporting integrations and teacher workflows
  behind explicit permissions, human approval and comprehensive integration tests.
- **Phase 5 — learning analytics:** only after lawful access to assessment data, consent/retention
  policy, branch isolation, explainable signals and teacher oversight are implemented.

## Release and activation

The plugin requires a trusted signature, verified package checksum, manifest identity and
installed-file integrity before loading. Credentials are encrypted and never re-rendered.
Do not activate or distribute an unsigned package. Test installation, activation,
deactivation, update, rollback and uninstall on disposable staging before production use.
