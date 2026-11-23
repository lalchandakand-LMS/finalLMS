# Admin-managed merchant credentials

The Admin payment gateway page stores merchant secrets in the encrypted `mfs_merchant_configurations.credentials` column using Laravel's encrypted array cast. The model hides the field from serialization. The UI never pre-fills or re-renders a saved secret. Activity log entries contain provider, environment and actor only; they never contain credential values. Validation failures must not flash `credentials_json` into the session.

## Supported credential records

| Provider | Admin fields | Current execution boundary |
|---|---|---|
| bKash | `username`, `password`, `app_key`, `app_secret` | Existing tokenized checkout client is sandbox-only; live checkout remains disabled |
| Nagad | Provider-specific merchant ID, keys, private/public key material and API configuration | Encrypted configuration only; no direct checkout adapter until official merchant protocol and sandbox tests are implemented |
| Rocket | Provider-issued merchant credentials | Encrypted configuration only; no direct checkout adapter yet |
| SSLCOMMERZ | `store_id`, `store_password` | Sandbox or live credentials can be stored; checkout remains unavailable until adapter, callback/IPN validation, reconciliation and sandbox acceptance are complete |

## Admin procedure

1. Open Admin → Payment Gateways.
2. Select the provider and its environment.
3. Paste the exact credential object supplied by that provider. Never put credentials in a support ticket, GitHub issue, source file or chat.
4. Save. The server encrypts the complete credential object; the page will not show it again.
5. To rotate credentials, submit the complete replacement object. A partial object replaces the previous set and can make the provider unconfigured.
6. Keep the provider disabled until its adapter is installed, verified and accepted in sandbox. Saving live credentials is not permission to charge customers.

## SSLCOMMERZ official flow requirements

Use the provider's hosted Session API from the server. Accept the returned hosted checkout URL only from the official HTTPS host for the selected environment. Process the IPN and browser return as untrusted input; call the Order Validation API server-to-server and bind the verified transaction to the local transaction ID, exact amount, BDT currency, store and payment record. A `VALID` status with a risk flag must be held for manual review; do not fulfill it automatically. Deduplicate callbacks and transaction IDs. Do not fulfill on browser redirect alone.

Official references:
- [SSLCOMMERZ Developer Arena](https://developer.sslcommerz.com/)
- [SSLCOMMERZ API v4 documentation](https://developer.sslcommerz.com/doc/v4/)

## Production gate

Before live activation, require a merchant-issued production store, verified TLS, an exact allow-listed production endpoint, IPN registration, sandbox end-to-end evidence, amount/currency/reference mismatch tests, duplicate/replay tests, failure/retry tests, refund/reconciliation acceptance and an operational rollback plan. Live secrets may be stored ahead of time, but the application must keep the gateway unavailable until these controls are implemented and independently verified.
