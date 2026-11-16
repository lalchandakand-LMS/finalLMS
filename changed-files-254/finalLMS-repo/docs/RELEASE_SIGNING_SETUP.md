# Release signing key setup

The signed release workflow is deliberately fail-closed. It will not publish a release if the signing secret is missing or invalid.

## One-time key creation

Generate a dedicated RSA signing key on a trusted, encrypted administrator workstation. Do not generate it in the repository, a web server, a CI log, or the production cPanel account.

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out aloms-release-private.pem
openssl pkey -in aloms-release-private.pem -pubout -out aloms-release-public.pem
openssl pkey -pubin -in aloms-release-public.pem -outform DER | sha256sum
```

Keep the private key in an encrypted password manager or offline secrets vault. Never commit or upload the private key. Add its complete PEM contents as a GitHub Actions repository secret named `RELEASE_SIGNING_PRIVATE_KEY` (Settings → Secrets and variables → Actions). Restrict repository administration and secret access.

The release workflow derives the public key from the private key and publishes `release-public.pem`, its SHA-256 fingerprint in the signed manifest, and the detached signature as release assets. The public key is not secret, but its fingerprint must be verified through a trusted out-of-band channel before it is pinned in a future production updater configuration. Never trust a public key merely because it was downloaded beside the package it verifies.

## Publishing a release

1. Ensure the intended commit is merged to `main`, reviewed, and all required CI and CodeQL checks pass.
2. Confirm the cPanel package workflow has built successfully from that exact commit.
3. Create and push an immutable semantic-version tag, for example `v1.2.3`, pointing to the reviewed commit.
4. The Signed LMS Release workflow builds a clean cPanel ZIP, calculates package and migration-set hashes, creates a versioned manifest, signs the manifest with the private key, verifies the signature, then publishes the assets to a GitHub Release.
5. Verify the release assets and commit SHA. Do not retarget or overwrite a published version tag. Publish corrections under a new patch version.

## Verify a downloaded release

Download the ZIP, manifest, signature and public key. First compare the public-key fingerprint with the independently trusted fingerprint. Then run:

```bash
openssl dgst -sha256 -verify release-public.pem -signature release-manifest.sig release-manifest.json
sha256sum -c package.sha256
```

Also inspect the manifest's product, version, commit, PHP/Laravel compatibility and migration-set hash. A valid signature proves the manifest was signed by the trusted key; it does not prove that the release is bug-free or safe to install.

## Current boundary

This pipeline publishes signed release artifacts; it does **not** install them on a live website. The Admin Update Center, production public-key pinning, package extraction/activation, migration orchestration and rollback are separate implementation phases. Until those phases and a real cPanel staging rehearsal pass, use the documented reviewed manual deployment procedure. Do not upload arbitrary ZIP files to production.
