# Official API Connector Framework

This document is the implementation contract for making official third-party APIs usable
from the Alom's English Center LMS. A provider must never appear connected merely because
credentials were entered. The LMS marks it **Connected** only after a successful provider
credential/permission check and, where applicable, webhook verification.

## Product behavior

Provide an Admin > Integrations workspace with one setup card per provider:
- WhatsApp Business Platform (Meta Cloud API)
- Facebook Messenger Platform
- Instagram Messaging API
- Telegram Bot API
- Email (SMTP and, later, official provider APIs)
- SMS gateway
- Meta Marketing API (separate from messaging)
- Payment gateways (existing payment contract; never conflate marketing delivery with payment verification)

Each setup wizard must show: provider prerequisites, required account/business assets,
exact permissions/scopes, callback/webhook URL, verification-token instructions, credential
fields, connection test, webhook test, enabled capabilities, last successful sync/event,
last error, reconnect, pause and disconnect. Show missing prerequisites explicitly.
Never promise that one API key enables capabilities that the provider account has not
approved or that the API does not expose.

## Provider adapter contract

Implement each provider behind a typed adapter and a common capability registry. The common
operations are deliberately normalized while provider-specific features remain capability
gated:

- validateCredentials(): provider identity and granted scopes; no message send side effects
- capabilities(): supported inbound/outbound message types, templates, media, reactions,
  read receipts, campaign limits and supported webhook events
- verifyWebhook(request): verify official signature/token and timestamp/replay constraints
- normalizeWebhook(request): return a validated internal event with provider event ID
- sendMessage(message): send one approved message; return provider message ID and accepted status
- sendTemplate(templateMessage): send approved template where required
- getDeliveryStatus(providerMessageId): return authoritative delivery state when supported
- healthCheck(): report credential, permission, webhook and API health separately

Do not implement unsupported capabilities as successful no-ops. Return a typed
UnsupportedCapability result and explain which provider permission or product limitation
prevents the operation.

## Credential and webhook security

- Encrypt provider secrets at rest using the application's established encryption service.
  Never return secret values to the browser after saving; display only masked metadata.
- Do not store tokens in source control, logs, analytics, exception text or webhook payload
  archives. Rotate/revoke credentials without losing conversation history.
- Use HTTPS callback URLs. Validate official signatures using the raw request body and
  constant-time comparison. Verify subscription challenge tokens separately.
- Enforce timestamp tolerance, event-ID idempotency, payload size limits, schema validation,
  replay protection and per-provider rate limits.
- Queue webhook processing; acknowledge only after authenticity and durable receipt are
  established. Retry transient failures with bounded exponential backoff and dead-letter
  visibility. Never duplicate an outbound send after an ambiguous provider timeout.
- Keep tenant/branch identity server-derived. A webhook payload cannot choose its own branch.
  Resolve provider account/page/phone-number IDs against a previously verified connection.
- Audit credential changes, scope changes, connection tests, webhook verification,
  outbound sends, retries, pauses, disconnects and data exports without recording secrets.

## Messaging policy and consent

Maintain channel-specific opt-in evidence (source, timestamp, policy version, purpose),
verification status, opt-out timestamp and suppression state. Suppression always wins over
campaign audience membership. Support provider-specific template approval and messaging
windows (including WhatsApp rules), quiet hours, frequency caps, local time zones and
human approval for bulk campaigns. Distinguish transactional notices from promotional
marketing. Do not message a student/guardian on a channel without a valid channel consent
and provider policy basis.

## Shared inbox and delivery lifecycle

Normalize inbound/outbound events into conversations, messages and delivery attempts.
Support assignment, staff handoff, internal notes, tags, SLA and branch-scoped access.
Only link a conversation to an LMS student/guardian after verified identity matching; never
merge on display name alone. Store provider IDs and minimal metadata needed for deduplication
and support. Delivery states must distinguish queued, accepted, sent, delivered, read,
failed and unknown; only show states the provider actually confirms.

## Human approvals and workflow engine

Campaigns use draft -> preview -> approval -> scheduled/queued -> sending -> completed/
partially failed/failed states. Audience preview must show eligible, suppressed and excluded
counts before approval. Large campaigns require an authorized approver other than the
creator. Add idempotency keys, dry-run, pause/kill switch, rate limits, retries, versioned
workflow definitions and immutable audit events. AI may draft messages or propose actions;
it cannot approve its own campaign or execute admission, grade, payment, refund or account
changes.

## Provider-specific prerequisites and setup

