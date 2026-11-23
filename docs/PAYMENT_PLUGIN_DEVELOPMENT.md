# Universal Payment Gateway Plugin Architecture

## Purpose

The LMS uses a provider-neutral payment contract. Checkout and fulfillment code owns local pricing, pending-payment creation, enrollment/order fulfillment and idempotency. A gateway adapter owns provider-specific checkout creation and server-to-server transaction verification.

## Security model

- Gateway PHP code is installed and reviewed as application code. Admin users cannot upload or execute arbitrary PHP plugins.
- Provider secrets stay in deployment environment variables or the encrypted Admin merchant-credential store (`mfs_merchant_configurations.credentials`). They are never returned to the Admin UI, flashed into session input, included in audit descriptions, committed to Git, or sent to mobile clients.
- Admin registry enablement is separate from provider configuration. A provider is available only when both are true.
- Browser redirects and callback query parameters are untrusted. Adapters must fetch authoritative status server-to-server.
- Every adapter must validate provider payment ID, exact amount, currency, merchant/order reference and terminal success status before the application fulfills an order.
- Provider transaction IDs and payment IDs must be unique; callbacks must be idempotent.
- TLS certificate verification must remain enabled. Checkout URLs must use HTTPS and a provider-host allow-list.
- Refund support is optional and must be declared only when the provider's official API supports it.

## Implementing a gateway

1. Add a class implementing App\Contracts\Payments\PaymentGateway.
2. Implement key, label, supportedCurrencies, isConfigured, createCheckout and verifyCallback.
3. Validate all provider-returned checkout URLs against a fixed HTTPS host allow-list.
4. In verifyCallback, ignore callback success claims and query the provider's API using server-held credentials.
5. Declare `payments.gateway` in the signed plugin manifest capabilities, then call `PluginContext::registerPaymentGateway($key, GatewayClass::class)` from the plugin entrypoint's `register()` method. Registration is restricted to active, signature-verified plugins and duplicate gateway keys are rejected.
6. Define provider-specific credentials and options in the signed manifest `settings_schema`; use the encrypted plugin settings store. Secret values must never appear in rendered pages, API responses, activity logs or application logs.
7. Add adapter HTTP tests, callback replay tests, amount/currency/reference mismatch tests, refund tests where supported, and explicit sandbox/live readiness checks.
8. Keep the provider disabled until official merchant documentation and sandbox acceptance are complete.

## Admin installation and credentials

Payment gateway packages use the same central Laravel Plugin Manager as every other extension: signed ZIP import, compatibility and signature checks, install, permission review, activation, settings, update, rollback and uninstall. Admins—not developers—enter their own merchant credentials and choose sandbox or live mode. Each adapter must validate the chosen mode against its official HTTPS host allow-list and perform provider-side transaction verification. Credentials alone must never mark a gateway ready.

## Current adapter status

- bKash Tokenized Checkout: first adapter, BDT only. Uses the existing server-side token/create/execute/status client and encrypted Admin credentials; current endpoint policy is sandbox-only.
- Nagad and Rocket: encrypted Admin credential records are supported; direct checkout adapters are not registered until official merchant protocol specifications and provider sandbox tests are available.
- SSLCOMMERZ: encrypted Admin store ID/password records support sandbox and live credential storage. No checkout adapter is registered until hosted-session, IPN, server-side validation, amount/currency binding, replay protection, reconciliation and sandbox acceptance tests are implemented.
- Stripe and PayPal: not registered.

## Admin enablement

The Admin payment gateway registry stores only a boolean allow-list switch. Credentials remain in hosting secrets. Enabling a provider does not by itself activate production payments; the provider must also pass its sandbox acceptance checklist.
