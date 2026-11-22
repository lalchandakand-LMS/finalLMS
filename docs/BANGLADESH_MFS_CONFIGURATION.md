# Bangladesh MFS merchant configuration foundation

The admin Payment Gateways page can store sandbox merchant credential sets for bKash, Nagad and Rocket.

## Security and current scope

- Credential maps are encrypted using Laravel's application encryption key before database storage.
- The encrypted field is hidden from model serialization. The admin page never reads credentials back into the form, and audit events contain provider/mode only.
- Saving credentials is restricted to the admin payment-gateway route group and protected by CSRF and request throttling.
- Only sandbox configuration is accepted in this foundation release. Live mode is deliberately rejected.
- The existing bKash adapter remains the only installed Bangladesh MFS checkout adapter. It can resolve bKash credentials from encrypted database configuration, with environment configuration retained as a backward-compatible fallback.
- Nagad and Rocket records are configuration storage only. No checkout, signature, encryption, callback, refund or reconciliation protocol is implied or implemented by this foundation.

## Admin setup

Open Admin > Payment Gateways > Bangladesh MFS merchant configuration. Submit a JSON object containing the exact credential keys supplied by the provider's official merchant documentation. For bKash Tokenized Checkout the supported keys are username, password, app_key and app_secret; the API base URL and TLS verification remain server environment settings.

Submitting replaces the complete credential set. Secret values cannot be retrieved through the UI. To rotate credentials, submit the complete new set. Keep APP_KEY stable and backed up: losing it makes encrypted merchant credentials unrecoverable.

## Before production

Obtain and follow the official merchant integration guide for each provider. Implement and test the provider-specific adapter, callback authentication and server-to-server transaction verification, exact amount/currency/order binding, idempotency, refunds and reconciliation. Complete sandbox acceptance and isolated cPanel backup/restore tests before any live activation.
