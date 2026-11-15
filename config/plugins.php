<?php

$trustedKeys = json_decode((string) env('PLUGIN_TRUSTED_PUBLIC_KEYS', '{}'), true);

return [
    // Trusted Ed25519 public keys keyed by immutable release key ID.
    // Values are base64-encoded public keys. Never configure private keys here.
    'trusted_public_keys' => is_array($trustedKeys) ? $trustedKeys : [],
    'max_package_bytes' => (int) env('PLUGIN_MAX_PACKAGE_BYTES', 52428800),
    'max_archive_entries' => (int) env('PLUGIN_MAX_ARCHIVE_ENTRIES', 1000),
    'max_uncompressed_bytes' => (int) env('PLUGIN_MAX_UNCOMPRESSED_BYTES', 104857600),
    'max_file_bytes' => (int) env('PLUGIN_MAX_FILE_BYTES', 20971520),
    'max_compression_ratio' => (int) env('PLUGIN_MAX_COMPRESSION_RATIO', 100),
    'staging_directory' => base_path('plugins/.staging'),
];
