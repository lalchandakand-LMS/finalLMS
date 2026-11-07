# Payment gateway setup

## bKash Checkout

The LMS uses the server-side bKash Tokenized Checkout flow. API credentials must remain on the server and must never be placed in Blade templates, JavaScript, Git, or browser requests.

1. Obtain the merchant's sandbox credentials and the Checkout product/API access from bKash.
2. Set the following values in the hosting environment (not in this repository):
   - `BKASH_ENABLED=true`
   - `BKASH_BASE_URL=https://tokenized.sandbox.bka.sh/v1.2.0-beta` for sandbox
   - `BKASH_USERNAME`
   - `BKASH_PASSWORD`
   - `BKASH_APP_KEY`
   - `BKASH_APP_SECRET`
   - `BKASH_VERIFY_SSL=true`
3. Set `APP_URL` to the public HTTPS domain.
4. Register this public callback URL with the merchant integration if the bKash account requires callback registration:
   `https://YOUR-DOMAIN/payments/bkash/callback`
5. Clear Laravel's cached configuration after changing environment variables.
6. Run a sandbox payment and confirm the server-side payment-status query reports the same payment ID, BDT amount, completed status, and transaction ID before granting enrollment.

The callback redirect is not proof of payment. The application executes and queries the transaction server-to-server, checks the amount and payment reference, and only then activates the enrollment. Failed or cancelled checkouts do not grant access. A successful payment with a now-full batch is flagged for manual refund/reassignment.

Switch to the live base URL and live credentials only after bKash merchant approval and sandbox acceptance. Keep TLS verification enabled.

## Nagad

Nagad configuration placeholders are present, but the direct Nagad API adapter is intentionally not enabled until the merchant-specific official integration guide is available. Nagad integrations use merchant-specific IDs and cryptographic key material; the exact endpoints, payload signing/encryption, callback registration, and response verification must match the guide issued for the account.

Keep the merchant private key and Nagad gateway public key in files outside the public web root, with restrictive filesystem permissions. Configure only their file paths:

- `NAGAD_ENABLED=false` until the implementation and sandbox tests are complete
- `NAGAD_MODE=sandbox`
- `NAGAD_BASE_URL` from the merchant guide
- `NAGAD_MERCHANT_ID`
- `NAGAD_MERCHANT_NUMBER`
- `NAGAD_MERCHANT_PRIVATE_KEY_PATH`
- `NAGAD_GATEWAY_PUBLIC_KEY_PATH`

Do not copy private keys or passwords into GitHub issues, commits, or chat messages. Never enable live mode before signed-response verification and sandbox tests pass.
