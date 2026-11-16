# Plugin release signatures

Alom's LMS accepts plugin trust metadata only after a detached Ed25519 signature
has been verified against an explicitly configured trusted public key.

## Creating a signed plugin release

Use the offline release signer from a trusted build workstation. Supply the
private Ed25519 secret key through the environment only; never pass it as a
command-line argument or commit it:

```sh
export PLUGIN_RELEASE_KEY_ID="release-key-2026"
export PLUGIN_RELEASE_PRIVATE_KEY="BASE64_ED25519_SECRET_KEY"
php tools/sign-plugin-release.php \
  plugins/aec/universal-ai-assistant/1.0.0 \
  /secure/private/plugin-release-output
unset PLUGIN_RELEASE_PRIVATE_KEY
```

The tool creates the ZIP, exact manifest JSON, detached Base64 signature and
SHA-256 file. It prints the derived public key so the operator can compare it
with the configured trusted public key. Upload the ZIP, manifest, signature and
key ID through Admin → Plugin Manager. Keep the output directory private and
delete or archive release material according to the institution's key policy.

## Trusted keys

Configure PLUGIN_TRUSTED_PUBLIC_KEYS in the private environment file as a JSON
object mapping immutable key IDs to base64-encoded Ed25519 public keys:

    PLUGIN_TRUSTED_PUBLIC_KEYS='{"release-key-2026":"BASE64_PUBLIC_KEY"}'
    PLUGIN_MAX_PACKAGE_BYTES=52428800

Generate and protect signing keys outside the LMS runtime. Only public keys belong
in LMS configuration. Never commit a private signing key or place one in
plugins/, a plugin archive, a public directory, or the database.

## Signed payload

Release tooling must sign the UTF-8 JSON returned by
PluginSignatureVerifier::payloadForSignature($manifest, $sha256). The payload
contains the recursively key-sorted manifest, the lowercase SHA-256 checksum of
the exact ZIP bytes, and schema identifier aloms-plugin-release-v1. List order
is preserved. The detached signature is base64-encoded Ed25519.

## Verification boundary

The archive must be a regular, readable file inside the private
plugins/.staging/ directory. Symlinks, paths outside staging, malformed
signatures, untrusted key IDs, invalid manifests, mismatched registry identity,
oversized archives and unsafe destination paths are rejected.

A successful verification records the checksum, key ID, verification timestamp
and canonical private destination in plugin_registry in one transaction.
Verification does not extract or execute PHP code. Safe ZIP extraction, package
installation, runtime loading and the Admin Plugin Manager remain separate
security-reviewed stages.