### WhatsApp Business Platform
Requires a Meta business portfolio, eligible WhatsApp Business Account, registered phone
number, app configuration, approved permissions and (for production messaging) approved
templates as required. Store the WABA ID, phone-number ID, access token, app secret and
webhook verify token securely. Subscribe and verify the official webhook. Enforce opt-in,
template and customer-service-window rules. Do not use unofficial WhatsApp Web automation.

### Messenger
Requires a Facebook Page, Meta app, approved Messenger permissions and a Page access token.
The Page must be connected and webhook subscriptions verified. Respect Meta app review and
messaging policy. A Page token is not interchangeable with a WhatsApp token.

### Instagram Messaging
Requires an eligible professional Instagram account linked to a Facebook Page, Meta app
permissions and the required review/approval. Store the account/page identifiers and token
for this specific connection. Support only API-exposed messaging features and webhook events.

### Telegram
Requires a bot created through BotFather, bot token and configured HTTPS webhook/secret token.
Telegram bot messaging does not grant access to arbitrary user chats: a user must first start
or otherwise interact with the bot according to Telegram rules. Verify the webhook secret
header and deduplicate update IDs.

### Email and SMS
Use separate provider adapters, sender/domain verification, SPF/DKIM/DMARC guidance for
email, delivery callbacks where available, unsubscribe handling for marketing and phone
verification/consent for SMS. Keep transactional and promotional streams distinct.

### Meta Marketing API
Treat ad account access, app review, business permissions and marketing insights as a separate
connector from Messenger/Instagram inbox. Do not claim live spend or conversion data unless
the Marketing API returns it successfully. Keep manually entered spend visibly labelled as
manual.

## Configuration schema and status

Store one connection record per provider account and institution/branch scope, including:
provider key, encrypted credential reference, external account IDs, granted scopes,
capability snapshot, webhook verification state, status, last credential check,
last webhook event, last successful operation and sanitized last error. Do not store
provider secrets in generic settings JSON or expose them to branch users unless a permission
explicitly grants credential administration.

Statuses: Not configured, Needs setup, Pending verification, Connected, Degraded,
Reauthorization required, Paused and Disconnected. Only Connected means credential,
required permissions and webhook checks have succeeded. A provider can be Connected for
inbound-only capabilities while outbound remains disabled; display capabilities individually.

## Acceptance tests required before enabling a provider

1. Valid credentials and required scopes pass; invalid/revoked credentials fail closed.
2. Webhook challenge and signature verification pass; invalid signature, stale timestamp,
   duplicate event and oversized payload are rejected safely.
3. Inbound event normalization is idempotent and branch/account mapping is server-derived.
4. Outbound send returns provider message ID; timeout ambiguity does not trigger blind resend.
5. Delivery callbacks update only the matching provider message and never invent delivery.
6. Consent, opt-out, quiet hours, template policy, rate limits and audience branch scope are
   enforced before queueing.
7. Secrets never appear in HTML, JSON responses, logs, exceptions, exports or audit payloads.
8. Disconnect/revoke pauses jobs, preserves audit/conversation history and safely handles
   pending queue items.
9. Capability-gated UI hides or disables unsupported features with an actionable explanation.
10. Provider sandbox/staging test passes before production activation.

## Rollout rule

Build the connector framework and setup UX first, then implement and test providers one at a
time against their official current APIs. A provider cannot be marked production-ready until
its adapter, migrations, queued jobs, webhook routes, permission checks, admin setup UI,
documentation, contract tests and staged end-to-end tests all pass. Credentials and provider
account approvals must be supplied by the institution; the repository cannot create those
accounts or grant external permissions on its behalf.


## Core rollout status

The native registry, encrypted credential lifecycle, provider capability catalog, admin setup workspace,
webhook event ledger, replay deduplication and audit trail are implemented in Laravel Core.
The first live adapter is Telegram Bot API. It validates the bot with `getMe`, requires a
separate webhook secret, authenticates Telegram's `X-Telegram-Bot-Api-Secret-Token` header,
and deduplicates updates by `update_id`. Outbound text is refused unless the connection has
received an authenticated webhook and is in the Connected state.

For Telegram setup, create a bot with BotFather, save the bot token and a high-entropy webhook
secret in Admin > API Integrations, then configure Telegram's `setWebhook` method with the
displayed HTTPS callback URL and the exact same `secret_token`. The first authenticated update
completes webhook verification. The LMS never treats credential entry alone as Connected.

WhatsApp Cloud API, Messenger, Instagram Messaging, SMTP, SMS and Meta Marketing API remain
unregistered until their provider-specific official adapters, permission checks, signature
verification, delivery callbacks and staged acceptance tests are implemented. The UI does not
pretend these providers are active.
