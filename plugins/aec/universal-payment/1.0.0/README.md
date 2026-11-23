# Universal Payment

This is the runtime package source for the provider-neutral payment gateway registry.

## Architecture

- The LMS Payment Core owns local payment creation, amount calculation, order fulfillment, callback idempotency and the gateway catalog.
- This package owns the bKash Tokenized Checkout adapter and its provider HTTP client.
- The entrypoint registers the adapter using `PluginContext::registerPaymentGateway()`.
- The manifest declares `payments.gateway`; the runtime rejects registration without that capability.
- Only an active package with a valid trusted release signature and verified installed-file hashes can execute.

## Release / installation

This source folder is not itself a trusted installation. Build a plugin archive using the official plugin packaging process, sign it with a configured trusted release key, upload it through Admin → Plugins, review its requested capability, install and activate it. Until then, bKash is intentionally absent from the gateway catalog.

## Current scope

- bKash Tokenized Checkout, BDT, sandbox endpoint allow-list.
- Encrypted merchant credential lookup through the shared Admin merchant credential service.
- Server-side provider status verification.
- Live mode is not implemented or enabled.
- Nagad and SSLCOMMERZ must be separate adapters/packages with provider-specific verification and sandbox acceptance tests.
