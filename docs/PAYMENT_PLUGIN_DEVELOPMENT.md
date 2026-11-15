# Universal Payment Gateway Plugin Architecture

## Purpose

The LMS uses a provider-neutral payment contract. Checkout and fulfillment code owns local pricing, pending-payment creation, enrollment/order fulfillment and idempotency. A gateway adapter owns provider-specific checkout creation and server-to-server transaction verification.

## Security model

- Gateway PHP code is installed and reviewed as application code. Admin users cannot upload or execute arbitrary PHP plugins.
- Provider secrets stay in deployment environment variables or a secret manager. They are never returned to the Admin UI, stored in ordinary settings, committed to Git, or sent to mobile clients.
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
5. Register the adapter in AppServiceProvider using PaymentGatewayManager::register.
6. Add provider configuration under config/services.php, deployment examples, admin readiness tests, adapter HTTP tests, callback replay tests and amount/currency/reference mismatch tests.
7. Keep the provider disabled until official merchant documentation and sandbox acceptance are complete.

## Current adapter status

- bKash Tokenized Checkout: first adapter, BDT only. Uses the existing server-side token/create/execute/status client.
- Nagad, SSLCommerz, Stripe and PayPal: not registered yet. They require official merchant/API documentation, credentials and provider-specific sandbox tests before an adapter can be enabled.

## Admin enablement

The Admin payment gateway registry stores only a boolean allow-list switch. Credentials remain in hosting secrets. Enabling a provider does not by itself activate production payments; the provider must also pass its sandbox acceptance checklist.
