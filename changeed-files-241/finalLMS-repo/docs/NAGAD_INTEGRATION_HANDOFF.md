# Nagad API integration handoff

## Current state

The LMS currently supports Nagad as a configurable/manual payment method. Direct Nagad API checkout is **not implemented** and `NAGAD_ENABLED` must remain `false`.

This is intentional: the merchant-specific official integration specification has not yet been supplied. Do not invent endpoints, request fields, signature rules, encryption formats, callback semantics, or response status values. A payment callback or browser redirect alone must never activate an enrollment.

## What to provide when the merchant guide is available

Upload the official merchant integration PDF/ZIP or share the official documentation URL. If Nagad supplied separate documents, include all of them:

- Merchant integration/API guide and API version.
- Sandbox base URL and endpoint definitions for authentication/token, checkout/order creation, payment verification/status query, and (if supported) refund.
- Exact request and response schemas, required headers, content types, timestamp/nonce rules, and error/status code definitions.
- Cryptographic specification: signing and encryption algorithms, padding, digest, canonical string/field ordering, character encoding, Base64/hex rules, and key/certificate formats.
- Callback/webhook specification: HTTP method, payload, signature verification, retry behavior, and whether a server-to-server status query is required.
- Sandbox test account instructions, test cases, IP allowlist requirements, TLS requirements, and production activation procedure.

**Never send live passwords, API secrets, private keys, OTPs, or account login details.** The guide and public key/certificate are useful; private merchant keys should be installed directly on the server and never committed to Git.

## Implementation plan after receiving the guide

1. Review the guide and map every required field, endpoint, cryptographic operation, and state transition to the LMS payment record.
2. Confirm any ambiguities against Nagad merchant support before coding. Do not guess undocumented behavior.
3. Implement a dedicated Nagad gateway service/adapter using Laravel's HTTP client and the exact documented protocol. Keep credentials and cryptographic operations server-side.
4. Add checkout initiation and callback/return handling. Validate CSRF/webhook requirements as specified by Nagad, and independently verify the transaction server-to-server.
5. Before approving a payment, verify the merchant/order reference, gateway payment ID, BDT currency, exact amount, successful terminal status, and transaction identifier against the pending local payment. Enforce idempotency and safe retry handling.
6. Add automated tests for valid payment, wrong amount/currency/reference, invalid signature/decryption, failed/cancelled/expired payment, duplicate callback, replay, API timeout, and batch-capacity race conditions.
7. Run the full test suite and cPanel package workflow. Complete sandbox end-to-end tests before enabling the gateway.
8. Keep live mode disabled until the merchant approves production credentials and the sandbox acceptance checklist passes.

## Existing configuration placeholders

The following settings already exist in `config/services.php` and `deployment/.env.example`:

- `NAGAD_ENABLED=false`
- `NAGAD_MODE=sandbox`
- `NAGAD_BASE_URL=`
- `NAGAD_MERCHANT_ID=`
- `NAGAD_MERCHANT_NUMBER=`
- `NAGAD_MERCHANT_PRIVATE_KEY_PATH=storage/app/secure/nagad-merchant-private.pem`
- `NAGAD_GATEWAY_PUBLIC_KEY_PATH=storage/app/secure/nagad-gateway-public.pem`

These names are provisional application configuration slots, not a claim that every Nagad merchant integration uses these exact credential names. They will be adjusted to the official guide if required. Store key files outside the public web root with restrictive permissions; never put key contents in environment files or source control.

## Completion criteria

- Protocol matches the supplied official merchant guide.
- All signature/encryption and response-verification tests pass.
- No payment can be approved from an unverified client callback.
- Duplicate callbacks cannot duplicate payment or enrollment.
- Sandbox checkout, success, failure, timeout, and retry scenarios pass.
- CI `test` and `package` jobs pass.
- Production credentials are configured securely and live mode is enabled only after explicit merchant approval.
