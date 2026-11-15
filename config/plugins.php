<?php

$trustedKeys = json_decode((string) env('PLUGIN_TRUSTED_PUBLIC_KEYS', '{}'), true);

return [
    // Trusted Ed25519 public keys keyed by immutable release key ID.
    // Values are base64-encoded public keys. Never configure private keys here.
    'trusted_public_keys' => is_array($trustedKeys) ? $trustedKeys : [],
    'max_package_bytes' => (int) env('PLUGIN_MAX_PACKAGE_BYTES', 52428800),
    'staging_directory' => base_path('plugins/.staging'),
];
