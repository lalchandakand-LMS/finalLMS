# Broadcasting, Marketing and Omnichannel Automation

This roadmap adapts the capabilities shown in the supplied BotSailor reference image to
Alom’s English Center LMS. It is an LMS communications and growth workspace, not a
white-label SaaS resale product. The LMS must retain Alom’s English Center identity.

## Current verified foundations

The repository already contains:
- SMS campaign creation and queued recipient delivery, restricted to phone-verified users
  who explicitly opted in to SMS marketing.
- Per-campaign recipient delivery states and reports.
- Marketing Center tracking configuration, sitemap/SEO links, Meta promotion planner and
  Meta Pixel events gated by marketing-cookie consent.
- Existing support-ticket workflow.
- Transactional email delivery when server SMTP is configured.
- Existing LMS notice-board and course/payment workflows.

The Marketing Center now links to these existing SMS campaign, Meta campaign planner and
support-ticket screens, and clearly labels the future integrations as planned. A UI badge is
not proof that an external channel is connected.

## Target capability set

### 1. Broadcasts and campaign management
- Campaign builder for SMS, email, in-app notices and (after provider integration) WhatsApp.
- Audience segments: role, branch, course, batch, enrollment status, admission stage and
  explicit communication preferences.
- Scheduled delivery, draft/approval/publish states, preview, test send, throttling, queue
  monitoring, retries and delivery reports.
- Campaign analytics: eligible, queued, delivered, failed, bounced, unsubscribed and
  conversion events. Avoid reporting a send as delivered without provider confirmation.
- Separate operational messages (class changes, payment receipts, safety notices) from
  promotional marketing campaigns.

### 2. Shared inbox and lightweight CRM
- Unified conversation timeline for website chat, support tickets and connected messaging
  channels.
- Contact profile linked to an LMS user only after verified identity matching; do not merge
  accounts solely by display name or phone number.
- Assignment, team queues, tags, internal notes, priority, SLA timers and human hand-off.
- Conversation history retention, export controls and branch-scoped visibility.
- AI Support Agent may draft a response or suggest an FAQ. A human can review/send; no
  automatic promise, refund, admission approval or account change.

### 3. Omnichannel connectors
Planned connectors: WhatsApp Business Platform, Facebook Messenger, Instagram messaging,
Telegram Bot API, website live chat and email. Each connector needs its own provider setup,
permissions, webhook verification, delivery callbacks, rate limits and health checks.
WhatsApp/Messenger/Instagram messaging must use approved official business APIs and comply
with their templates, opt-in and messaging-window policies. No unofficial browser automation
or scraping.

### 4. Workflow automation
A visual, event-triggered workflow builder should support:
- Triggers: registration, admission application submitted/approved, enrollment activated,
  invoice due/paid, lesson progress milestone, assignment deadline, class reminder and
  support-ticket state changes.
- Conditions: role, branch, course/batch, consent, schedule window and event properties.
- Actions: create an internal task, enqueue a consent-eligible notification, add a CRM tag,
  notify assigned staff or request human approval.
- Guardrails: preview, dry-run, per-workflow rate limits, deduplication/idempotency, retry
  policy, pause/kill switch, audit trail and versioned publishing.
- High-impact actions (admission decisions, payment/refund changes, grading, bulk account
  changes) are never direct automation actions. They create an approval request.

### 5. API, webhooks and integrations
- Versioned, permission-scoped API tokens with expiry, revocation, last-used time and
  per-token rate limits. Store only token hashes; show the secret once.
- Signed outbound webhooks with timestamp, HMAC signature, event ID, retry/backoff,
  delivery logs and replay protection.
- Inbound webhooks require provider signature validation, timestamp tolerance, idempotency
  and strict payload validation before processing.
- Event catalog should expose only documented, minimized events. Never include passwords,
  OTPs, API keys, full payment credentials, exam answers or unnecessary student data.
- Integration health dashboard: configured, verified, last successful event, failures and
  reconnect action. A provider is marked Connected only after a real verification check.

### 6. E-commerce and enrollment integrations
- Connect campaign attribution to course pages, approved coupon codes, checkout and
  completed enrollment/payment events.
- Support existing LMS products/ebook flows and course purchases where the current
  checkout supports them.
- Preserve payment gateway verification and reconciliation. A marketing integration must
  never mark an invoice paid or enroll a student based only on a browser redirect or
  unverified webhook.

### 7. Alom-branded white-label controls
- Institution branding controls: logo, favicon, brand colors, email sender display name,
  public domain and portal identity, subject to current single-institution architecture.
- Future multi-branch or multi-tenant support must isolate domains, assets, contacts,
  conversations, provider credentials, campaigns and analytics by tenant/branch.
- Do not expose third-party vendor branding in the LMS interface unless legally required.
- Custom domains require DNS ownership verification and TLS before activation.

## Mandatory communication and privacy controls

- Store channel-specific consent, source, timestamp, policy version and withdrawal time.
- Honor opt-outs immediately, maintain suppression lists and prevent re-import from
  re-enabling opted-out contacts.
- Segment queries must be permission- and branch-scoped before recipient IDs are generated.
- Use verified contact endpoints; avoid sending marketing messages to unverified numbers.
- Apply quiet hours, frequency caps, provider rate limits and emergency stop controls.
- Keep secrets in encrypted server-side secret storage/environment configuration, never in
  browser code, logs or campaign exports.
- Restrict message templates and attachments; scan uploads and limit file types/sizes.
- Audit campaign creation, audience preview, approval, schedule, send, pause, retry, export
  and connector credential changes. Minimize personal data in audit metadata.

## Suggested phased implementation

1. **Foundation and UI:** expose current SMS, Meta planner and support tools from one
   Marketing Center; label connected vs planned features accurately. (Initial dashboard
   integration completed in this branch.)
2. **Campaign engine:** shared campaign/audience model, email + in-app broadcasts, consent
   center, approvals, scheduling, queue metrics and delivery analytics; reuse the existing
   SMS gateway without duplicating its sending implementation.
3. **CRM and inbox:** normalized contacts, verified LMS identity linking, conversation
   threads, assignment, staff hand-off and website chat.
4. **Official channel connectors:** WhatsApp Business, Messenger/Instagram, Telegram and
   email adapters, each with isolated credentials, verified webhooks and contract tests.
5. **Workflow builder:** event triggers, safe conditions/actions, dry-run, approval gates,
   idempotent execution and audit log.
6. **API/webhooks and commerce attribution:** scoped token lifecycle, signed event delivery,
   replay protection and verified checkout attribution.
7. **Advanced analytics and AI:** campaign recommendations, response drafts, support
   summarization and learning-safe messaging. AI may propose; humans approve sensitive
   communications and all consequential LMS actions.

## Release acceptance criteria

- Tests prove no campaign can target a user who opted out or lacks required verification.
- Branch and role isolation is tested for audience preview, campaign creation, exports,
  conversations and API access.
- Duplicate inbound webhooks and queue retries cannot create duplicate sends or payment
  changes.
- Provider failure, rate limit, invalid signature and revoked credential paths are tested.
- Human approval, audit events, opt-out processing and emergency stop are verified.
- Connector status is backed by a live sandbox verification; unconfigured channels remain
  visibly marked Planned/Not connected.
- All migrations, route checks, PHP tests, frontend build and security scans pass on the
  exact PR head before merge or production activation.
